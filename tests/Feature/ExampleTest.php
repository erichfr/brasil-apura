<?php

namespace Tests\Feature;

use App\Services\Tse\TseSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TseFixture;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_dashboard_shows_an_explicit_empty_state_without_external_requests(): void
    {
        Http::fake();
        $this->get('/')->assertOk()->assertSee('Votação não disponível')->assertSee('Brasil');
        Http::assertNothingSent();
    }

    public function test_dashboard_displays_stored_votes_and_api_matches_the_selection(): void
    {
        config(['tse.enabled' => true]);
        Storage::fake('local');
        Http::fake([
            '*ele-c.json' => Http::response(TseFixture::catalog()),
            '*-u.json' => Http::response(TseFixture::result()),
        ]);
        app(TseSyncService::class)->sync(2, 'br');
        $this->get('/?round=2&scope=br')->assertOk()->assertSee('CANDIDATO SINTÉTICO DE TESTE')->assertSee('100,00%');
        $this->getJson('/api/results?round=2&scope=br')->assertOk()
            ->assertJsonPath('result.scope', 'br')->assertJsonPath('result.round', 2)
            ->assertJsonPath('result.data.totals.total_votes', 100);
        $this->get('/?round=1&scope=sp')->assertOk()->assertSee('São Paulo')->assertDontSee('CANDIDATO SINTÉTICO DE TESTE');
        Http::assertSentCount(2);
    }

    public function test_api_rejects_unknown_scopes_and_rounds(): void
    {
        Http::fake();
        $this->getJson('/api/results?round=3&scope=unknown')->assertUnprocessable()->assertJsonValidationErrors(['round', 'scope']);
        Http::assertNothingSent();
    }

    public function test_external_candidate_names_are_escaped(): void
    {
        config(['tse.enabled' => true]);
        Storage::fake('local');
        $fixture = TseFixture::result();
        $fixture['carg'][0]['agr'][0]['par'][0]['cand'][0]['nmu'] = '<script>alert(1)</script>';
        Http::fake([
            '*ele-c.json' => Http::response(TseFixture::catalog()),
            '*-u.json' => Http::response($fixture),
        ]);
        app(TseSyncService::class)->sync(2, 'br');
        $this->get('/')->assertOk()->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_exterior_link_and_results_work_in_both_rounds(): void
    {
        config(['tse.enabled' => true]);
        Storage::fake('local');
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), 'ele-c.json')) {
                return Http::response(TseFixture::catalog());
            }
            $round = str_contains($request->url(), '/6257/') ? 1 : 2;

            return Http::response(TseFixture::result([
                'ele' => $round === 1 ? '6257' : '6258',
                't' => (string) $round, 'tpabr' => 'uf', 'cdabr' => 'zz',
            ]));
        });
        foreach ([1, 2] as $round) {
            $this->assertSame('updated', app(TseSyncService::class)->sync($round, 'zz')['status']);
            $this->get('/?round='.$round.'&scope=br')->assertOk()
                ->assertSee('/?round='.$round.'&amp;scope=zz#resultados', false);
            $this->get('/?round='.$round.'&scope=zz')->assertOk()
                ->assertSee('Votos no exterior')
                ->assertSee('CANDIDATO SINTÉTICO DE TESTE')
                ->assertSee('id="world-map"', false);
            $this->getJson('/api/results?round='.$round.'&scope=zz')->assertOk()
                ->assertJsonPath('result.scope', 'zz')
                ->assertJsonPath('result.round', $round)
                ->assertJsonPath('result.data.totals.total_votes', 100);
        }
        Http::assertSentCount(3);
    }

    public function test_exterior_uses_world_map_while_brazil_keeps_state_map(): void
    {
        Http::fake();
        foreach ([1, 2] as $round) {
            $this->get('/?round='.$round.'&scope=zz')->assertOk()
                ->assertSee('id="world-map"', false)
                ->assertSee('Selecionar país ou território')
                ->assertSee('Portugal')
                ->assertSee('Os recortes por país são somas da aplicação')
                ->assertDontSee('Escolha um estado');
            $this->get('/?round='.$round.'&scope=br')->assertOk()
                ->assertSee('Escolha um estado')
                ->assertDontSee('id="world-map"', false);
        }
        $this->getJson('/api/results?round=2&scope=zz')->assertOk()
            ->assertJsonPath('result.scope', 'zz')
            ->assertSee('world-map');
        Http::assertNothingSent();
    }
}
