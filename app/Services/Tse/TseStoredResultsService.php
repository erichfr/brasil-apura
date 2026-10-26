<?php

namespace App\Services\Tse;

use App\Models\CurrentResult;
use App\Models\ElectionRound;
use App\Models\Scope;
use App\Models\Snapshot;
use App\Models\Source;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Cache;

class TseStoredResultsService
{
    public function __construct(private TseResultsService $transport) {}

    /** Reads local storage only. Never calls the TSE. */
    public function get(int $round, string $scope): array
    {
        $this->transport->resultsUrl($round, $scope);
        $electionRound = ElectionRound::where('tse_election_code', config("tse.rounds.$round.election_code"))->firstOrFail();
        $scopeModel = Scope::where('code', $scope)->firstOrFail();
        $pointer = CurrentResult::where('election_round_id', $electionRound->id)->where('scope_id', $scopeModel->id)->first();
        $source = Source::where('election_round_id', $electionRound->id)->where('scope_id', $scopeModel->id)->where('file_type', 'EA20')->first();
        $lastRun = $source ? SyncRun::where('source_id', $source->id)->whereIn('status', ['updated', 'unchanged', 'not_modified', 'failed', 'archived'])->latest('id')->first() : null;
        $data = $pointer ? Cache::remember('tse:stored:v1:'.$pointer->snapshot_id, 3600, function () use ($pointer) {
            $snapshot = Snapshot::with(['results', 'payload'])->findOrFail($pointer->snapshot_id);
            $candidates = $snapshot->results->map(fn ($result) => [
                'id' => $result->candidate_id,
                'name' => $result->official_metadata['ballot_name'],
                'number' => $result->official_metadata['ballot_number'],
                'party' => $result->official_metadata['party'],
                'federation' => $result->official_metadata['federation'],
                'votes' => $result->votes,
                'valid_votes_percentage' => $result->valid_votes_percentage,
                'official_status' => $result->official_status,
                'vote_destination' => $result->vote_destination,
                'official_metadata' => $result->official_metadata,
            ])->sort(function ($a, $b) {
                return ($b['votes'] <=> $a['votes'])
                    ?: (($a['official_metadata']['official_sequence'] ?? PHP_INT_MAX) <=> ($b['official_metadata']['official_sequence'] ?? PHP_INT_MAX))
                    ?: ($a['number'] <=> $b['number']);
            })->values()->all();

            return [
                'snapshot_id' => $snapshot->id,
                'source_reported_at' => $snapshot->source_reported_at?->toIso8601String(),
                'source_generated_at' => $snapshot->source_generated_at?->toIso8601String(),
                'collected_at' => $snapshot->collected_at->toIso8601String(),
                'official_status' => $snapshot->official_status,
                'votes_disclosed' => $snapshot->votes_disclosed,
                'totals' => $snapshot->only(['total_votes', 'valid_votes', 'blank_votes', 'null_votes', 'turnout', 'abstentions', 'total_sections', 'counted_sections', 'counted_sections_percentage']),
                'source_sha256' => $snapshot->payload->sha256,
                'official_metadata' => $snapshot->official_metadata,
                'candidates' => $candidates,
            ];
        }) : null;
        $final = ($data['official_status'] ?? null) === 'f';
        $staleAfter = $final ? config('tse.final_poll_seconds') + config('tse.stale_after_seconds') : config('tse.stale_after_seconds');
        $stale = ! $source?->last_success_at || $source->last_success_at->addSeconds($staleAfter)->isPast();
        $failed = in_array($lastRun?->status, ['failed', 'archived'], true);
        $freshnessReason = $failed ? ($lastRun?->status === 'archived' ? 'older_generation' : 'sync_failed') : ($stale ? 'confirmation_expired' : null);

        return [
            'round' => $round, 'scope' => $scope, 'scope_name' => $scopeModel->name,
            'election_date' => $electionRound->election_date->format('Y-m-d'),
            'status' => ! $data ? 'unavailable' : ($failed || $stale ? 'stale' : ($data['votes_disclosed'] ? 'available' : 'not_disclosed')),
            'freshness_reason' => $data ? $freshnessReason : null,
            'last_success_at' => $source?->last_success_at?->toIso8601String(),
            'last_sync_status' => $lastRun?->status,
            'source_url' => $source?->url,
            'data' => $data,
        ];
    }
}
