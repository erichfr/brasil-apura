<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionProxyTest extends TestCase
{
    public function test_only_the_configured_gateway_can_supply_https_headers(): void
    {
        $previous = $_ENV['TRUSTED_PROXIES'] ?? null;
        $_ENV['TRUSTED_PROXIES'] = '172.28.0.2';
        try {
            $this->refreshApplication();
            Route::get('/proxy-check', fn (Request $request) => response()->json([
                'secure' => $request->isSecure(), 'url' => route('dashboard'),
            ]));
            $this->withServerVariables(['REMOTE_ADDR' => '172.28.0.2', 'HTTP_HOST' => 'painel.example.org'])
                ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
                ->get('http://painel.example.org/proxy-check')->assertOk()->assertJsonPath('secure', true)
                ->assertJsonPath('url', 'https://painel.example.org');

            $this->withServerVariables(['REMOTE_ADDR' => '172.28.0.99', 'HTTP_HOST' => 'painel.example.org'])
                ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
                ->get('http://painel.example.org/proxy-check')->assertOk()->assertJsonPath('secure', false);
        } finally {
            if ($previous === null) {
                unset($_ENV['TRUSTED_PROXIES']);
            } else {
                $_ENV['TRUSTED_PROXIES'] = $previous;
            }
            TrustProxies::flushState();
        }
    }
}
