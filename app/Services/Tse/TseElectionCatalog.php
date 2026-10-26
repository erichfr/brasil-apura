<?php

namespace App\Services\Tse;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class TseElectionCatalog
{
    public function __construct(private TseResultsService $transport) {}

    public function verify(): array
    {
        if (! config('tse.enabled')) {
            throw new TseException('Coleta desativada na configuração.', 'disabled');
        }

        return Cache::remember('tse:catalog:2026:v1', 3600, function () {
            $response = $this->transport->fetchConfiguration();
            $data = json_decode($response['raw'], true, 128, JSON_THROW_ON_ERROR);
            if (($data['f'] ?? null) !== 'o' || ! is_array($data['pl'] ?? null)) {
                throw new TseException('Configuração oficial inválida.', 'invalid_catalog');
            }
            $path = collect($data['arq'] ?? [])->firstWhere('tp', 'u')['dir'] ?? null;
            if ($path !== '<base>/<ambiente>/<ciclo>/<cd_eleicao>/dados/<uf>') {
                throw new TseException('Caminho EA20 mudou na configuração oficial.', 'invalid_catalog');
            }
            foreach (config('tse.rounds') as $number => $round) {
                $found = false;
                foreach ($data['pl'] as $contest) {
                    foreach ($contest['e'] ?? [] as $election) {
                        if (($contest['cd'] ?? null) === $round['contest_code']
                            && ($contest['c'] ?? null) === 'ele2026'
                            && ($contest['dt'] ?? null) === date('d/m/Y', strtotime($round['date']))
                            && ($election['cd'] ?? null) === $round['election_code']
                            && ($election['t'] ?? null) === (string) $number) {
                            foreach ($election['abr'] ?? [] as $scope) {
                                foreach ($scope['cp'] ?? [] as $office) {
                                    $found = $found || (($scope['cd'] ?? null) === 'br' && ($office['cd'] ?? null) === '1');
                                }
                            }
                        }
                    }
                }
                if (! $found) {
                    throw new TseException('Eleição presidencial não confirmada na configuração oficial.', 'invalid_catalog');
                }
            }
            $storagePath = 'tse/catalog/'.$response['sha256'].'.json';
            if (! Storage::disk('local')->put($storagePath, $response['raw'])) {
                throw new TseException('Não foi possível preservar a configuração oficial.', 'storage');
            }

            return ['sha256' => $response['sha256'], 'storage_path' => $storagePath, 'collected_at' => $response['collected_at']];
        });
    }
}
