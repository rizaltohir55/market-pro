<?php

namespace Tests\Feature\MarketIntegration;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

/**
 * Live Integration Tests for Binance Public REST API.
 *
 * These tests make REAL HTTP requests to Binance endpoints
 * to validate that the response structures our application
 * depends on have not changed.
 *
 * Run with: php artisan test --filter BinanceApiTest
 */
#[Group('live-integration')]
class BinanceApiTest extends TestCase
{
    protected string $base = 'https://data-api.binance.vision/api/v3';

    #[Test]
    public function binance_ping_endpoint_is_reachable(): void
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])
                ->timeout(10)
                ->get("{$this->base}/ping");
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Binance: {$e->getMessage()}");
        }

        $this->assertTrue(
            $response->successful(),
            "Binance /ping returned HTTP {$response->status()}. The endpoint may be blocked or changed."
        );

        // Binance ping returns "{}" on success
        $body = $response->json();
        $this->assertIsArray($body, 'Binance /ping should return a JSON object.');
    }

    #[Test]
    public function binance_klines_returns_expected_ohlcv_format(): void
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])
                ->timeout(10)
                ->get("{$this->base}/klines", [
                    'symbol'   => 'BTCUSDT',
                    'interval' => '15m',
                    'limit'    => 5,
                ]);
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Binance: {$e->getMessage()}");
        }

        $this->assertTrue(
            $response->successful(),
            "Binance /klines returned HTTP {$response->status()}. API may be rate-limited or down."
        );

        $data = $response->json();
        $this->assertIsArray($data, 'Binance /klines should return a JSON array.');
        $this->assertGreaterThan(0, count($data), 'Klines array should not be empty.');

        // Each kline is an array: [openTime, open, high, low, close, volume, ...]
        $kline = $data[0];
        $this->assertIsArray($kline, 'Each kline entry should be an array.');
        $this->assertArrayHasKey(0, $kline, 'kline[0] should be the open timestamp.');
        $this->assertArrayHasKey(1, $kline, 'kline[1] should be the open price.');
        $this->assertArrayHasKey(2, $kline, 'kline[2] should be the high price.');
        $this->assertArrayHasKey(3, $kline, 'kline[3] should be the low price.');
        $this->assertArrayHasKey(4, $kline, 'kline[4] should be the close price.');
        $this->assertArrayHasKey(5, $kline, 'kline[5] should be the volume.');

        // Validate types — timestamps are integers, prices are numeric strings
        $this->assertIsInt($kline[0], 'Open timestamp (kline[0]) should be an integer.');
        $this->assertIsNumeric($kline[4], 'Close price (kline[4]) should be numeric.');
    }

    #[Test]
    public function binance_ticker_24hr_returns_expected_keys(): void
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])
                ->timeout(10)
                ->get("{$this->base}/ticker/24hr", ['symbol' => 'BTCUSDT']);
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Binance: {$e->getMessage()}");
        }

        $this->assertTrue(
            $response->successful(),
            "Binance /ticker/24hr returned HTTP {$response->status()}."
        );

        $data = $response->json();
        $this->assertIsArray($data, 'Ticker response should be a JSON object.');

        // These are the exact keys our app depends on in MultiSourceMarketService
        $requiredKeys = [
            'symbol', 'lastPrice', 'priceChange', 'priceChangePercent',
            'highPrice', 'lowPrice', 'volume', 'quoteVolume',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey(
                $key, $data,
                "Binance ticker response is missing key '{$key}'. The API may have changed."
            );
        }

        $this->assertEquals('BTCUSDT', $data['symbol']);
        $this->assertIsNumeric($data['lastPrice'], "'lastPrice' should be a numeric string.");
    }

    #[Test]
    public function binance_bulk_ticker_returns_array_of_objects(): void
    {
        try {
            // Without a symbol we get all tickers
            $response = Http::withOptions(['verify' => false])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])
                ->timeout(15)
                ->get("{$this->base}/ticker/24hr");
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Binance: {$e->getMessage()}");
        }

        $this->assertTrue($response->successful(), "Binance bulk /ticker/24hr failed.");

        $data = $response->json();
        $this->assertIsArray($data, 'Bulk ticker should return an array.');
        $this->assertGreaterThan(100, count($data), 'Should return more than 100 ticker pairs.');

        // Check the structure of the first ticker
        $first = $data[0];
        $this->assertArrayHasKey('symbol', $first);
        $this->assertArrayHasKey('lastPrice', $first);
        $this->assertArrayHasKey('quoteVolume', $first);
    }
}
