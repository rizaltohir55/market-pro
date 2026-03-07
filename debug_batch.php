<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\MarketController;
use Illuminate\Http\Request;
use App\Services\MultiSourceMarketService;
use App\Services\PredictionService;

$controller = new MarketController();
$request = new Request(['symbols' => 'BTCUSDT,ETHUSDT', 'interval' => '15m']);
$market = app(MultiSourceMarketService::class);
$prediction = app(PredictionService::class);

$response = $controller->batchPredictions($request, $market, $prediction);
echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response Data: " . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
