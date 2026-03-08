<?php
// Verify API Improvements

require_once __DIR__ . '/../vendor/autoload.php';

echo "Bootstrapping Laravel...\n";
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\StockMarketService;
use Illuminate\Support\Facades\Cache;

$service = app(StockMarketService::class);

echo "1. Testing getAnalystEstimates (Finnhub + Yahoo Fallback)...\n";
$symbol = 'AAPL';
Cache::forget("analyst_estimates_{$symbol}_v4"); // Force fresh fetch
$estimates = $service->getAnalystEstimates($symbol);

echo "Result for $symbol:\n";
echo "Has Recommendation: " . ($estimates['has_recommendation'] ? 'YES' : 'NO') . "\n";
echo "Target Mean Price: " . ($estimates['target_price']['mean'] ?? 'NULL') . "\n";
if (empty($estimates['target_price']['mean'])) {
    echo "DEBUG: Scrape likely failed or Finnhub returned no PT.\n";
}

echo "\n2. Testing getPeerComparison (Sequential + Delay)...\n";
$symbols = ['AAPL', 'MSFT'];
// Force fresh fetch for valuations
foreach ($symbols as $s) {
    Cache::forget("equity_valuation_{$s}_v3");
}

$start = microtime(true);
$peers = $service->getPeerComparison($symbols);
$end = microtime(true);

echo "Time taken for " . count($symbols) . " symbols: " . round($end - $start, 2) . "s\n";
echo "Should be > " . (count($symbols) * 0.25) . "s due to usleep(250000).\n";

foreach ($peers as $p) {
    echo "{$p['symbol']}: Price \${$p['price']}, PE: " . ($p['pe_ratio'] ?? 'N/A') . "\n";
}
