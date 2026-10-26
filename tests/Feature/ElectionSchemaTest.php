<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\ElectionRound;
use App\Models\Scope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ElectionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_is_idempotent_and_does_not_create_results_or_users(): void
    {
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('elections', 1);
        $this->assertDatabaseCount('election_rounds', 2);
        $this->assertDatabaseCount('scopes', 29);
        $this->assertDatabaseCount('candidates', 0);
        $this->assertDatabaseCount('snapshots', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame('6258', Election::firstOrFail()->rounds()->where('number', 2)->firstOrFail()->tse_election_code);
        $this->assertSame('2026-10-25', ElectionRound::where('number', 2)->firstOrFail()->election_date->format('Y-m-d'));
        $this->assertSame('abroad', Scope::where('code', 'zz')->firstOrFail()->type);
    }

    public function test_a_round_cannot_be_duplicated(): void
    {
        $this->seed();
        $round = DB::table('election_rounds')->first();
        $this->expectException(QueryException::class);
        DB::table('election_rounds')->insert([
            'election_id' => $round->election_id, 'number' => $round->number,
            'election_date' => $round->election_date, 'tse_election_code' => 'different',
            'tse_contest_code' => '3220',
        ]);
    }

    public function test_a_payload_cannot_be_repeated_for_the_same_source(): void
    {
        $this->seed();
        $source = DB::table('sources')->insertGetId([
            'election_round_id' => ElectionRound::firstOrFail()->id,
            'scope_id' => Scope::where('code', 'br')->firstOrFail()->id,
            'file_type' => 'EA20', 'url' => 'https://resultados.tse.jus.br/fixture',
        ]);
        $payload = ['source_id' => $source, 'sha256' => str_repeat('a', 64),
            'storage_path' => 'test-only.json', 'size_bytes' => 2, 'collected_at' => now()];
        DB::table('source_payloads')->insert($payload);
        $this->expectException(QueryException::class);
        DB::table('source_payloads')->insert($payload);
    }
}
