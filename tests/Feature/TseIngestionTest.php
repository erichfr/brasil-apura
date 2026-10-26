<?php

namespace Tests\Feature;

use App\Models\CurrentResult;
use App\Models\Snapshot;
use App\Models\Source;
use App\Models\SourcePayload;
use App\Models\SyncRun;
use App\Services\Tse\TseStoredResultsService;
use App\Services\Tse\TseSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TseFixture;
use Tests\TestCase;

class TseIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->travelTo(CarbonImmutable::parse('2026-10-26T12:00:00Z'));
        config(['tse.enabled' => true, 'tse.final_poll_seconds' => 60]);
        Cache::flush();
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function sourceResponse(array|string $fixture, int $status = 200): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            '*ele-c.json' => Http::response(TseFixture::catalog()),
            '*-u.json' => Http::response($fixture, $status, ['ETag' => '"test"']),
        ]);
    }

    public function test_ingestion_preserves_raw_payload_and_publishes_a_traceable_snapshot(): void
    {
        $fixture = TseFixture::result();
        $this->sourceResponse($fixture);
        $result = app(TseSyncService::class)->sync(2, 'br');
        $this->assertSame('updated', $result['status']);
        $this->assertDatabaseCount('snapshots', 1);
        $this->assertDatabaseCount('snapshot_candidate_results', 1);
        $payload = SourcePayload::firstOrFail();
        $raw = Storage::disk('local')->get($payload->storage_path);
        $this->assertSame(hash('sha256', $raw), $payload->sha256);
        $this->assertSame($fixture, json_decode($raw, true));
        $stored = app(TseStoredResultsService::class)->get(2, 'br');
        $this->assertSame('available', $stored['status']);
        $this->assertSame(100, $stored['data']['totals']['total_votes']);
        $this->assertSame('CANDIDATO SINTÉTICO DE TESTE', $stored['data']['candidates'][0]['name']);
        $this->assertSame('2026-10-26T12:00:00+00:00', $stored['last_success_at']);
        Http::assertSentCount(2);
    }

    public function test_unchanged_payload_does_not_duplicate_history(): void
    {
        $this->sourceResponse(TseFixture::result());
        $service = app(TseSyncService::class);
        $service->sync(2, 'br');
        $this->travel(61)->seconds();
        $this->assertSame('unchanged', $service->sync(2, 'br')['status']);
        $this->assertDatabaseCount('source_payloads', 1);
        $this->assertDatabaseCount('snapshots', 1);
        $this->assertDatabaseCount('sync_runs', 2);
    }

    public function test_304_confirms_freshness_without_changing_the_snapshot_collection_time(): void
    {
        $this->sourceResponse(TseFixture::result());
        $service = app(TseSyncService::class);
        $service->sync(2, 'br');
        $collected = Snapshot::firstOrFail()->collected_at;
        $this->travel(61)->seconds();
        $this->sourceResponse('', 304);
        $this->assertSame('not_modified', $service->sync(2, 'br')['status']);
        $this->assertEquals($collected, Snapshot::firstOrFail()->collected_at);
        $this->assertTrue(Source::firstOrFail()->last_success_at->greaterThan($collected));
        $this->assertDatabaseCount('snapshots', 1);
    }

    public function test_corrupt_results_preserve_last_good_data_and_archive_received_bytes(): void
    {
        $this->sourceResponse(TseFixture::result());
        $service = app(TseSyncService::class);
        $service->sync(2, 'br');
        $original = CurrentResult::firstOrFail()->snapshot_id;
        $success = Source::firstOrFail()->last_success_at;
        $this->travel(61)->seconds();
        $this->sourceResponse(TseFixture::result(['f' => 's']));
        $this->assertSame('failed', $service->sync(2, 'br')['status']);
        $this->assertSame($original, CurrentResult::firstOrFail()->snapshot_id);
        $this->assertEquals($success, Source::firstOrFail()->last_success_at);
        $this->assertDatabaseCount('snapshots', 1);
        $run = SyncRun::latest('id')->firstOrFail();
        Storage::disk('local')->assertExists($run->raw_path);
        $this->assertSame('invalid_payload', $run->error_code);
        $this->assertSame('stale', app(TseStoredResultsService::class)->get(2, 'br')['status']);
        $this->assertSame('sync_failed', app(TseStoredResultsService::class)->get(2, 'br')['freshness_reason']);
    }

    public function test_older_cdn_generation_is_archived_without_replacing_latest_results(): void
    {
        $this->sourceResponse(TseFixture::result());
        $service = app(TseSyncService::class);
        $service->sync(2, 'br');
        $id = CurrentResult::firstOrFail()->snapshot_id;
        $this->travel(61)->seconds();
        $this->sourceResponse(TseFixture::result(['hg' => '18:00:01', 'ht' => '18:00:00', 'idg' => 'older']));
        $this->assertSame('archived', $service->sync(2, 'br')['status']);
        $this->assertSame($id, CurrentResult::firstOrFail()->snapshot_id);
        $this->assertDatabaseCount('snapshots', 2);
    }

    public function test_a_newer_revision_keeps_both_observations_even_if_votes_decrease(): void
    {
        $this->sourceResponse(TseFixture::result());
        $service = app(TseSyncService::class);
        $service->sync(2, 'br');
        $this->travel(61)->seconds();
        $fixture = TseFixture::result(['hg' => '19:05:01', 'ht' => '19:05:00', 'idg' => 'revision']);
        $fixture['carg'][0]['agr'][0]['par'][0]['cand'][0]['vap'] = '75';
        $this->sourceResponse($fixture);
        $this->assertSame('updated', $service->sync(2, 'br')['status']);
        $this->assertDatabaseCount('snapshots', 2);
        $this->assertSame(80, Snapshot::oldest('id')->firstOrFail()->results()->firstOrFail()->votes);
        $this->assertSame(75, app(TseStoredResultsService::class)->get(2, 'br')['data']['candidates'][0]['votes']);
    }

    public function test_http_errors_are_logged_without_erasing_results(): void
    {
        $this->sourceResponse(TseFixture::result());
        $service = app(TseSyncService::class);
        $service->sync(2, 'br');
        $this->travel(61)->seconds();
        $this->sourceResponse('', 503);
        $this->assertSame('failed', $service->sync(2, 'br')['status']);
        $this->assertSame(503, SyncRun::latest('id')->firstOrFail()->http_status);
        $this->assertDatabaseCount('snapshots', 1);
    }

    public function test_unconfirmed_catalog_prevents_results_requests(): void
    {
        Http::fake(['*ele-c.json' => Http::response(['f' => 's'])]);
        $this->assertSame('failed', app(TseSyncService::class)->sync(2, 'br')['status']);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('snapshots', 0);
    }

    public function test_local_reads_never_make_external_requests(): void
    {
        Http::fake();
        $result = app(TseStoredResultsService::class)->get(2, 'br');
        $this->assertSame('unavailable', $result['status']);
        Http::assertNothingSent();
    }

    public function test_invalid_command_batch_is_rejected_before_network_access(): void
    {
        Http::fake();
        $this->artisan('tse:sync', ['--round' => ['1', 'invalid']])->assertExitCode(2);
        Http::assertNothingSent();
    }

    public function test_expired_confirmation_is_distinguished_from_a_failed_sync(): void
    {
        $this->sourceResponse(TseFixture::result());
        app(TseSyncService::class)->sync(2, 'br');
        $this->travel(181)->seconds();
        $result = app(TseStoredResultsService::class)->get(2, 'br');
        $this->assertSame('stale', $result['status']);
        $this->assertSame('confirmation_expired', $result['freshness_reason']);
        $this->get('/?round=2&scope=br')->assertOk()
            ->assertSee('A confirmação automática destes dados está pendente.')
            ->assertDontSee('A última sincronização falhou');
    }
}
