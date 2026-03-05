<?php

namespace Tests\Feature\MarketIntegration;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

/**
 * Live Integration Tests for Finnhub API.
 *
 * These tests make REAL HTTP requests to Finnhub endpoints
 * to validate that the response structures our application
 * depends on have not changed.
 *
 * Requires FINNHUB_API_KEY to be set in .env (or will be skipped).
 *
 * Run with: php artisan test --filter FinnhubApiTest
 */
#[Group('live-integration')]
class FinnhubApiTest extends TestCase
{
    protected string $base = 'https://finnhub.io/api/v1';
    protected ?string $apiKey = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKey = config('services.finnhub.key', env('FINNHUB_API_KEY'));

        if (empty($this->apiKey) || $this->apiKey === 'your_finnhub_api_key_here') {
            $this->markTestSkipped('FINNHUB_API_KEY is not configured. Skipping live Finnhub tests.');
        }
    }

    private function finnhubGet(string $path, array $params = [])
    {
        try {
            return Http::withOptions(['verify' => false])
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 Chrome/120',
                    'X-Finnhub-Token' => $this->apiKey,
                ])
                ->timeout(10)
                ->get($this->base . $path, $params);
        } catch (ConnectionException $e) {
            $this->markTestSkipped("Cannot connect to Finnhub: {$e->getMessage()}");
        }
    }

    #[Test]
    public function finnhub_market_news_endpoint_returns_articles(): void
    {
        $response = $this->finnhubGet('/news', ['category' => 'general']);

        if ($response->status() === 429) {
            $this->markTestSkipped('Finnhub rate limit reached. Skipping test.');
        }

        $this->assertTrue($response->successful(), "Finnhub /news returned HTTP {$response->status()}.");

        $data = $response->json();
        $this->assertIsArray($data, 'Finnhub /news should return a JSON array.');
        $this->assertGreaterThan(0, count($data), 'News array should not be empty.');

        // Validate structure of first article — these are the keys our parsing depends on
        $article = $data[0];
        $requiredKeys = ['id', 'headline', 'summary', 'source', 'url', 'image', 'category', 'datetime'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey(
                $key, $article,
                "Finnhub news article is missing key '{$key}'. The API format may have changed."
            );
        }

        $this->assertIsInt($article['id'], "'id' should be an integer.");
        $this->assertIsString($article['headline'], "'headline' should be a string.");
        $this->assertIsInt($article['datetime'], "'datetime' should be a UNIX timestamp integer.");
    }

    #[Test]
    public function finnhub_company_news_endpoint_returns_articles(): void
    {
        $from = date('Y-m-d', strtotime('-7 days'));
        $to   = date('Y-m-d');

        $response = $this->finnhubGet('/company-news', [
            'symbol' => 'AAPL',
            'from'   => $from,
            'to'     => $to,
        ]);

        if ($response->status() === 429) {
            $this->markTestSkipped('Finnhub rate limit reached. Skipping test.');
        }

        $this->assertTrue($response->successful(), "Finnhub /company-news returned HTTP {$response->status()}.");

        $data = $response->json();
        $this->assertIsArray($data, 'Finnhub /company-news should return a JSON array.');

        if (count($data) > 0) {
            $article = $data[0];
            $requiredKeys = ['id', 'headline', 'url', 'datetime'];
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $article, "Company news missing key '{$key}'.");
            }
        }
    }

    #[Test]
    public function finnhub_stock_search_returns_result_array(): void
    {
        $response = $this->finnhubGet('/search', ['q' => 'AAPL']);

        if ($response->status() === 429) {
            $this->markTestSkipped('Finnhub rate limit reached. Skipping test.');
        }

        $this->assertTrue($response->successful(), "Finnhub /search returned HTTP {$response->status()}.");

        $data = $response->json();
        $this->assertIsArray($data, 'Search response should be a JSON object.');
        $this->assertArrayHasKey('result', $data, "Search response missing 'result' key.");
        $this->assertIsArray($data['result'], "'result' should be an array.");

        if (!empty($data['result'])) {
            $item = $data['result'][0];
            $this->assertArrayHasKey('symbol', $item, "Search result item missing 'symbol'.");
            $this->assertArrayHasKey('description', $item, "Search result item missing 'description'.");
        }
    }

    #[Test]
    public function finnhub_stock_metric_returns_valuation_data(): void
    {
        $response = $this->finnhubGet('/stock/metric', ['symbol' => 'AAPL', 'metric' => 'all']);

        if ($response->status() === 429) {
            $this->markTestSkipped('Finnhub rate limit reached. Skipping test.');
        }

        $this->assertTrue($response->successful(), "Finnhub /stock/metric returned HTTP {$response->status()}.");

        $data = $response->json();
        $this->assertIsArray($data, 'Stock metric response should be a JSON object.');
        $this->assertArrayHasKey('metric', $data, "Stock metric response missing 'metric' key — API format may have changed.");

        $metric = $data['metric'];
        $this->assertIsArray($metric, "'metric' should be an object/array.");

        // These are the specific keys our StockMarketService::getEquityValuation() uses
        $usedKeys = ['peTTM', 'pbAnnual', 'psTTM', 'roeTTM', 'roaTTM', 'grossMarginTTM'];
        foreach ($usedKeys as $key) {
            $this->assertArrayHasKey(
                $key, $metric,
                "Finnhub metric missing key '{$key}'. StockMarketService::getEquityValuation() will break."
            );
        }
    }

    #[Test]
    public function finnhub_recommendation_returns_analyst_data(): void
    {
        $response = $this->finnhubGet('/stock/recommendation', ['symbol' => 'AAPL']);

        if ($response->status() === 429) {
            $this->markTestSkipped('Finnhub rate limit reached. Skipping test.');
        }

        $this->assertTrue($response->successful(), "Finnhub /stock/recommendation returned HTTP {$response->status()}.");

        $data = $response->json();
        $this->assertIsArray($data, 'Recommendation response should be a JSON array.');

        if (!empty($data)) {
            $rec = $data[0];
            $requiredKeys = ['strongBuy', 'buy', 'hold', 'sell', 'strongSell', 'period'];
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey(
                    $key, $rec,
                    "Analyst recommendation missing key '{$key}'. StockMarketService::getAnalystEstimates() will break."
                );
            }
        }
    }
}
