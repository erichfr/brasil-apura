<?php

namespace App\Console\Commands;

use App\Services\Tse\TseResultsService;
use App\Services\Tse\TseSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncTseResults extends Command
{
    protected $signature = 'tse:sync {--round=* : Turno 1 ou 2; padrão ambos} {--scope=* : br ou sigla da UF; padrão br} {--all-scopes : Brasil, UFs e exterior}';

    protected $description = 'Coleta resultados presidenciais oficiais e registra snapshots verificáveis';

    public function handle(TseSyncService $sync, TseResultsService $transport): int
    {
        if (! config('tse.enabled')) {
            $this->error('Coleta desativada. Configure TSE_SYNC_ENABLED=true no .env.');

            return self::FAILURE;
        }
        $rounds = $this->option('round') ?: ['1', '2'];
        $scopes = $this->option('all-scopes') ? config('tse.scopes') : ($this->option('scope') ?: ['br']);
        // Validate the entire batch before any request or write.
        foreach ($rounds as $round) {
            if (! in_array($round, ['1', '2'], true)) {
                $this->error('Turno inválido.');

                return self::INVALID;
            }
            foreach ($scopes as $scope) {
                try {
                    $transport->resultsUrl((int) $round, $scope);
                } catch (\InvalidArgumentException) {
                    $this->error('Abrangência inválida.');

                    return self::INVALID;
                }
            }
        }
        $failed = false;
        foreach (array_unique($rounds) as $round) {
            foreach (array_unique($scopes) as $scope) {
                try {
                    $result = $sync->sync((int) $round, $scope);
                    $this->line("$round / $scope: ".$result['status'].(isset($result['message']) ? ' — '.$result['message'] : ''));
                    $failed = $failed || $result['status'] === 'failed';
                    if (($result['error_code'] ?? null) === 'blocked') {
                        $this->warn('Lote interrompido para respeitar o bloqueio da fonte.');

                        return self::FAILURE;
                    }
                } catch (Throwable $e) {
                    report($e);
                    $this->error("$round / $scope: falha de configuração ou infraestrutura. Consulte o log.");
                    $failed = true;
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
