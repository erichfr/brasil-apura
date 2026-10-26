<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            DB::table('elections')->updateOrInsert(['year' => 2026], [
                'name' => 'Eleições presidenciais 2026', 'office_code' => '0001',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $id = DB::table('elections')->where('year', 2026)->value('id');
            foreach (config('tse.rounds') as $number => $round) {
                DB::table('election_rounds')->updateOrInsert(['election_id' => $id, 'number' => $number], [
                    'election_date' => $round['date'], 'tse_election_code' => $round['election_code'],
                    'tse_contest_code' => $round['contest_code'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $names = ['br' => 'Brasil', 'ac' => 'Acre', 'al' => 'Alagoas', 'ap' => 'Amapá', 'am' => 'Amazonas', 'ba' => 'Bahia', 'ce' => 'Ceará', 'df' => 'Distrito Federal', 'es' => 'Espírito Santo', 'go' => 'Goiás', 'ma' => 'Maranhão', 'mt' => 'Mato Grosso', 'ms' => 'Mato Grosso do Sul', 'mg' => 'Minas Gerais', 'pa' => 'Pará', 'pb' => 'Paraíba', 'pr' => 'Paraná', 'pe' => 'Pernambuco', 'pi' => 'Piauí', 'rj' => 'Rio de Janeiro', 'rn' => 'Rio Grande do Norte', 'rs' => 'Rio Grande do Sul', 'ro' => 'Rondônia', 'rr' => 'Roraima', 'sc' => 'Santa Catarina', 'sp' => 'São Paulo', 'se' => 'Sergipe', 'to' => 'Tocantins', 'zz' => 'Exterior'];
            foreach ($names as $code => $name) {
                DB::table('scopes')->updateOrInsert(['code' => $code], [
                    'name' => $name, 'type' => match ($code) {
                        'br' => 'national', 'zz' => 'abroad', default => 'state'
                    },
                ]);
            }
        });
    }
}
