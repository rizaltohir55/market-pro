<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\PredictionService;

try {
    $prediction = app(PredictionService::class);
    
    // Mock klines
    $klines = [];
    $start = 1700000000;
    for ($i = 0; $i < 201; $i++) {
        $klines[] = [
            'time' => $start + ($i * 900),
            'open' => 100 + $i,
            'high' => 105 + $i,
            'low' => 95 + $i,
            'close' => 100 + $i,
            'volume' => 1000
        ];
    }

    echo "Testing getScalpingSignal...\n";
    $result = $prediction->getScalpingSignal($klines, 'BTCUSDT', '15m');
    echo "Success! Signal: " . $result['signal'] . "\n";
    echo "Summary: " . $result['summary'] . "\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo "STACK TRACE:\n" . $e->getTraceAsString() . "\n";
}
