<?php
// Benchmark Batch Prediction Performance

require_once __DIR__ . '/../vendor/autoload.php';

echo "Bootstrapping Laravel...\n";
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\PredictionService;
use App\Services\MultiSourceMarketService;

$prediction = app(PredictionService::class);
$market = app(MultiSourceMarketService::class);

$symbols = ['BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'SOLUSDT', 'ADAUSDT'];
$interval = '15m';

echo "Preparing 5 symbols for batch...\n";
$batchKlines = [];
foreach ($symbols as $symbol) {
    $batchKlines[$symbol] = [
        'klines' => $market->getKlines($symbol, $interval, 200)
    ];
}

echo "Running BATCH prediction...\n";
$start = microtime(true);
$results = $prediction->getBatchSignals($batchKlines, $interval);
$end = microtime(true);

echo "Batch Time for " . count($symbols) . " symbols: " . round($end - $start, 2) . "s\n";
echo "Average per symbol: " . round(($end - $start) / count($symbols), 2) . "s\n";

foreach ($results as $s => $r) {
    echo "{$s}: " . ($r['signal'] ?? 'ERROR') . " (Confidence: " . ($r['confidence'] ?? 0) . ")\n";
}
