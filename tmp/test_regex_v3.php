<?php
// Test Regex against the downloaded HTML - Final attempt

$html = file_get_contents('d:/Project/market_pro/tmp/yahoo_aapl_v2.html');

if (substr($html, 0, 2) === "\xFF\xFE") {
    echo "Detected UTF-16LE encoding. Converting...\n";
    $html = mb_convert_encoding($html, 'UTF-8', 'UTF-16LE');
}

echo "HTML Length: " . strlen($html) . "\n";
echo "First 500 chars: " . substr($html, 0, 500) . "\n";

// Search for targetMeanPrice specifically anywhere
if (stripos($html, 'targetMeanPrice') !== false) {
    echo "Found 'targetMeanPrice' using stripos!\n";
    $pos = stripos($html, 'targetMeanPrice');
    echo "Context: ..." . substr($html, $pos - 20, 100) . "...\n";
} else {
    echo "Literal 'targetMeanPrice' NOT found.\n";
}

// Try one more search for ANY numeric value that looks like a target price (e.g. 240-270 for AAPL)
if (preg_match_all('/"[\w]+":\s*\{\s*"raw":\s*([2-3][\d]{2}\.[\d]+)/', $html, $m)) {
   echo "Found likely target price candidates:\n";
   print_r(array_unique($m[0]));
}
