<?php

namespace App\Services\Tse;

use App\Models\AbroadCity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use JsonException;

class TseResultsService
{
    public function resultsUrl(int $round, string $scope): string
    {
        $city = preg_match('/^zz[0-9]{5}$/D', $scope) && AbroadCity::where('tse_code', substr($scope, 2))->exists();
        if (! array_key_exists($round, config('tse.rounds')) || (! in_array($scope, config('tse.scopes'), true) && ! $city)) {
            throw new InvalidArgumentException('Turno ou abrangência inválidos.');
        }
        $election = config("tse.rounds.$round.election_code");
        $padded = str_pad($election, 6, '0', STR_PAD_LEFT);

        $directory = $city ? 'zz' : $scope;

        return "https://resultados.tse.jus.br/oficial/ele2026/$election/dados/$directory/$scope-c0001-e$padded-u.json";
    }

    public function fetchMunicipalConfiguration(int $round): array
    {
        $this->resultsUrl($round, 'zz');
        $election = config("tse.rounds.$round.election_code");
        $padded = str_pad($election, 6, '0', STR_PAD_LEFT);

        return $this->request("https://resultados.tse.jus.br/oficial/ele2026/$election/config/mun-e$padded-cm.json");
    }

    public function fetchConfiguration(): array
    {
        return $this->request('https://resultados.tse.jus.br/oficial/comum/config/ele-c.json');
    }

    public function fetchRaw(int $round, string $scope, ?string $etag = null, ?string $lastModified = null): array
    {
        return $this->request($this->resultsUrl($round, $scope), $etag, $lastModified);
    }

    private function request(string $url, ?string $etag = null, ?string $lastModified = null): array
    {
        if (! config('tse.enabled')) {
            throw new TseException('Coleta desativada na configuração.', 'disabled');
        }

        return Cache::lock('tse:transport', max(30, config('tse.timeout_seconds') + 10))->block(2, function () use ($url, $etag, $lastModified) {
            $pollKey = 'tse:poll:'.hash('sha256', $url);
            if (Cache::has('tse:cooldown') || Cache::has($pollKey)) {
                throw new TseException('Consulta adiada pelo controle de frequência.', 'deferred');
            }
            if (! RateLimiter::attempt('tse:global', 10, fn () => true, 1)) {
                throw new TseException('Consulta adiada pelo limite global de frequência.', 'deferred');
            }
            Cache::put($pollKey, true, config('tse.poll_seconds'));
            try {
                $response = Http::acceptJson()
                    ->withHeaders(array_filter(['If-None-Match' => $etag, 'If-Modified-Since' => $lastModified]))
                    ->connectTimeout(config('tse.connect_timeout_seconds'))
                    ->timeout(config('tse.timeout_seconds'))
                    ->withOptions([
                        'allow_redirects' => false,
                        'on_progress' => function ($total, $downloaded) {
                            if (max($total, $downloaded) > config('tse.max_body_bytes')) {
                                throw new TseException('Arquivo excede o tamanho permitido.', 'oversized');
                            }
                        },
                    ])->get($url);
            } catch (ConnectionException) {
                $this->backoff($pollKey);
                throw new TseException('Não foi possível conectar à fonte oficial.', 'connection');
            }

            $status = $response->status();
            if (in_array($status, [403, 429], true)) {
                $retry = $response->header('Retry-After');
                $delay = is_numeric($retry) ? (int) $retry : max(0, (strtotime($retry) ?: time()) - time());
                Cache::put('tse:cooldown', true, max(600, $delay));
                throw new TseException('Fonte temporariamente indisponível; coleta suspensa.', 'blocked', $status);
            }
            if (! in_array($status, [200, 304], true)) {
                if ($status === 404) {
                    Cache::put($pollKey, true, 600);
                } else {
                    $this->backoff($pollKey);
                }
                throw new TseException('Arquivo oficial indisponível (HTTP '.$status.').', 'http', $status);
            }
            if ($status === 304 && $etag === null && $lastModified === null) {
                throw new TseException('Resposta condicional sem versão anterior.', 'unexpected_304', 304);
            }

            $raw = $status === 304 ? null : $response->body();
            if ($raw !== null) {
                if (strlen($raw) > config('tse.max_body_bytes')) {
                    throw new TseException('Arquivo excede o tamanho permitido.', 'oversized', $status);
                }
                try {
                    $decoded = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new TseException('Resposta JSON inválida.', 'invalid_json', $status);
                }
                if (! is_array($decoded) || array_is_list($decoded)) {
                    throw new TseException('Estrutura JSON inesperada.', 'invalid_json', $status);
                }
            }

            Cache::forget($pollKey.':failures');

            return [
                'status' => $raw === null ? 'not_modified' : 'downloaded',
                'http_status' => $status,
                'raw' => $raw, 'sha256' => $raw === null ? null : hash('sha256', $raw),
                'etag' => $response->header('ETag') ?: null,
                'last_modified' => $response->header('Last-Modified') ?: null,
                'collected_at' => now('UTC')->toIso8601String(),
            ];
        });
    }

    private function backoff(string $pollKey): void
    {
        $attempts = min(6, (int) Cache::get($pollKey.':failures', 0) + 1);
        Cache::put($pollKey.':failures', $attempts, 86400);
        Cache::put($pollKey, true, max(config('tse.poll_seconds'), min(3600, 60 * (2 ** $attempts))));
    }
}
