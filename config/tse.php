<?php

return [
    'enabled' => (bool) env('TSE_SYNC_ENABLED', false),
    'base_url' => 'https://resultados.tse.jus.br',
    'environment' => 'oficial',
    'cycle' => 'ele2026',
    'office' => '0001',
    'display_timezone' => 'America/Sao_Paulo',
    'final_poll_seconds' => max(3600, (int) env('TSE_FINAL_POLL_SECONDS', 3600)),
    'poll_seconds' => max(60, (int) env('TSE_POLL_SECONDS', 60)),
    'timeout_seconds' => (int) env('TSE_TIMEOUT_SECONDS', 15),
    'connect_timeout_seconds' => (int) env('TSE_CONNECT_TIMEOUT_SECONDS', 5),
    'stale_after_seconds' => (int) env('TSE_STALE_AFTER_SECONDS', 180),
    'max_body_bytes' => (int) env('TSE_MAX_BODY_BYTES', 10485760),
    'rounds' => [
        1 => ['election_code' => '6257', 'contest_code' => '3220', 'date' => '2026-10-04'],
        2 => ['election_code' => '6258', 'contest_code' => '3221', 'date' => '2026-10-25'],
    ],
    'scopes' => ['br', 'ac', 'al', 'ap', 'am', 'ba', 'ce', 'df', 'es', 'go', 'ma', 'mt', 'ms', 'mg', 'pa', 'pb', 'pr', 'pe', 'pi', 'rj', 'rn', 'rs', 'ro', 'rr', 'sc', 'sp', 'se', 'to', 'zz'],
];
