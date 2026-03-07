<?php

// Real Data Verification Script
// This script uses the actual application services and real data from live APIs

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Services\PredictionService;
use App\Services\TechnicalAnalysisService;
use App\Services\EconomicCalendarService;
use App\Services\MultiSourceMarketService;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$ta = app(TechnicalAnalysisService::class);
$calendar = app(EconomicCalendarService::class);
$prediction = app(PredictionService::class);
$market = app(MultiSourceMarketService::class);

$symbol = 'BTCUSDT';
echo "--- Fetching Real Data for $symbol ---\n";

try {
    // 1. Fetch Real Klines
    $klines = $market->getKlines($symbol, '15m', 200);
    if (empty($klines)) {
        throw new Exception("Could not fetch klines from live API.");
    }
    echo "Successfully fetched " . count($klines) . " real candles.\n";

    // 2. Fetch Real Economic Calendar
    $events = $calendar->getEconomicCalendar();
    echo "Successfully fetched " . count($events) . " real economic events.\n";

    // 3. Run Prediction Engine
    echo "\n--- Running Prediction Engine with Real Data ---\n";
    $signal = $prediction->getScalpingSignal($klines);

    echo "Result for $symbol (15m):\n";
    echo "Signal: " . $signal['signal'] . "\n";
    echo "Confidence: " . $signal['confidence'] . "%\n";
    echo "Category Scores:\n";
    foreach ($signal['categories'] as $cat => $score) {
        echo "- $cat: Buy(" . $score['buy'] . "%) Sell(" . $score['sell'] . "%) -> " . $score['signal'] . "\n";
    }

    echo "\nDetected Indicators:\n";
    foreach ($signal['indicators'] as $ind) {
        echo "- [" . $ind['name'] . "] Signal: " . $ind['signal'] . " Value: " . $ind['value'] . "\n";
    }

    // 4. Verify Technical Methods Directly
    echo "\n--- Verifying Specific Technical Methods ---\n";
    
    $closes = array_column($klines, 'close');
    $rsi = $ta->calculateRSI($closes);
    $div = $ta->detectDivergence($closes, $rsi);
    echo "Current Divergence Status: $div\n";

    $patterns = $ta->detectCandlestickPatterns($klines);
    echo "Real Patterns Detected: " . (empty($patterns) ? "None at current candle" : implode(', ', $patterns)) . "\n";

    $fib = $ta->calculateFibonacciPivots($klines);
    echo "Fibonacci S1 Zone: " . round($fib['s1'], 2) . " | R1 Zone: " . round($fib['r1'], 2) . "\n";

} catch (\Exception $e) {
    echo "Verification Error: " . $e->getMessage() . "\n";
}

echo "\n--- Verification Finished ---\n";
