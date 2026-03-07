<?php
$url = 'http://localhost:8000/api/market/stream?page=trading&symbol=BTCUSDT&interval=15m';

echo "Hitting $url (waiting for first chunk) ... ";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
    echo "\nRECEIVED DATA: " . substr($data, 0, 200) . "...\n";
    return 0; // Stop after first chunk
});
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\nHTTP CODE: $httpCode\n";
