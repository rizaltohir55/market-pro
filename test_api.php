<?php
$endpoints = [
    'http://localhost:8000/api/market/klines?symbol=BTCUSDT&interval=15m',
    'http://localhost:8000/api/market/ticker?symbol=BTCUSDT',
    'http://localhost:8000/api/market/prediction?symbol=BTCUSDT&interval=15m'
];

foreach ($endpoints as $url) {
    echo "Hitting $url ... ";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP CODE: $httpCode\n";
    if ($httpCode !== 200) {
        echo "RESPONSE: " . substr($response, 0, 500) . "\n";
    } else {
        $json = json_decode($response, true);
        if ($json === null) {
            echo "RESPONSE NOT JSON: " . substr($response, 0, 100) . "\n";
        } else {
            echo "OK (JSON parsed)\n";
        }
    }
    echo "-----------------------------------\n";
}
