<?php

namespace App\Services\Tse;

use App\Models\AbroadCity;
use App\Models\CurrentResult;
use App\Models\ElectionRound;
use App\Models\Scope;
use App\Models\Source;
use App\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TseCountryResultsService
{
    public function overview(int $round): array
    {
        $election = ElectionRound::where('tse_election_code', config("tse.rounds.$round.election_code"))->firstOrFail();
        $cities = AbroadCity::whereIn('id', DB::table('abroad_round_cities')->where('election_round_id', $election->id)->select('abroad_city_id'))->orderBy('name')->get();
        $ids = $cities->pluck('scope_id');
        $pointers = CurrentResult::where('election_round_id', $election->id)->whereIn('scope_id', $ids)->orderBy('scope_id')->pluck('snapshot_id', 'scope_id');
        $sources = Source::where('election_round_id', $election->id)->whereIn('scope_id', $ids)->get()->keyBy('scope_id');
        $lastRunId = SyncRun::whereIn('source_id', $sources->pluck('id'))->max('id');
        $fingerprint = hash('sha256', json_encode([$pointers, $sources->pluck('last_success_at'), $lastRunId, $cities->pluck('reference_sha256')]));
        $overview = Cache::remember("tse:countries:v1:$round:$fingerprint", 60, function () use ($round, $election, $cities, $ids, $sources) {
            $rows = CurrentResult::with(['snapshot.results', 'snapshot.payload'])->where('election_round_id', $election->id)->whereIn('scope_id', $ids)->get()->keyBy('scope_id');
            $lastIds = SyncRun::whereIn('source_id', $sources->pluck('id'))->whereIn('status', ['updated', 'unchanged', 'not_modified', 'failed', 'archived'])->selectRaw('MAX(id)')->groupBy('source_id');
            $lastRuns = SyncRun::whereIn('id', $lastIds)->get()->keyBy('source_id');
            $countries = [];
            foreach ($cities->groupBy('country_code') as $code => $group) {
                $totals = array_fill_keys(['total_votes', 'valid_votes', 'blank_votes', 'null_votes', 'turnout', 'abstentions', 'total_sections', 'counted_sections'], 0);
                $candidates = [];
                $localities = [];
                $times = ['reported' => [], 'generated' => [], 'collected' => [], 'success' => []];
                $available = 0;
                $final = true;
                $failed = false;
                foreach ($group as $city) {
                    $snapshot = $rows->get($city->scope_id)?->snapshot;
                    $source = $sources->get($city->scope_id);
                    $ready = $snapshot && $snapshot->votes_disclosed;
                    $localities[] = [
                        'code' => $city->tse_code, 'name' => $city->name, 'available' => (bool) $ready,
                        'total_votes' => $ready ? $snapshot->total_votes : null,
                        'source_url' => $source?->url, 'sha256' => $snapshot?->payload?->sha256,
                        'snapshot_id' => $snapshot?->id,
                        'source_reported_at' => $snapshot?->source_reported_at?->toIso8601String(),
                        'source_generated_at' => $snapshot?->source_generated_at?->toIso8601String(),
                        'collected_at' => $snapshot?->collected_at?->toIso8601String(),
                        'geographic_evidence' => $city->geographic_evidence,
                    ];
                    if (! $ready) {
                        $final = false;

                        continue;
                    }
                    $available++;
                    foreach ($totals as $key => $sum) {
                        $totals[$key] = $sum === null || $snapshot->$key === null ? null : $sum + (int) $snapshot->$key;
                    }
                    $final = $final && $snapshot->official_status === 'f';
                    $failed = $failed || in_array($lastRuns->get($source?->id)?->status, ['failed', 'archived'], true);
                    foreach (['reported' => $snapshot->source_reported_at, 'generated' => $snapshot->source_generated_at, 'collected' => $snapshot->collected_at, 'success' => $source?->last_success_at] as $key => $value) {
                        if ($value) {
                            $times[$key][] = $value->toIso8601String();
                        }
                    }
                    foreach ($snapshot->results as $candidate) {
                        $id = $candidate->candidate_id;
                        if (! isset($candidates[$id])) {
                            $meta = $candidate->official_metadata;
                            $candidates[$id] = [
                                'id' => $id, 'name' => $meta['ballot_name'], 'number' => $meta['ballot_number'],
                                'party' => $meta['party'], 'federation' => $meta['federation'],
                                'votes' => 0, 'valid_votes_percentage' => null,
                                'official_status' => $candidate->official_status,
                                'vote_destination' => $candidate->vote_destination,
                                'official_metadata' => ['aggregation' => 'sum_of_official_localities'],
                            ];
                        }
                        $candidates[$id]['votes'] += $candidate->votes;
                        if ($candidates[$id]['vote_destination'] !== $candidate->vote_destination) {
                            $candidates[$id]['vote_destination'] = 'Destinações distintas';
                        }
                        if ($candidates[$id]['official_status'] !== $candidate->official_status) {
                            $candidates[$id]['official_status'] = '';
                        }
                    }
                }
                $complete = $available === $group->count();
                if ($available) {
                    foreach ($candidates as &$candidate) {
                        if ($candidate['vote_destination'] === 'Válido' && ($totals['valid_votes'] ?? 0) > 0) {
                            $candidate['valid_votes_percentage'] = bcdiv(bcmul((string) $candidate['votes'], '100', 0), (string) $totals['valid_votes'], 4);
                        }
                    }
                    unset($candidate);
                    $totals['counted_sections_percentage'] = $complete && ($totals['total_sections'] ?? 0) > 0
                        ? bcdiv(bcmul((string) $totals['counted_sections'], '100', 0), (string) $totals['total_sections'], 4) : null;
                }
                usort($candidates, fn ($a, $b) => ($b['votes'] <=> $a['votes']) ?: ($a['number'] <=> $b['number']));
                foreach ($times as &$values) {
                    sort($values);
                }
                unset($values);
                $country = $group->first();
                $countries[$code] = [
                    'round' => $round, 'scope' => 'zz', 'country' => $code, 'scope_name' => $country->country_name,
                    'election_date' => $election->election_date->format('Y-m-d'),
                    'status' => ! $available ? 'unavailable' : (! $complete ? 'partial' : ($failed ? 'stale' : 'available')),
                    'freshness_reason' => $failed ? 'sync_failed' : null,
                    'last_success_at' => $times['success'][0] ?? null, 'last_sync_status' => $failed ? 'failed' : 'aggregated',
                    'source_url' => null, 'localities' => $localities,
                    'coverage' => ['available' => $available, 'expected' => $group->count()],
                    'data' => ! $available ? null : [
                        'snapshot_id' => 'country-'.$code.'-'.hash('sha256', json_encode(array_column($localities, 'snapshot_id'))),
                        'source_reported_at' => $times['reported'] ? end($times['reported']) : null,
                        'source_generated_at' => $times['generated'] ? end($times['generated']) : null,
                        'collected_at' => $times['collected'] ? end($times['collected']) : null,
                        'official_status' => $final && $complete ? 'f' : 'p', 'votes_disclosed' => true,
                        'totals' => $totals, 'candidates' => array_values($candidates),
                        'source_sha256' => hash('sha256', json_encode(array_column($localities, 'sha256'))),
                        'official_metadata' => [
                            'aggregation' => 'sum_of_official_localities',
                            'source_reported_at_min' => $times['reported'][0] ?? null,
                            'source_reported_at_max' => $times['reported'] ? end($times['reported']) : null,
                            'geographic_reference_sha256' => $country->reference_sha256,
                        ],
                    ],
                ];
            }
            uasort($countries, fn ($a, $b) => strcoll($a['scope_name'], $b['scope_name']));
            $exteriorId = Scope::where('code', 'zz')->value('id');
            $official = CurrentResult::with('snapshot.results')->where('election_round_id', $election->id)->where('scope_id', $exteriorId)->first()?->snapshot;
            $fields = ['total_votes', 'valid_votes', 'blank_votes', 'null_votes', 'turnout', 'abstentions', 'total_sections', 'counted_sections'];
            $ready = count(array_filter($countries, fn ($c) => $c['status'] !== 'unavailable' && $c['coverage']['available'] === $c['coverage']['expected']));
            $differences = [];
            foreach ($fields as $field) {
                $values = array_map(fn ($c) => $c['data']['totals'][$field] ?? null, $countries);
                $sum = in_array(null, $values, true) ? null : array_sum($values);
                if ($official && $sum !== $official->$field) {
                    $differences[$field] = ['cities' => $sum, 'official_exterior' => $official->$field];
                }
            }
            if ($official) {
                $candidateTotals = [];
                foreach ($countries as $country) {
                    foreach ($country['data']['candidates'] ?? [] as $candidate) {
                        $candidateTotals[$candidate['id']] = ($candidateTotals[$candidate['id']] ?? 0) + $candidate['votes'];
                    }
                }
                foreach ($official->results as $candidate) {
                    if (($candidateTotals[$candidate->candidate_id] ?? 0) !== $candidate->votes) {
                        $differences['candidate_'.$candidate->candidate_id] = ['cities' => $candidateTotals[$candidate->candidate_id] ?? 0, 'official_exterior' => $candidate->votes];
                    }
                    unset($candidateTotals[$candidate->candidate_id]);
                }
                foreach ($candidateTotals as $id => $votes) {
                    if ($votes !== 0) {
                        $differences['extra_candidate_'.$id] = ['cities' => $votes, 'official_exterior' => null];
                    }
                }
            }

            return [
                'countries' => $countries,
                'reconciliation' => [
                    'countries_complete' => $ready, 'countries_expected' => count($countries),
                    'cities_expected' => $cities->count(),
                    'cities_available' => array_sum(array_column(array_column($countries, 'coverage'), 'available')),
                    'matches' => $cities->isNotEmpty() && $ready === count($countries) && $official && $differences === [],
                    'differences' => $differences,
                ],
            ];
        });
        // Recompute freshness against the current clock, even for cached aggregates.
        foreach ($overview['countries'] as &$country) {
            $limit = ($country['data']['official_status'] ?? null) === 'f'
                ? config('tse.final_poll_seconds') + config('tse.stale_after_seconds') : max(300, config('tse.poll_seconds')) + config('tse.stale_after_seconds');
            if ($country['status'] === 'available' && (! $country['last_success_at'] || CarbonImmutable::parse($country['last_success_at'])->addSeconds($limit)->isPast())) {
                $country['status'] = 'stale';
                $country['freshness_reason'] = 'confirmation_expired';
            }
        }

        return $overview;
    }

    public function get(int $round, string $country): array
    {
        $data = $this->overview($round);
        if (! isset($data['countries'][$country])) {
            throw new InvalidArgumentException('País não cadastrado neste turno.');
        }

        return $data['countries'][$country];
    }
}
