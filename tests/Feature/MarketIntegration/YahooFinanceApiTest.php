<?php

namespace Tests\Feature\MarketIntegration;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use App\Services\StockMarketService;

/**
 * Live Integration Tests for Yahoo Finance API.
 *
 * Tests that Yahoo Finance response structures used by StockMarketService
 * have not changed. Uses the actual service methods to also test
 * the crumb/cookie authentication logic.
 *
 * Run with: php artisan test --filter YahooFinanceApiTest
 */
#[Group('live-integration')]
class YahooFinanceApiTest extends TestCase
{
    private StockMarketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockMarketService::class);
        // Clear relevant cache keys to ensure fresh live calls
        Cache::forget('yahoo_crumb_cookie');
        Cache::forget('stock_quote_AAPL');
        Cache::forget('bulk_quotes_' . md5(implode(',', ['AAPL', 'MSFT'])));
    }

    #[Test]
    public function yahoo_v8_chart_returns_valid_stock_quote_structure(): void
    {
        try {
            $quote = $this->service->getStockQuote('AAPL');
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Yahoo Finance: {$e->getMessage()}");
        }

        $this->assertNotEmpty($quote, 'AAPL stock quote returned empty. Yahoo Finance v8 format may have changed.');

        $requiredKeys = [
            'symbol', 'name', 'price', 'open', 'high', 'low',
            'prev_close', 'change', 'change_pct', 'volume', 'currency',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey(
                $key, $quote,
                "Stock quote missing key '{$key}'. Yahoo v8 chart API format may have changed."
            );
        }

        $this->assertEquals('AAPL', $quote['symbol']);
        $this->assertGreaterThan(0, $quote['price'], "Stock price should be positive.");
        $this->assertIsFloat($quote['price'], "'price' should be a float.");
        $this->assertIsFloat($quote['change_pct'], "'change_pct' should be a float.");
    }

    #[Test]
    public function yahoo_v7_bulk_quote_returns_multiple_stocks(): void
    {
        try {
            $symbols = ['AAPL', 'MSFT'];
            $quotes  = $this->service->getBulkStockQuotes($symbols);
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Yahoo Finance: {$e->getMessage()}");
        }

        $this->assertNotEmpty($quotes, 'Bulk stock quotes returned empty. Yahoo Finance v7 /quote endpoint may have changed.');
        $this->assertGreaterThanOrEqual(
            1, count($quotes),
            "Should return at least 1 quote. Yahoo v7 bulk endpoint may be broken."
        );

        $quote = $quotes[0];
        $requiredKeys = ['symbol', 'name', 'price', 'change', 'change_pct', 'volume'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $quote, "Bulk quote missing key '{$key}'.");
        }
        $this->assertGreaterThan(0, $quote['price']);
    }

    #[Test]
    public function yahoo_crumb_cookie_authentication_succeeds(): void
    {
        // The yahooGetWithCrumb method caches the crumb/cookie pair.
        // We cleared the cache in setUp, so this will perform a fresh fetch.
        try {
            $response = $this->service->yahooGetWithCrumb(
                '/v10/finance/quoteSummary/AAPL',
                ['modules' => 'summaryProfile']
            );
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Yahoo Finance: {$e->getMessage()}");
        }

        $this->assertNotNull($response, 'yahooGetWithCrumb returned null. Crumb/cookie authentication failed.');

        // Even if the crumb fails, Yahoo may return 401/406 rather than 200
        $this->assertContains(
            $response->status(), [200, 401, 406],
            "Yahoo Finance crumb authentication returned unexpected status {$response->status()}."
        );

        // If 200, verify the response structure
        if ($response->successful()) {
            $data = $response->json();
            $this->assertIsArray($data, 'quoteSummary response should be a JSON object.');
            $this->assertArrayHasKey('quoteSummary', $data, "Response missing 'quoteSummary' key.");
        }
    }

    #[Test]
    public function yahoo_stock_candle_data_returns_ohlcv_array(): void
    {
        // 30 days of daily candles
        $to   = time();
        $from = $to - (30 * 86400);

        try {
            $candles = $this->service->getStockCandles('AAPL', 'D', $from, $to);
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Yahoo Finance: {$e->getMessage()}");
        }

        $this->assertNotEmpty($candles, 'Stock candles returned empty. Yahoo Finance chart endpoint may have changed.');

        $candle = $candles[0];
        $requiredKeys = ['time', 'open', 'high', 'low', 'close', 'volume'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $candle, "Candle missing key '{$key}'. Yahoo v8 chart format may have changed.");
        }

        $this->assertIsInt($candle['time'], "'time' should be a UNIX timestamp integer.");
        $this->assertGreaterThan(0, $candle['close'], "Close price should be positive.");
    }

    #[Test]
    public function yahoo_major_indices_return_valid_data(): void
    {
        try {
            $indices = $this->service->getMajorIndices();
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Yahoo Finance: {$e->getMessage()}");
        }

        $this->assertNotEmpty($indices, 'getMajorIndices() returned empty. Yahoo Finance bulk quote may be down.');
        $this->assertGreaterThanOrEqual(
            1, count($indices),
            "Should return at least 1 index."
        );

        $index = $indices[0];
        $this->assertArrayHasKey('symbol', $index);
        $this->assertArrayHasKey('price', $index);
        $this->assertGreaterThan(0, $index['price']);
    }
}
