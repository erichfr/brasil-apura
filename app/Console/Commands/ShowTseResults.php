<?php

namespace App\Console\Commands;

use App\Services\Tse\TseStoredResultsService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ShowTseResults extends Command
{
    protected $signature = 'tse:results {--round=2} {--scope=br} {--json : Saída JSON dos dados locais}';

    protected $description = 'Consulta resultados armazenados sem acessar o TSE';

    public function handle(TseStoredResultsService $results): int
    {
        if (! in_array($this->option('round'), ['1', '2'], true)) {
            $this->error('Turno inválido.');

            return self::INVALID;
        }
        try {
            $result = $results->get((int) $this->option('round'), $this->option('scope'));
        } catch (InvalidArgumentException) {
            $this->error('Abrangência inválida.');

            return self::INVALID;
        }
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }
        $this->table(['Campo', 'Valor'], [
            ['Abrangência', $result['scope_name']], ['Turno', $result['round']], ['Estado', $result['status']],
            ['Totalização (UTC)', $result['data']['source_reported_at'] ?? '—'],
            ['Geração (UTC)', $result['data']['source_generated_at'] ?? '—'],
            ['Coleta do snapshot (UTC)', $result['data']['collected_at'] ?? '—'],
            ['Última confirmação (UTC)', $result['last_success_at'] ?? '—'],
        ]);
        $this->table(['Candidato', 'Partido', 'Votos', '% válidos', 'Situação oficial'], array_map(
            fn ($candidate) => [$candidate['name'], $candidate['party'], $candidate['votes'], $candidate['valid_votes_percentage'] ?? '—', $candidate['official_status']],
            $result['data']['candidates'] ?? [],
        ));

        return self::SUCCESS;
    }
}
