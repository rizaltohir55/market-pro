<?php
// Test Finnhub Price Target API with SSL Bypass

require_once __DIR__ . '/../vendor/autoload.php';

echo "Bootstrapping Laravel...\n";
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = config('services.finnhub.key', env('FINNHUB_API_KEY'));
$symbol = 'AAPL';

echo "Fetching price target for $symbol from Finnhub (SSL Bypass)...\n";
$r = Http::withOptions(['verify' => false])
    ->withHeaders(['X-Finnhub-Token' => $apiKey])
    ->get('https://finnhub.io/api/v1/stock/price-target', [
        'symbol' => $symbol,
    ]);

if ($r->successful()) {
    echo "SUCCESS:\n";
    print_r($r->json());
} else {
    echo "FAILED: " . $r->status() . "\n";
    echo $r->body() . "\n";
}
