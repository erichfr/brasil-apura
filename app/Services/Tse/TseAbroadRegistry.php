<?php

namespace App\Services\Tse;

use App\Models\AbroadCity;
use App\Models\ElectionRound;
use App\Models\Scope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TseAbroadRegistry
{
    public function __construct(private TseResultsService $transport, private TseElectionCatalog $catalog) {}

    public function register(int $round): void
    {
        $this->catalog->verify();
        $catalog = Cache::remember("tse:municipal-catalog:$round", 3600, function () use ($round) {
            $response = $this->transport->fetchMunicipalConfiguration($round);
            $data = json_decode($response['raw'], true, 128, JSON_THROW_ON_ERROR);
            if (($data['f'] ?? null) !== 'o') {
                throw new TseException('Cadastro de localidades não é oficial.', 'invalid_catalog');
            }
            $cities = collect($data['abr'] ?? [])->firstWhere('cd', 'zz')['mu'] ?? null;
            if (! is_array($cities) || count($cities) === 0) {
                throw new TseException('Cadastro de localidades do exterior ausente.', 'invalid_catalog');
            }
            if (! Storage::disk('local')->put('tse/catalog/'.$response['sha256'].'.json', $response['raw'])) {
                throw new TseException('Falha ao preservar o cadastro do exterior.', 'storage');
            }

            return ['cities' => $cities, 'sha256' => $response['sha256']];
        });
        $path = resource_path('data/abroad-cities.json');
        $reference = json_decode(file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
        $hash = hash_file('sha256', $path);
        $mapping = collect($reference['cities'])->keyBy('tse_code');
        foreach ($catalog['cities'] as $city) {
            if (! preg_match('/^[0-9]{5}$/D', $city['cd'] ?? '') || ! $mapping->has($city['cd'])) {
                throw new TseException('Localidade sem correspondência geográfica verificada: '.($city['cd'] ?? '?'), 'unknown_city');
            }
            if (($mapping[$city['cd']]['name'] ?? null) !== $city['nm']) {
                throw new TseException('Nome de localidade mudou no catálogo oficial.', 'unknown_city');
            }
        }
        $roundModel = ElectionRound::where('number', $round)->where('tse_election_code', config("tse.rounds.$round.election_code"))->firstOrFail();
        DB::transaction(function () use ($catalog, $mapping, $hash, $roundModel) {
            foreach ($catalog['cities'] as $official) {
                $city = $mapping[$official['cd']];
                $scope = Scope::firstOrCreate(['code' => 'zz'.$official['cd']], ['name' => $official['nm'], 'type' => 'abroad_city']);
                $row = AbroadCity::updateOrCreate(['tse_code' => $official['cd']], [
                    'scope_id' => $scope->id, 'name' => $official['nm'],
                    'country_code' => $city['country_code'], 'country_name' => $city['country_name'],
                    'geographic_evidence' => $city['evidence'], 'reference_sha256' => $hash,
                ]);
                DB::table('abroad_round_cities')->updateOrInsert([
                    'election_round_id' => $roundModel->id, 'abroad_city_id' => $row->id,
                ], ['catalog_sha256' => $catalog['sha256']]);
            }
        });
    }
}
