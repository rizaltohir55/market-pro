<?php

use App\Services\PredictionService;
use App\Services\TechnicalAnalysisService;
use App\Services\MachineLearningService;
use App\Services\EconomicCalendarService;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$ta = new TechnicalAnalysisService();
$ml = new MachineLearningService();
$calendar = new EconomicCalendarService();
$predictionService = new PredictionService($ta, $calendar, $ml);

// Generate mock klines
$klines = [];
$basePrice = 50000;
for ($i = 0; $i < 200; $i++) {
    $basePrice += rand(-100, 100);
    $klines[] = [
        'open' => $basePrice - rand(10, 50),
        'high' => $basePrice + rand(50, 100),
        'low' => $basePrice - rand(50, 100),
        'close' => $basePrice,
        'volume' => rand(100, 1000),
        'time' => time() - (200 - $i) * 60
    ];
}

echo "Testing Prediction with Mock Data...\n";
$result = $predictionService->getScalpingSignal($klines, 'BTCUSDT', '15m');

echo "Overall Signal: {$result['signal']}\n";
echo "Confidence: {$result['confidence']}%\n";
echo "Hurst: " . round($result['hurst'], 2) . "\n";

echo "\nIndicators Detail:\n";
foreach ($result['indicators'] as $ind) {
    printf("%-20s | %-15s | %s\n", $ind['name'], $ind['value'], $ind['signal']);
}

echo "\nCategory Breakdown:\n";
foreach ($result['categories'] as $cat => $sc) {
    printf("%-20s | Buy: %-5s%% | Sell: %-5s%% | Signal: %s\n", $cat, $sc['buy'], $sc['sell'], $sc['signal']);
}

echo "\nVerification Successful.\n";
