<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\MultiSourceMarketService;
use App\Services\PredictionService;

echo "Starting ML Test directly via CLI...\n";
$start = microtime(true);

$market = app(MultiSourceMarketService::class);
$prediction = app(PredictionService::class);

echo "Fetching 500 klines...\n";
$klines = $market->getKlines('BTCUSDT', '15m', 500);
echo "Klines fetched. Count: " . count($klines) . "\n";

echo "Running PredictionService->getScalpingSignal...\n";
$signal = $prediction->getScalpingSignal($klines, 'BTCUSDT', '15m');
$time = microtime(true) - $start;

echo "\nResult:\n";
echo "Signal: " . ($signal['signal'] ?? 'N/A') . "\n";
echo "R2: " . ($signal['ml_forecast']['linear_regression']['r_squared'] ?? 'N/A') . "\n";
if (isset($signal['ml_forecast']['monte_carlo'])) {
   echo "MC Median: " . $signal['ml_forecast']['monte_carlo']['median'] . "\n";
}
echo "Time Taken: " . number_format($time, 2) . " seconds\n";
