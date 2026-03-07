<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\EconomicCalendarService;

echo "Calling EconomicCalendarService...\n";
$service = app(EconomicCalendarService::class);
$data = $service->getEconomicCalendar();
echo "Count: " . count($data) . "\n";
if (count($data) > 0) {
    echo "Sample: " . json_encode($data[0], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No data returned.\n";
}
