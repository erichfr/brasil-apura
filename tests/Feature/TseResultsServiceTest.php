<?php

namespace Tests\Feature;

use App\Services\Tse\TseResultsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class TseResultsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'tse.enabled' => true]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_only_presidential_urls_for_known_rounds_and_scopes_are_built(): void
    {
        $service = new TseResultsService;
        $this->assertSame('https://resultados.tse.jus.br/oficial/ele2026/6258/dados/sp/sp-c0001-e006258-u.json', $service->resultsUrl(2, 'sp'));
        $this->expectException(InvalidArgumentException::class);
        $service->resultsUrl(1, 'https://example.com');
    }

    public function test_disabled_collection_never_calls_the_source(): void
    {
        config(['tse.enabled' => false]);
        Http::fake();
        try {
            (new TseResultsService)->fetchRaw(1, 'br');
            $this->fail('Coleta deveria estar desativada.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('desativada', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_raw_bytes_hash_and_conditional_headers_are_preserved(): void
    {
        $raw = '{"fixture":"synthetic transport test, not election results"}';
        Http::fake(['*' => Http::response($raw, 200, ['ETag' => '"v2"'])]);
        $result = (new TseResultsService)->fetchRaw(1, 'br', '"v1"');
        $this->assertSame($raw, $result['raw']);
        $this->assertSame(hash('sha256', $raw), $result['sha256']);
        $this->assertSame('"v2"', $result['etag']);
        Http::assertSent(fn ($request) => $request->hasHeader('If-None-Match', '"v1"'));
    }

    public function test_304_does_not_create_a_new_payload(): void
    {
        Http::fake(['*' => Http::response('', 304)]);
        $result = (new TseResultsService)->fetchRaw(1, 'br', '"v1"');
        $this->assertSame('not_modified', $result['status']);
        $this->assertNull($result['raw']);
        $this->assertNull($result['sha256']);
    }

    public function test_invalid_json_is_rejected(): void
    {
        Http::fake(['*' => Http::response('<html>unavailable</html>', 200)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JSON inválida');
        (new TseResultsService)->fetchRaw(1, 'br');
    }

    public function test_rate_limit_stops_requests_to_other_scopes_too(): void
    {
        Http::fake(['*' => Http::response('', 429, ['Retry-After' => '1200'])]);
        $service = new TseResultsService;
        try {
            $service->fetchRaw(1, 'br');
        } catch (RuntimeException) {
        }
        try {
            $service->fetchRaw(1, 'sp');
            $this->fail('Cooldown deveria bloquear a consulta.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('frequência', $e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_repeated_requests_for_one_file_are_throttled(): void
    {
        Http::fake(['*' => Http::response('{"fixture":true}', 200)]);
        $service = new TseResultsService;
        $service->fetchRaw(2, 'br');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('frequência');
        $service->fetchRaw(2, 'br');
    }

    public function test_oversized_response_is_rejected(): void
    {
        config(['tse.max_body_bytes' => 5]);
        Http::fake(['*' => Http::response('{"fixture":true}', 200)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tamanho permitido');
        (new TseResultsService)->fetchRaw(1, 'br');
    }
}
