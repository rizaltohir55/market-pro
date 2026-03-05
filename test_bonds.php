<?php

use App\Services\BondMarketService;
use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(BondMarketService::class);

echo "Fetching Treasury Yields...\n";
$start = microtime(true);
$yields = $service->getTreasuryYields();
$end = microtime(true);

echo "Time taken: " . round($end - $start, 4) . "s\n";
echo "Result:\n";
print_r($yields);
