import os

file_path = "d:\\Project\\market_pro\\app\\Services\\GlobalMarketService.php"

with open(file_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

def write_service(name, line_ranges):
    out_path = f"d:\\Project\\market_pro\\app\\Services\\{name}.php"
    content = "<?php\n\nnamespace App\\Services;\n\nuse Illuminate\\Support\\Facades\\Http;\nuse Illuminate\\Support\\Facades\\Cache;\nuse Illuminate\\Support\\Facades\\Log;\n\nclass " + name + " extends BaseMarketService\n{\n"
    for start, end in line_ranges:
        # line numbers are 1-based
        for i in range(start - 1, end):
            # Replace some private calls with $this-> calls if they were private methods? 
            # Well, they are now protected so $this->method() still works.
            # Replace private fn with public/protected if needed, wait, the original ones are just methods.
            line = lines[i]
            # If there's an internal method like getForexFromBinanceCross we can make it protected or keep it as is.
            content += line
    content += "}\n"
    with open(out_path, "w", encoding="utf-8") as f:
        f.write(content)

# StockMarketService
write_service("StockMarketService", [
    (213, 561),
    (998, 1190),
    (1228, 1356),
])

# ForexMarketService
write_service("ForexMarketService", [
    (565, 699),
    (1194, 1224),
])

# BondMarketService
write_service("BondMarketService", [
    (703, 778),
])

# CommodityMarketService
write_service("CommodityMarketService", [
    (782, 936),
    (1414, 1456),
])

# CryptoMarketService
write_service("CryptoMarketService", [
    (942, 995),
    (1358, 1412),
])

# NewsMarketService
write_service("NewsMarketService", [
    (1460, 1798),
])

# BaseMarketService
base_code = """<?php

namespace App\Services;

use Illuminate\\Support\\Facades\\Http;
use Illuminate\\Support\\Facades\\Cache;
use Illuminate\\Support\\Facades\\Log;

abstract class BaseMarketService
{
    protected string $finnhubBase      = 'https://finnhub.io/api/v1';
    protected string $exchangeRateBase  = 'https://open.er-api.com/v6';
    protected string $fredBase          = 'https://fred.stlouisfed.org/graph/fredgraph.csv';
    protected string $okxBase           = 'https://www.okx.com/api/v5';
    protected string $binanceBase       = 'https://data-api.binance.vision';

    public array $majorStocks = [
        'AAPL', 'MSFT', 'GOOGL', 'AMZN', 'NVDA',
        'META', 'TSLA', 'BRK.B', 'JPM', 'V',
        'TSM', 'ASML', 'SAP', 'BABA', 'NVO',
    ];

    public array $forexPairs = [
        'EUR' => 'EUR/USD',
        'GBP' => 'GBP/USD',
        'JPY' => 'JPY/USD',
        'AUD' => 'AUD/USD',
        'CAD' => 'CAD/USD',
        'CHF' => 'CHF/USD',
        'CNY' => 'CNY/USD',
        'HKD' => 'HKD/USD',
        'SGD' => 'SGD/USD',
        'IDR' => 'IDR/USD',
    ];
"""
for i in range(52 - 1, 209):
    line = lines[i]
    if "private function" in line:
        line = line.replace("private function", "protected function")
    base_code += line

base_code += "}\n"

with open("d:\\Project\\market_pro\\app\\Services\\BaseMarketService.php", "w", encoding="utf-8") as f:
    f.write(base_code)

print("Split completed successfully.")
