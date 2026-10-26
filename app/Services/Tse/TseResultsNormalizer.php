<?php

namespace App\Services\Tse;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use JsonException;

class TseResultsNormalizer
{
    public const SCHEMA_VERSION = 'EA20-2026-10-13';

    public function normalize(string $raw, int $round, string $scope): array
    {
        try {
            $data = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->invalid('JSON');
        }
        if (! is_array($data)) {
            $this->invalid('raiz');
        }
        $city = preg_match('/^zz[0-9]{5}$/D', $scope) === 1;
        foreach ([
            'ele' => config("tse.rounds.$round.election_code"),
            't' => (string) $round, 'f' => 'o', 'sup' => 'n',
            'tpabr' => $city ? 'mu' : ($scope === 'br' ? 'br' : 'uf'), 'cdabr' => $city ? substr($scope, 2) : $scope,
        ] as $key => $expected) {
            if ($expected === null || ($data[$key] ?? null) !== $expected) {
                $this->invalid($key);
            }
        }
        foreach (['dv' => ['s', 'n'], 'tf' => ['s', 'n'], 'and' => ['n', 'p', 'f']] as $key => $allowed) {
            if (! in_array($data[$key] ?? null, $allowed, true)) {
                $this->invalid($key);
            }
        }
        $this->text($data['idg'] ?? null, 'idg', 64);
        $generated = $this->date($data['dg'] ?? null, $data['hg'] ?? null, 'dg/hg');
        $reported = null;
        if (! empty($data['dt']) || ! empty($data['ht'])) {
            $reported = $this->date($data['dt'] ?? null, $data['ht'] ?? null, 'dt/ht');
        } elseif ($data['and'] !== 'n') {
            $this->invalid('dt/ht');
        }
        $offices = $this->list($data['carg'] ?? null, 'carg');
        if (count($offices) !== 1 || ($offices[0]['cd'] ?? null) !== '1' || isset($data['perg'])) {
            $this->invalid('cargo presidencial');
        }
        foreach (['s', 'e', 'v'] as $key) {
            if (! isset($data[$key]) || ! is_array($data[$key]) || array_is_list($data[$key])) {
                $this->invalid($key);
            }
        }
        $disclosed = $data['dv'] === 's';
        $sections = $this->integer($data['s']['ts'] ?? null, 's.ts');
        $counted = $this->integer($data['s']['st'] ?? null, 's.st');
        if ($counted > $sections) {
            $this->invalid('s.st > s.ts');
        }
        $totals = [
            'total_votes' => $this->integer($data['v']['tv'] ?? null, 'v.tv'),
            'valid_votes' => $this->integer($data['v']['vv'] ?? null, 'v.vv'),
            'blank_votes' => $this->integer($data['v']['vb'] ?? null, 'v.vb'),
            'null_votes' => $this->integer($data['v']['tvn'] ?? null, 'v.tvn'),
        ];
        if (array_sum(array_slice($totals, 1)) > $totals['total_votes']) {
            $this->invalid('totais de votos');
        }
        $candidates = [];
        $federations = [];
        foreach ($this->list($offices[0]['fed'] ?? [], 'fed') as $federation) {
            $federations[$this->text($federation['n'] ?? null, 'fed.n', 32)] =
                $this->text($federation['nm'] ?? null, 'fed.nm', 255);
        }
        foreach ($this->list($offices[0]['agr'] ?? null, 'agr') as $group) {
            foreach ($this->list($group['par'] ?? null, 'par') as $party) {
                $partyName = $this->text($party['sg'] ?? null, 'par.sg', 255);
                foreach ($this->list($party['cand'] ?? null, 'cand') as $candidate) {
                    $id = $this->text($candidate['sqcand'] ?? null, 'sqcand', 32);
                    if (isset($candidates[$id])) {
                        $this->invalid('sqcand duplicado');
                    }
                    $identity = [
                        'tse_candidate_id' => $id,
                        'ballot_name' => $this->text($candidate['nmu'] ?? null, 'nmu', 255),
                        'ballot_number' => $this->text($candidate['n'] ?? null, 'cand.n', 8),
                        'party' => $partyName,
                        'federation' => $federations[$party['nfed'] ?? ''] ?? null,
                    ];
                    $votes = $this->integer($candidate['vap'] ?? null, 'vap');
                    $destination = $this->text($candidate['dvt'] ?? null, 'dvt', 255);
                    $officialPercentage = $this->percentage($candidate['pvapn'] ?? $candidate['pvap'] ?? null, 'pvap');
                    $validPercentage = null;
                    if ($disclosed && $destination === 'Válido' && $totals['valid_votes'] > 0) {
                        $validPercentage = bcdiv(bcmul((string) $votes, '100', 0), (string) $totals['valid_votes'], 4);
                        if (bccomp($validPercentage, '100', 4) > 0) {
                            $this->invalid('percentual válido');
                        }
                    }
                    $candidates[$id] = [
                        'identity' => $identity, 'votes' => $votes,
                        'valid_votes_percentage' => $validPercentage,
                        'official_status' => $this->text($candidate['st'] ?? '', 'st', 255, true),
                        'vote_destination' => $destination,
                        'official_metadata' => [
                            ...$identity,
                            'official_percentage' => $officialPercentage,
                            'official_percentage_denominator' => 'v.vvc',
                            'official_sequence' => $candidate['seq'] ?? null,
                            'elected' => $candidate['e'] ?? null,
                            'vice_candidates' => $candidate['vs'] ?? [],
                            'replaced_candidates' => $candidate['subs'] ?? [],
                        ],
                    ];
                }
            }
        }

        return [
            'generation_id' => $data['idg'],
            'snapshot' => [
                'source_reported_at' => $reported, 'source_generated_at' => $generated,
                'votes_disclosed' => $disclosed,
                ...array_map(fn ($value) => $disclosed ? $value : null, $totals),
                'turnout' => $this->optionalInteger($data['e']['c'] ?? null, 'e.c'),
                'abstentions' => $this->optionalInteger($data['e']['a'] ?? null, 'e.a'),
                'total_sections' => $sections, 'counted_sections' => $counted,
                'counted_sections_percentage' => $this->percentage($data['s']['pstn'] ?? $data['s']['pst'] ?? null, 's.pst'),
                'official_status' => $data['and'],
                'official_metadata' => [
                    'final_totalization' => $data['tf'], 'disclosure' => $data['dv'],
                    'mathematically_defined' => $data['md'] ?? null,
                    'pending_finalization_reason' => $data['mntf'] ?? null,
                    'schema_version' => self::SCHEMA_VERSION,
                    'source_timezone' => config('tse.display_timezone'),
                ],
            ],
            // dv=n is never presented as observed zero votes.
            'candidates' => $disclosed ? array_values($candidates) : [],
        ];
    }

    private function date(mixed $date, mixed $time, string $field): CarbonImmutable
    {
        if (! is_string($date) || ! is_string($time)) {
            $this->invalid($field);
        }
        $value = $date.' '.$time;
        $parsed = DateTimeImmutable::createFromFormat('!d/m/Y H:i:s', $value, new \DateTimeZone(config('tse.display_timezone')));
        if (! $parsed || $parsed->format('d/m/Y H:i:s') !== $value) {
            $this->invalid($field);
        }

        return CarbonImmutable::instance($parsed)->utc();
    }

    private function integer(mixed $value, string $field): int
    {
        if (! is_string($value) && ! is_int($value)) {
            $this->invalid($field);
        }
        $string = (string) $value;
        if (! preg_match('/^(0|[1-9][0-9]*)$/D', $string) || strlen($string) > 18) {
            $this->invalid($field);
        }

        return (int) $string;
    }

    private function optionalInteger(mixed $value, string $field): ?int
    {
        return $value === null || $value === '' ? null : $this->integer($value, $field);
    }

    private function percentage(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) && ! is_int($value)) {
            $this->invalid($field);
        }
        $number = str_replace(',', '.', (string) $value);
        if (! preg_match('/^\d{1,3}(\.\d{1,9})?$/D', $number) || bccomp($number, '100', 9) > 0) {
            $this->invalid($field);
        }

        return $number;
    }

    private function text(mixed $value, string $field, int $max, bool $empty = false): string
    {
        if (! is_string($value) || (! $empty && trim($value) === '') || mb_strlen($value) > $max) {
            $this->invalid($field);
        }

        return $value;
    }

    private function list(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $this->invalid($field);
        }
        foreach ($value as $item) {
            if (! is_array($item)) {
                $this->invalid($field);
            }
        }

        return $value;
    }

    private function invalid(string $field): never
    {
        throw new TseException('Arquivo incompatível com o leiaute presidencial: '.$field.'.', 'invalid_payload');
    }
}
