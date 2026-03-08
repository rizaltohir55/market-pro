<?php
// Test Regex against the downloaded HTML

$html = file_get_contents('d:/Project/market_pro/tmp/yahoo_aapl_v2.html');

// Try converting from UTF-16LE to UTF-8 if it starts with BOM or looks like it
if (substr($html, 0, 2) === "\xFF\xFE") {
    echo "Detected UTF-16LE encoding. Converting...\n";
    $html = mb_convert_encoding($html, 'UTF-8', 'UTF-16LE');
}

echo "HTML Length: " . strlen($html) . "\n";

$regexes = [
    '/"targetMeanPrice":\s*\{\s*"raw":\s*([\d\.]+)/i',
    '/targetPrice.*?data.*?value.*?([\d\.]+)/i',
    '/targetPrice.*?>([\d\.]+)</i',
    '/targetMeanPrice.*?(?:raw|fmt)".*?([\d\.]+)/i',
    '/data-testid="targetPrice".*?>([^<]+)</i'
];

foreach ($regexes as $r) {
    if (preg_match($r, $html, $m)) {
        echo "MATCH FOUND with $r: " . $m[1] . "\n";
    } else {
        echo "NO MATCH with $r\n";
    }
}

// Search for anything near price
if (preg_match_all('/.{0,100}price.{0,100}/i', $html, $m)) {
    echo "Found " . count($m[0]) . " matches for 'price'. Examples:\n";
    foreach (array_slice($m[0], 0, 10) as $sample) {
        echo "- " . trim($sample) . "\n";
    }
}
