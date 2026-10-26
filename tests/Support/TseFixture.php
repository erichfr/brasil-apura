<?php

namespace Tests\Support;

/** Synthetic test data. Never seeded or displayed as official results. */
class TseFixture
{
    public static function result(array $replace = []): array
    {
        return array_replace_recursive([
            'ele' => '6258', 't' => '2', 'f' => 'o', 'sup' => 'n',
            'tpabr' => 'br', 'cdabr' => 'br', 'dg' => '25/10/2026', 'hg' => '19:00:01',
            'dt' => '25/10/2026', 'ht' => '19:00:00', 'idg' => 'test-generation',
            'dv' => 's', 'tf' => 'n', 'and' => 'p',
            'carg' => [[
                'cd' => '1', 'fed' => [],
                'agr' => [['par' => [[
                    'sg' => 'TESTE', 'nfed' => '',
                    'cand' => [[
                        'sqcand' => 'fixture-candidate', 'nmu' => 'CANDIDATO SINTÉTICO DE TESTE',
                        'n' => '99', 'dvt' => 'Válido', 'vap' => '80', 'pvap' => '100,00',
                        'pvapn' => '100', 'seq' => '1', 'e' => 'n', 'st' => '',
                    ]],
                ]]]],
            ]],
            's' => ['ts' => '10', 'st' => '5', 'pst' => '50,00', 'pstn' => '50'],
            'e' => ['c' => '100', 'a' => '20'],
            'v' => ['tv' => '100', 'vv' => '80', 'vvc' => '80', 'vb' => '10', 'tvn' => '10'],
        ], $replace);
    }

    public static function catalog(): array
    {
        return [
            'f' => 'o',
            'arq' => [['tp' => 'u', 'dir' => '<base>/<ambiente>/<ciclo>/<cd_eleicao>/dados/<uf>']],
            'pl' => array_map(fn ($number) => [
                'cd' => $number === 1 ? '3220' : '3221', 'c' => 'ele2026',
                'dt' => $number === 1 ? '04/10/2026' : '25/10/2026',
                'e' => [['cd' => $number === 1 ? '6257' : '6258', 't' => (string) $number,
                    'abr' => [['cd' => 'br', 'cp' => [['cd' => '1']]]]]],
            ], [1, 2]),
        ];
    }
}
