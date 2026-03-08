<?php
// Test Finnhub Price Target API

require_once __DIR__ . '/../vendor/autoload.php';

echo "Bootstrapping Laravel...\n";
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = env('FINNHUB_API_KEY');
$symbol = 'AAPL';

echo "Fetching price target for $symbol from Finnhub...\n";
$r = Http::get('https://finnhub.io/api/v1/stock/price-target', [
    'symbol' => $symbol,
    'token'  => $apiKey
]);

if ($r->successful()) {
    echo "SUCCESS:\n";
    print_r($r->json());
} else {
    echo "FAILED: " . $r->status() . "\n";
    echo $r->body() . "\n";
}
