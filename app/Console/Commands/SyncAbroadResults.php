<?php

namespace App\Console\Commands;

use App\Models\AbroadCity;
use App\Models\ElectionRound;
use App\Services\Tse\TseAbroadRegistry;
use App\Services\Tse\TseException;
use App\Services\Tse\TseSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncAbroadResults extends Command
{
    protected $signature = 'tse:sync-abroad {--round=* : Turnos; padrão ambos} {--country= : Código ISO de três letras opcional}';

    protected $description = 'Importa os resultados presidenciais das localidades do exterior para agregação por país';

    public function handle(TseAbroadRegistry $registry, TseSyncService $sync): int
    {
        if (! config('tse.enabled')) {
            $this->error('Coleta desativada.');

            return self::FAILURE;
        }
        $rounds = $this->option('round') ?: ['1', '2'];
        foreach ($rounds as $round) {
            if (! in_array($round, ['1', '2'], true)) {
                $this->error('Turno inválido.');

                return self::INVALID;
            }
        }
        $country = $this->option('country');
        if ($country !== null && ! preg_match('/^[A-Z]{3}$/D', $country)) {
            $this->error('País inválido.');

            return self::INVALID;
        }
        $failed = false;
        foreach (array_unique($rounds) as $round) {
            try {
                $registry->register((int) $round);
            } catch (TseException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $roundId = ElectionRound::where('tse_election_code', config("tse.rounds.$round.election_code"))->value('id');
            $cities = AbroadCity::whereIn('id', DB::table('abroad_round_cities')->where('election_round_id', $roundId)->select('abroad_city_id'))
                ->when($country, fn ($query) => $query->where('country_code', $country))->orderBy('country_name')->orderBy('name')->get();
            if ($cities->isEmpty()) {
                $this->error('Nenhuma localidade cadastrada para o país.');

                return self::INVALID;
            }
            foreach ($cities as $city) {
                $result = $sync->sync((int) $round, 'zz'.$city->tse_code);
                $this->line("$round / {$city->country_code} / {$city->name}: ".$result['status'].(isset($result['message']) ? ' — '.$result['message'] : ''));
                $failed = $failed || $result['status'] === 'failed';
                if (($result['error_code'] ?? null) === 'blocked') {
                    return self::FAILURE;
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
