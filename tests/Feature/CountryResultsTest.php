<?php

namespace Tests\Feature;

use App\Services\Tse\TseAbroadRegistry;
use App\Services\Tse\TseCountryResultsService;
use App\Services\Tse\TseSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TseFixture;
use Tests\TestCase;

class CountryResultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->travelTo(CarbonImmutable::parse('2026-10-26T16:00:00Z'));
        config(['tse.enabled' => true]);
        Cache::flush();
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), 'ele-c.json')) {
                return Http::response(TseFixture::catalog());
            }
            if (str_ends_with($request->url(), '-cm.json')) {
                return Http::response(['f' => 'o', 'abr' => [['cd' => 'zz', 'mu' => [
                    ['cd' => '29955', 'nm' => 'LISBOA'], ['cd' => '30341', 'nm' => 'PORTO'], ['cd' => '29513', 'nm' => 'CHICAGO'],
                ]]]]);
            }
            preg_match('/zz([0-9]{5})?-/', $request->url(), $match);
            $code = $match[1] ?? '';
            $exterior = $code === '';
            $valid = $exterior ? 340 : ($code === '30341' ? 180 : 80);
            $first = $exterior ? 220 : ($code === '30341' ? 100 : 60);
            $sections = $exterior ? 30 : 10;
            $blank = $exterior ? 30 : 10;
            $fixture = TseFixture::result([
                'tpabr' => $exterior ? 'uf' : 'mu', 'cdabr' => $exterior ? 'zz' : $code,
                'and' => 'f', 'tf' => 's',
                'v' => ['tv' => (string) ($valid + $blank * 2), 'vv' => (string) $valid, 'vvc' => (string) $valid, 'vb' => (string) $blank, 'tvn' => (string) $blank],
                's' => ['ts' => (string) $sections, 'st' => (string) $sections, 'pstn' => '100'],
                'e' => ['c' => (string) ($valid + $blank * 2), 'a' => $exterior ? '60' : '20'],
            ]);
            $candidate = &$fixture['carg'][0]['agr'][0]['par'][0]['cand'][0];
            $candidate['vap'] = (string) $first;
            $candidate['pvapn'] = bcdiv(bcmul((string) $first, '100', 0), (string) $valid, 9);
            $second = $candidate;
            $second['sqcand'] = 'fixture-other';
            $second['nmu'] = 'OUTRO CANDIDATO SINTÉTICO';
            $second['n'] = '98';
            $second['vap'] = (string) ($valid - $first);
            $second['pvapn'] = bcdiv(bcmul((string) ($valid - $first), '100', 0), (string) $valid, 9);
            $fixture['carg'][0]['agr'][0]['par'][0]['cand'][] = $second;

            return Http::response($fixture);
        });
        app(TseAbroadRegistry::class)->register(2);
    }

    public function test_country_sums_only_its_cities_and_recalculates_percentages(): void
    {
        $sync = app(TseSyncService::class);
        foreach (['29955', '30341', '29513'] as $code) {
            $this->assertSame('updated', $sync->sync(2, 'zz'.$code)['status']);
        }
        $sync->sync(2, 'zz');
        $service = app(TseCountryResultsService::class);
        $portugal = $service->get(2, 'PRT');
        $this->assertSame('Portugal', $portugal['scope_name']);
        $this->assertSame(300, $portugal['data']['totals']['total_votes']);
        $this->assertSame(260, $portugal['data']['totals']['valid_votes']);
        $this->assertSame(160, $portugal['data']['candidates'][0]['votes']);
        $this->assertSame('61.5384', $portugal['data']['candidates'][0]['valid_votes_percentage']);
        $this->assertSame(['available' => 2, 'expected' => 2], $portugal['coverage']);
        $this->assertCount(2, $portugal['localities']);
        $this->assertTrue($service->overview(2)['reconciliation']['matches']);
        $this->assertSame(100, $service->get(2, 'USA')['data']['totals']['total_votes']);
        $this->get('/?round=2&scope=zz&country=PRT')->assertOk()->assertSee('Votos em Portugal')->assertSee('LISBOA')->assertSee('PORTO');
        $this->getJson('/api/results?round=2&scope=zz&country=PRT')->assertOk()
            ->assertJsonPath('result.country', 'PRT')
            ->assertJsonPath('result.data.totals.total_votes', 300);
        Http::assertSentCount(6);
    }

    public function test_missing_city_is_explicitly_partial_and_never_counted_as_zero(): void
    {
        app(TseSyncService::class)->sync(2, 'zz29955');
        $country = app(TseCountryResultsService::class)->get(2, 'PRT');
        $this->assertSame('partial', $country['status']);
        $this->assertSame(['available' => 1, 'expected' => 2], $country['coverage']);
        $this->assertSame(100, $country['data']['totals']['total_votes']);
        $missing = collect($country['localities'])->firstWhere('code', '30341');
        $this->assertNull($missing['total_votes']);
        $this->assertFalse(app(TseCountryResultsService::class)->overview(2)['reconciliation']['matches']);
        $this->get('/?round=2&scope=zz&country=PRT')->assertOk()->assertSee('Importação incompleta');
    }

    public function test_country_without_observations_is_unavailable_and_invalid_countries_are_rejected(): void
    {
        $country = app(TseCountryResultsService::class)->get(2, 'PRT');
        $this->assertSame('unavailable', $country['status']);
        $this->assertNull($country['data']);
        $this->getJson('/api/results?round=2&scope=zz&country=XXX')->assertUnprocessable()->assertJsonValidationErrors('country');
        $this->get('/?round=2&scope=zz')->assertOk()->assertSee('country=PRT', false);
        Http::assertSentCount(2);
    }
}
