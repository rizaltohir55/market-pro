<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\MachineLearningService;
use Illuminate\Container\Container;

// Boot minimal Laravel environment
$app = new Container();
$app->bind('path.storage', fn() => 'd:/Project/market_pro/storage');
$app->bind('path.base', fn() => 'd:/Project/market_pro');
// Need basic Laravel setup for base_path() and storage_path() helper if used in script
// Here we'll just mock them if needed or use real ones if running via artisan

function base_path($path = '') { return "d:/Project/market_pro/$path"; }
function storage_path($path = '') { return "d:/Project/market_pro/storage/$path"; }

$ml = new MachineLearningService();

// Mock Klines (50 samples)
$klines = [];
for ($i = 0; $i < 50; $i++) {
    $klines[] = [
        'open' => 50000 + mt_rand(-100, 100),
        'high' => 50100 + mt_rand(0, 50),
        'low' => 49900 - mt_rand(0, 50),
        'close' => 50000 + mt_rand(-100, 100),
        'volume' => 10 + mt_rand(0, 5)
    ];
}

$symbols = [];
for ($i = 0; $i < 50; $i++) {
    $symbols[] = "SYMBOL_$i";
}
$batchInput = [];
foreach ($symbols as $s) {
    $batchInput[$s] = $klines;
}

echo "Testing Batch ML Optimization...\n";

// First run (Cold - No Cache)
$start = microtime(true);
$res1 = $ml->predictBatchXGBoost($batchInput, 5);
$end = microtime(true);
echo "Cold Run Time: " . round($end - $start, 2) . "s\n";

// Second run (Warm - Cached Models)
$start = microtime(true);
$res2 = $ml->predictBatchXGBoost($batchInput, 5);
$end = microtime(true);
echo "Warm Run Time: " . round($end - $start, 2) . "s\n";

if (($end - $start) < 2) {
    echo "Optimization SUCCESSFUL! Warm run is fast.\n";
} else {
    echo "Warning: Warm run still taking " . round($end - $start, 2) . "s\n";
}
