<?php

namespace Tests\Feature\MarketIntegration;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use App\Services\ForexMarketService;

/**
 * Live Integration Tests for Forex / ExchangeRate APIs.
 *
 * Tests that open.er-api.com (ExchangeRate-API) and other Forex
 * data sources used by ForexMarketService return the expected structure.
 *
 * Run with: php artisan test --filter ForexApiTest
 */
#[Group('live-integration')]
class ForexApiTest extends TestCase
{
    private ForexMarketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ForexMarketService::class);
        Cache::forget('forex_rates_v3');
        Cache::forget('forex_full_v2');
    }

    #[Test]
    public function exchange_rate_api_ping_is_reachable(): void
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0 Chrome/120'])
                ->timeout(10)
                ->get('https://open.er-api.com/v6/latest/USD');
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to ExchangeRate-API: {$e->getMessage()}");
        }

        $this->assertTrue(
            $response->successful(),
            "open.er-api.com returned HTTP {$response->status()}. The free Forex API may be down or have changed."
        );

        $data = $response->json();
        $this->assertIsArray($data, 'ExchangeRate-API should return a JSON object.');
        $this->assertArrayHasKey('result', $data, "Response missing 'result' key.");
        $this->assertEquals('success', $data['result'], "'result' should be 'success'.");
    }

    #[Test]
    public function exchange_rate_api_returns_usd_conversion_rates(): void
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0 Chrome/120'])
                ->timeout(10)
                ->get('https://open.er-api.com/v6/latest/USD');
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to ExchangeRate-API: {$e->getMessage()}");
        }

        $this->assertTrue($response->successful());

        $data  = $response->json();
        $rates = $data['rates'] ?? [];

        $this->assertNotEmpty($rates, "ExchangeRate-API 'rates' should not be empty.");

        // These are the currencies our app explicitly requests
        $expectedCurrencies = ['EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF', 'CNY', 'IDR'];
        foreach ($expectedCurrencies as $currency) {
            $this->assertArrayHasKey(
                $currency, $rates,
                "ExchangeRate-API response missing currency '{$currency}'. ForexMarketService will have missing data."
            );
            $this->assertIsFloat(
                (float) $rates[$currency],
                "Exchange rate for '{$currency}' should be a numeric value."
            );
            $this->assertGreaterThan(0, $rates[$currency], "Exchange rate for '{$currency}' must be positive.");
        }
    }

    #[Test]
    public function forex_market_service_get_forex_rates_returns_valid_collection(): void
    {
        try {
            $result = $this->service->getForexRates();
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Forex API: {$e->getMessage()}");
        }

        $this->assertNotEmpty($result, 'ForexMarketService::getForexRates() returned empty. ExchangeRate-API may be down.');
        $this->assertArrayHasKey('rates', $result, "getForexRates() result missing 'rates' key.");

        $rates = $result['rates'];
        $this->assertNotEmpty($rates, "'rates' collection should not be empty.");

        // Grab the first currency's data
        $rate = array_values($rates)[0];
        $requiredKeys = ['pair', 'rate'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $rate, "Forex rate entry missing key '{$key}'.");
        }

        $this->assertStringContainsString('/', $rate['pair'], "Forex pair should be in 'BASE/QUOTE' format.");
        $this->assertIsNumeric($rate['rate'], "'rate' should be numeric.");
    }

    #[Test]
    public function forex_history_returns_time_series_data(): void
    {
        try {
            $history = $this->service->getForexHistory('EUR', 7);
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Forex API: {$e->getMessage()}");
        }

        $this->assertNotEmpty($history, 'ForexMarketService::getForexHistory() returned empty for EUR.');

        $point = $history[0];
        $requiredKeys = ['date', 'rate'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $point, "Forex history point missing key '{$key}'.");
        }

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/', $point['date'],
            "'date' should be in YYYY-MM-DD format."
        );
        $this->assertIsNumeric($point['rate'], "'rate' should be numeric.");
    }
}
