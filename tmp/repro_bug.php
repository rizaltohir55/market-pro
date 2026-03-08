<?php
// Mocking necessary classes and data to test PredictionService

require_once __DIR__ . '/../vendor/autoload.php';

// Simple mock for klines
$klines = [];
for ($i = 0; $i < 300; $i++) {
    $klines[] = [
        'open' => 100 + $i,
        'high' => 105 + $i,
        'low' => 95 + $i,
        'close' => 100 + $i,
        'volume' => 1000,
        'timestamp' => time() - (300 - $i) * 60
    ];
}

// We need a real Laravel app context or at least mock the dependencies
// Since this is a standalone script, it's better to use Artisan to run a test or use a Tinker-like approach

echo "Bootstrapping Laravel...\n";
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\PredictionService;

echo "Instantiating PredictionService...\n";
$service = app(PredictionService::class);

echo "Running getScalpingSignal...\n";
try {
    $result = $service->getScalpingSignal($klines, 'BTCUSDT', '15m');
    echo "Result: " . $result['signal'] . " (Confidence: " . $result['confidence'] . ")\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
