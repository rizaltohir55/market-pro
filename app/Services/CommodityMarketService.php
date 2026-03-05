<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CommodityMarketService extends BaseMarketService
{
    protected StockMarketService $stockMarket;

    public function __construct(StockMarketService $stockMarket)
    {
        $this->stockMarket = $stockMarket;
    }

    /**
     * Get real commodity prices from real APIs (no dummy data).
     *
     * XAU (Gold)     → Binance PAXGUSDT (PAX Gold token, 1 PAXG = 1 troy oz gold)
     * XAG (Silver)   → CoinGecko 'silver' coin ID (fallback from OKX if unreachable)
     * WTI (Oil)      → CoinGecko 'crude-oil' or 'petroleum' via simple_price
     * NGAS           → CoinGecko 'natural-gas' via simple_price
     * ETH            → Binance ETHUSDT (for reference)
     * BTC            → Binance BTCUSDT (for reference)
     *
     * Primary: OKX public API (no key). Fallback: CoinGecko or Binance.
     *
     * @return array of commodity objects
     */
    public function getCommodityPrices(): array
    {
        $cacheKey = 'commodity_prices';
        return Cache::remember($cacheKey, 60, function () {
            return $this->fetchCommodityPricesCore();
        }) ?? [];
    }

    /**
     * Internal: Fetch core commodity prices without caching.
     */
    protected function fetchCommodityPricesCore(): array
    {
        $commodities = [];

        // ── Gold (XAU) via Binance PAXGUSDT ─────────────────────────────
        try {
            $r = $this->binanceGet('/api/v3/ticker/24hr', ['symbol' => 'PAXGUSDT']);
            if ($r && $r->successful()) {
                $d = $r->json();
                if (!empty($d['lastPrice'])) {
                    $commodities[] = [
                        'symbol'     => 'XAU',
                        'name'       => 'Gold (Troy Oz)',
                        'price'      => (float) $d['lastPrice'],
                        'change_pct' => (float) $d['priceChangePercent'],
                        'high_24h'   => (float) $d['highPrice'],
                        'low_24h'    => (float) $d['lowPrice'],
                        'volume'     => (float) $d['volume'],
                        'unit'       => 'oz/USD',
                        'source'     => 'binance_paxgusdt',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('CommodityMarketService: Gold (PAXGUSDT) exception — ' . $e->getMessage());
        }

        // ── Silver (XAG) via OKX → Fallback: CoinGecko silver ────────────
        $silverFetched = false;
        try {
            $r = $this->okxGet('/market/ticker', ['instId' => 'XAG-USD']);
            if ($r && $r->successful()) {
                $d = $r->json();
                $data = $d['data'][0] ?? null;
                if ($data && !empty($data['last'])) {
                    $last = (float) $data['last'];
                    $open = (float) ($data['open24h'] ?? $last);
                    $chg  = $open > 0 ? (($last - $open) / $open) * 100 : 0;
                    $commodities[] = [
                        'symbol'     => 'XAG',
                        'name'       => 'Silver (Troy Oz)',
                        'price'      => $last,
                        'change_pct' => round($chg, 4),
                        'high_24h'   => (float) ($data['high24h'] ?? 0),
                        'low_24h'    => (float) ($data['low24h'] ?? 0),
                        'volume'     => (float) ($data['volCcy24h'] ?? 0),
                        'unit'       => 'oz/USD',
                        'source'     => 'okx',
                    ];
                    $silverFetched = true;
                }
            }
        } catch (\Exception $e) {
            Log::warning('CommodityMarketService: Silver (OKX) exception — ' . $e->getMessage());
        }
        // Fallback: CoinGecko silver
        if (!$silverFetched) {
            try {
                $r = Http::withOptions(['verify' => storage_path('cacert.pem')])
                    ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0 Chrome/120'])
                    ->timeout(10)
                    ->get('https://api.coingecko.com/api/v3/simple/price', [
                        'ids'              => 'silver,tether-gold',
                        'vs_currencies'    => 'usd',
                        'include_24hr_change' => 'true',
                    ]);
                if ($r && $r->successful()) {
                    $d = $r->json();
                    if (!empty($d['silver']['usd'])) {
                        $commodities[] = [
                            'symbol'     => 'XAG',
                            'name'       => 'Silver (Troy Oz)',
                            'price'      => (float) $d['silver']['usd'],
                            'change_pct' => (float) ($d['silver']['usd_24h_change'] ?? 0),
                            'high_24h'   => 0,
                            'low_24h'    => 0,
                            'volume'     => 0,
                            'unit'       => 'oz/USD',
                            'source'     => 'coingecko',
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('CommodityMarketService: Silver (CoinGecko) exception — ' . $e->getMessage());
            }
        }

        // ── WTI Crude Oil via Yahoo ──────────────────────────────────────────
        $yhTargets = [
            'CL=F' => ['symbol' => 'WTI', 'name' => 'WTI Crude Oil', 'unit' => 'barrel/USD'],
            'NG=F' => ['symbol' => 'NGAS', 'name' => 'Natural Gas', 'unit' => 'MMBtu/USD']
        ];
        $quotes = $this->stockMarket->getBulkStockQuotes(array_keys($yhTargets));
        foreach ($quotes as $q) {
            $sym = $q['symbol'];
            if (isset($yhTargets[$sym])) {
                $info = $yhTargets[$sym];
                $commodities[] = [
                    'symbol'     => $info['symbol'],
                    'name'       => $info['name'],
                    'price'      => $q['price'],
                    'change_pct' => $q['change_pct'],
                    'high_24h'   => $q['high'],
                    'low_24h'    => $q['low'],
                    'volume'     => $q['volume'],
                    'unit'       => $info['unit'],
                    'source'     => 'yahoo_finance',
                ];
            }
        }

        // ── Bitcoin (BTC) via Binance ─────────────────────────────────────
        try {
            $r = $this->binanceGet('/api/v3/ticker/24hr', ['symbol' => 'BTCUSDT']);
            if ($r && $r->successful()) {
                $d = $r->json();
                if (!empty($d['lastPrice'])) {
                    $commodities[] = [
                        'symbol'     => 'BTC',
                        'name'       => 'Bitcoin',
                        'price'      => (float) $d['lastPrice'],
                        'change_pct' => (float) $d['priceChangePercent'],
                        'high_24h'   => (float) $d['highPrice'],
                        'low_24h'    => (float) $d['lowPrice'],
                        'volume'     => (float) $d['quoteVolume'],
                        'unit'       => 'BTC/USD',
                        'source'     => 'binance',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('CommodityMarketService: BTC (Binance) exception — ' . $e->getMessage());
        }

        return $commodities;
    }

    public function getExtendedCommodityPrices(): array
    {
        $cacheKey = 'commodity_prices_extended';
        return Cache::remember($cacheKey, 60, function () {
            $commodities = $this->fetchCommodityPricesCore();
            
            $yhTargets = [
                'PL=F' => ['symbol' => 'PLAT', 'name' => 'Platinum', 'unit' => 'troy oz/USD'],
                'PA=F' => ['symbol' => 'PALL', 'name' => 'Palladium', 'unit' => 'troy oz/USD'],
                'ZW=F' => ['symbol' => 'WHEAT', 'name' => 'Wheat', 'unit' => 'USd/bu'],
                'ZC=F' => ['symbol' => 'CORN', 'name' => 'Corn', 'unit' => 'USd/bu'],
                'ZS=F' => ['symbol' => 'SOY', 'name' => 'Soybeans', 'unit' => 'USd/bu'],
                'KC=F' => ['symbol' => 'COFFEE', 'name' => 'Coffee', 'unit' => 'USd/lb'],
                'SB=F' => ['symbol' => 'SUGAR', 'name' => 'Sugar', 'unit' => 'USd/lb'],
                'BZ=F' => ['symbol' => 'BRENT', 'name' => 'Brent Crude Oil', 'unit' => 'barrel/USD'],
                'HG=F' => ['symbol' => 'COPPER', 'name' => 'Copper', 'unit' => 'USD'],
            ];
            $yhSymbols = array_keys($yhTargets);
            $quotes = $this->stockMarket->getBulkStockQuotes($yhSymbols);
            
            foreach ($quotes as $q) {
                $sym = $q['symbol'];
                if (isset($yhTargets[$sym])) {
                    $info = $yhTargets[$sym];
                    $commodities[] = [
                        'symbol'     => $info['symbol'],
                        'name'       => $info['name'],
                        'price'      => $q['price'],
                        'change_pct' => $q['change_pct'],
                        'high_24h'   => $q['high'],
                        'low_24h'    => $q['low'],
                        'volume'     => $q['volume'],
                        'unit'       => $info['unit'],
                        'source'     => 'yahoo_finance',
                    ];
                }
            }

            return $commodities;
        }) ?? [];
    }
}
