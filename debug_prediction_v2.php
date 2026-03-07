<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Services\PredictionService;
use App\Services\TechnicalAnalysisService;
use App\Services\EconomicCalendarService;
use App\Services\MachineLearningService;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$ta = new TechnicalAnalysisService();
$calendar = new EconomicCalendarService();
$ml = new MachineLearningService();
$prediction = new PredictionService($ta, $calendar, $ml);

// Mock Klines (Trending Bullish)
$klines = [];
$basePrice = 50000;
for ($i = 0; $i < 200; $i++) {
    $open = $basePrice + ($i * 10) + rand(-5, 5);
    $close = $open + 15 + rand(-5, 5);
    $high = max($open, $close) + rand(1, 5);
    $low = min($open, $close) - rand(1, 5);
    $klines[] = [
        'open' => $open,
        'close' => $close,
        'high' => $high,
        'low' => $low,
        'volume' => 1000 + rand(0, 500),
        'time' => time() - (200 - $i) * 60,
    ];
}

echo "--- Testing Bullish Scenario ---\n";
$signal = $prediction->getScalpingSignal($klines);
echo "Signal: " . $signal['signal'] . "\n";
echo "Confidence: " . $signal['confidence'] . "%\n";
echo "Indicators Count: " . count($signal['indicators']) . "\n";
foreach ($signal['indicators'] as $ind) {
    echo "- " . $ind['name'] . ": " . $ind['signal'] . " (" . $ind['value'] . ")\n";
}

// Mock Divergence
$klinesDiv = $klines;
// Artificially create a divergence: Price goes up, RSI (oscillator pattern) goes down
// We can't easily "force" RSI without full calculation, but we can check if the detector catches a pattern
$closes = array_column($klines, 'close');
$rsiValues = [80, 78, 75, 72, 70, 68, 65, 62, 60, 58]; // Downward trend
$priceValues = array_slice($closes, -10); // Upward trend from mock loop

echo "\n--- Testing Divergence Detector ---\n";
$div = $ta->detectDivergence($priceValues, $rsiValues);
echo "Divergence Detected: $div\n";

// Mock Candlestick Pattern (Bullish Engulfing)
$klinesPatterns = [
    ['open' => 100, 'close' => 90, 'high' => 105, 'low' => 85, 'volume' => 100], // Bearish small
    ['open' => 85, 'close' => 110, 'high' => 115, 'low' => 80, 'volume' => 150], // Bullish engulfing
];
echo "\n--- Testing Candlestick Patterns ---\n";
$cp = $ta->detectCandlestickPatterns($klinesPatterns);
echo "Patterns: " . implode(', ', $cp) . "\n";

echo "\n--- Verification Finished ---\n";
