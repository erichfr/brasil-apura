<?php

namespace App\Services\Tse;

use App\Models\Candidate;
use App\Models\CurrentResult;
use App\Models\ElectionRound;
use App\Models\RoundCandidate;
use App\Models\Scope;
use App\Models\Snapshot;
use App\Models\SnapshotCandidateResult;
use App\Models\Source;
use App\Models\SourcePayload;
use App\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TseSyncService
{
    public function __construct(
        private TseResultsService $transport,
        private TseResultsNormalizer $normalizer,
        private TseElectionCatalog $catalog,
    ) {}

    public function sync(int $round, string $scope): array
    {
        $url = $this->transport->resultsUrl($round, $scope);
        $electionRound = ElectionRound::where('tse_election_code', config("tse.rounds.$round.election_code"))->firstOrFail();
        $scopeModel = Scope::where('code', $scope)->firstOrFail();

        return Cache::lock("tse:sync:$round:$scope", max(90, config('tse.timeout_seconds') * 3 + 15))
            ->block(2, function () use ($url, $round, $scope, $electionRound, $scopeModel) {
                $source = Source::firstOrCreate([
                    'election_round_id' => $electionRound->id, 'scope_id' => $scopeModel->id, 'file_type' => 'EA20',
                ], ['url' => $url, 'schema_version' => TseResultsNormalizer::SCHEMA_VERSION]);
                $current = CurrentResult::where('election_round_id', $electionRound->id)->where('scope_id', $scopeModel->id)->first();
                $snapshot = $current?->snapshot;
                $interval = $snapshot?->official_status === 'f'
                    ? config('tse.final_poll_seconds') : config('tse.poll_seconds');
                if ($source->last_success_at && $source->last_success_at->addSeconds($interval)->isFuture()) {
                    return ['status' => 'deferred', 'snapshot_id' => $current?->snapshot_id];
                }
                $run = SyncRun::create(['source_id' => $source->id, 'started_at' => now('UTC'), 'status' => 'running']);

                try {
                    $catalog = $this->catalog->verify();
                    $response = $this->transport->fetchRaw($round, $scope, $source->etag, $source->last_modified);
                    $run->update(['http_status' => $response['http_status']]);
                    $collected = CarbonImmutable::parse($response['collected_at'])->utc();

                    if ($response['status'] === 'not_modified') {
                        if (! $snapshot) {
                            throw new TseException('A fonte retornou 304 sem snapshot local.', 'missing_snapshot', 304);
                        }
                        DB::transaction(function () use ($source, $run, $response, $collected, $snapshot) {
                            $this->markSuccess($source, $response, $collected);
                            $run->update(['status' => 'not_modified', 'source_payload_id' => $snapshot->source_payload_id, 'finished_at' => now('UTC')]);
                        });

                        return ['status' => 'not_modified', 'snapshot_id' => $snapshot->id];
                    }

                    $storagePath = 'tse/results/'.$source->id.'/'.$response['sha256'].'.json';
                    if (! Storage::disk('local')->put($storagePath, $response['raw'])) {
                        throw new TseException('Não foi possível preservar a resposta oficial.', 'storage');
                    }
                    $run->update(['response_sha256' => $response['sha256'], 'raw_path' => $storagePath]);
                    $normalized = $this->normalizer->normalize($response['raw'], $round, $scope);
                    $normalized['snapshot']['official_metadata']['catalog_sha256'] = $catalog['sha256'];
                    $normalized['snapshot']['official_metadata']['catalog_collected_at'] = $catalog['collected_at'];

                    return DB::transaction(function () use ($normalized, $source, $response, $collected, $storagePath, $run, $electionRound, $scopeModel) {
                        $payload = SourcePayload::firstOrCreate([
                            'source_id' => $source->id, 'sha256' => $response['sha256'],
                        ], [
                            'generation_id' => $normalized['generation_id'], 'storage_path' => $storagePath,
                            'size_bytes' => strlen($response['raw']), 'etag' => $response['etag'],
                            'last_modified' => $response['last_modified'],
                            'source_reported_at' => $normalized['snapshot']['source_reported_at'], 'collected_at' => $collected,
                        ]);
                        $snapshot = Snapshot::where('source_payload_id', $payload->id)->first();
                        if (! $snapshot) {
                            $snapshot = Snapshot::create([
                                'election_round_id' => $electionRound->id, 'scope_id' => $scopeModel->id,
                                'source_payload_id' => $payload->id, 'collected_at' => $collected,
                                ...$normalized['snapshot'],
                            ]);
                            foreach ($normalized['candidates'] as $result) {
                                $identity = $result['identity'];
                                $candidate = Candidate::firstOrCreate([
                                    'election_id' => $electionRound->election_id,
                                    'tse_candidate_id' => $identity['tse_candidate_id'],
                                ], $identity);
                                RoundCandidate::firstOrCreate(['election_round_id' => $electionRound->id, 'candidate_id' => $candidate->id]);
                                unset($result['identity']);
                                SnapshotCandidateResult::create(['snapshot_id' => $snapshot->id, 'candidate_id' => $candidate->id, ...$result]);
                            }
                        }
                        $pointer = CurrentResult::where('election_round_id', $electionRound->id)->where('scope_id', $scopeModel->id)->lockForUpdate()->first();
                        $previous = $pointer?->snapshot;
                        // IDG identifies a generation but is not assumed to be monotonic.
                        $promote = ! $previous || $previous->id === $snapshot->id
                            || ($snapshot->source_generated_at && (! $previous->source_generated_at || $snapshot->source_generated_at->greaterThan($previous->source_generated_at)));
                        $status = 'archived';
                        if ($promote) {
                            $status = $previous?->id === $snapshot->id ? 'unchanged' : 'updated';
                            CurrentResult::updateOrCreate([
                                'election_round_id' => $electionRound->id, 'scope_id' => $scopeModel->id,
                            ], ['snapshot_id' => $snapshot->id, 'updated_at' => now('UTC')]);
                            $this->markSuccess($source, $response, $collected);
                        }
                        $run->update(['source_payload_id' => $payload->id, 'status' => $status, 'finished_at' => now('UTC')]);

                        return ['status' => $status, 'snapshot_id' => $snapshot->id];
                    });
                } catch (Throwable $e) {
                    $reason = $e instanceof TseException ? $e->reason : 'internal';
                    $message = $e instanceof TseException ? $e->getMessage() : 'Falha interna na sincronização; consulte os logs do servidor.';
                    $run->update([
                        'status' => in_array($reason, ['deferred', 'disabled'], true) ? 'deferred' : 'failed',
                        'error_code' => $reason, 'error_message' => $message,
                        'http_status' => $e instanceof TseException ? $e->httpStatus : $run->http_status,
                        'finished_at' => now('UTC'),
                    ]);
                    Log::warning('Sincronização TSE não concluída.', ['sync_run_id' => $run->id, 'source_id' => $source->id, 'reason' => $reason]);
                    if (! $e instanceof TseException) {
                        report($e);
                    }

                    return ['status' => $run->status, 'error_code' => $reason, 'message' => $message, 'snapshot_id' => $current?->snapshot_id];
                }
            });
    }

    private function markSuccess(Source $source, array $response, CarbonImmutable $collected): void
    {
        $source->update([
            'etag' => $response['status'] === 'not_modified' ? ($response['etag'] ?? $source->etag) : $response['etag'],
            'last_modified' => $response['status'] === 'not_modified' ? ($response['last_modified'] ?? $source->last_modified) : $response['last_modified'],
            'last_success_at' => $collected, 'schema_version' => TseResultsNormalizer::SCHEMA_VERSION,
        ]);
    }
}
