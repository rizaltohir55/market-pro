<?php
// Verify Binance Timeout Cascade Fix

require_once __DIR__ . '/../vendor/autoload.php';

echo "Bootstrapping Laravel...\n";
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\MultiSourceMarketService;
use Illuminate\Support\Facades\Cache;

$service = app(MultiSourceMarketService::class);

echo "Clearing endpoint cache...\n";
Cache::forget('binance_working_endpoint');

echo "Running parallel pings for Binance endpoints...\n";
$start = microtime(true);
// Use reflection to call private method if needed, or just call a public method that uses it
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('getWorkingBinanceBase');
$method->setAccessible(true);
$base = $method->invoke($service);
$end = microtime(true);

echo "Working Endpoint: $base\n";
echo "Time taken: " . round($end - $start, 2) . "s\n";
echo "The maximum time should be around 2s even if endpoints are slow/down.\n";
