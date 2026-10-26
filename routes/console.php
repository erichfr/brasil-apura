<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('tse:status', function () {
    $this->table(['Configuração', 'Valor'], [
        ['Coleta', config('tse.enabled') ? 'Habilitada' : 'Desativada'],
        ['Cargo', 'Presidente da República'],
        ['Primeiro turno', config('tse.rounds.1.election_code')],
        ['Segundo turno', config('tse.rounds.2.election_code')],
        ['Intervalo mínimo', config('tse.poll_seconds').' segundos'],
        ['Situação', 'Ingestão EA20 implementada; consulte tse:results'],
    ]);
})->purpose('Exibe a configuração TSE sem realizar consultas externas');

Schedule::command('tse:sync --all-scopes')->everyMinute()->withoutOverlapping(30)->when(fn () => config('tse.enabled'));

Schedule::command('tse:sync-abroad')->everyFiveMinutes()->withoutOverlapping(120)->when(fn () => config('tse.enabled'));
