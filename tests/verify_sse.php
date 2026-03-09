<?php

/**
 * Test script to verify SSE streaming in StreamController.php
 * This script will attempt to read from the SSE endpoint and verify multiple events are sent in one request.
 */

$url = 'http://127.0.0.1:8000/api/market/stream?page=dashboard';
echo "Connecting to $url...\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
    static $eventCount = 0;
    echo "Received data chunk (" . strlen($data) . " bytes):\n";
    echo $data . "\n";
    
    if (strpos($data, 'data:') !== false) {
        $eventCount++;
    }
    
    // Stop after 3 events to verify it's streaming
    if ($eventCount >= 3) {
        echo "\nSuccessfully received 3 events in a single stream. Test PASSED.\n";
        return 0; // Stop curl
    }
    
    return strlen($data);
});

curl_exec($ch);
curl_close($ch);
