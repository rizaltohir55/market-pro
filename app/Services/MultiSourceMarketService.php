<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MultiSourceMarketService
 *
 * Fetches crypto market data from multiple sources with automatic fallback:
 *   1. Binance Public REST API (primary)
 *   2. CoinGecko Public API (fallback — no auth required)
 *
 * All public APIs — zero configuration needed.
 */
class MultiSourceMarketService
{
    /**
     * Ordered list of Binance-compatible endpoints to try.
     * data-api.binance.vision is often NOT blocked by ISPs that block binance.com
     * because it uses a different TLD (.vision vs .com).
     */
    private array $binanceEndpoints = [
        'https://data-api.binance.vision', // PUBLIC data-only endpoint — not .com, often unblocked
        'https://api1.binance.com',
        'https://api2.binance.com',
        'https://api3.binance.com',
        'https://api4.binance.com',
        'https://api.binance.com',
    ];
    private string $coinGeckoUrl = 'https://api.coingecko.com/api/v3';

    // Map from Binance symbol to CoinGecko ID for fallback
    private array $coinGeckoIds = [
        'BTCUSDT'  => 'bitcoin',
        'ETHUSDT'  => 'ethereum',
        'SOLUSDT'  => 'solana',
        'BNBUSDT'  => 'binancecoin',
        'XRPUSDT'  => 'ripple',
        'ADAUSDT'  => 'cardano',
        'DOGEUSDT' => 'dogecoin',
        'XAUUSDT'  => 'tether-gold',
        'AVAXUSDT' => 'avalanche-2',
        'DOTUSDT'  => 'polkadot',
        'MATICUSDT'=> 'matic-network',
        'LINKUSDT' => 'chainlink',
        'LTCUSDT'  => 'litecoin',
        'ATOMUSDT' => 'cosmos',
        'UNIUSDT'  => 'uniswap',
        'TRXUSDT'  => 'tron',
    ];

    /** Cache key to remember the last working Binance endpoint across requests. */
    private function getWorkingBinanceBase(): string
    {
        $cacheKey = 'binance_working_endpoint';
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $cacert = config('services.market.ca_cert');
        $verify = file_exists($cacert) ? $cacert : true;

        try {
            // Ping all endpoints in parallel to find the fastest responder
            // Total maximum delay is now 2 seconds instead of N * timeout
            $responses = Http::pool(function (Pool $pool) use ($verify) {
                $reqs = [];
                foreach ($this->binanceEndpoints as $base) {
                    $reqs[] = $pool->as($base)
                        ->withOptions(['verify' => $verify])
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])
                        ->timeout(2)
                        ->get($base . '/api/v3/ping');
                }
                return $reqs;
            });

            // Iterate in preference order
            foreach ($this->binanceEndpoints as $base) {
                $r = $responses[$base] ?? null;
                if ($r instanceof \Illuminate\Http\Client\Response && $r->successful()) {
                    Log::info('MarketService: Working Binance endpoint found (via pool) and cached: ' . $base);
                    Cache::put($cacheKey, $base, 300);
                    return $base;
                }
            }
        } catch (\Exception $e) {
            Log::warning('MarketService: Binance pool ping exception: ' . $e->getMessage());
        }

        Log::warning('MarketService: No Binance endpoint reachable. Returning fallback without caching.');
        return $this->binanceEndpoints[0]; 
    }

    // ─── HTTP HELPERS ────────────────────────────────────────────────────────

    private function binanceGet(string $path, array $params = [])
    {
        $base = $this->getWorkingBinanceBase();
        return Http::withOptions([
                'verify' => config('services.market.ca_cert'),
            ])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])
            ->timeout(10)
            ->get($base . $path, $params);
    }

    private function coinGeckoGet(string $path, array $params = [])
    {
        return Http::withOptions([
                'verify' => config('services.market.ca_cert'),
            ])
            ->withHeaders([
                'Accept'     => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
            ])
            ->timeout(12)
            ->retry(2, 500)
            ->get($this->coinGeckoUrl . $path, $params);
    }

    // ─── PUBLIC METHODS ──────────────────────────────────────────────────────

    /**
     * Get 24hr ticker for one OR all USDT pairs.
     * Returns array compatible with original BinanceService format.
     */
    public function getTicker24hr(?string $symbol = null): array
    {
        $cacheKey = 'market_ticker_' . ($symbol ?? 'all');

        // Return cached value only if it's non-empty
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) {
            return $cached;
        }

        $result = (function () use ($symbol) {

            // 1. Try Binance FIRST (auto-detects working endpoint via data-api.binance.vision etc.)
            try {
                $params   = $symbol ? ['symbol' => strtoupper($symbol)] : [];
                $response = $this->binanceGet('/api/v3/ticker/24hr', $params);
                if ($response->successful()) {
                    $data = $response->json();
                    if (!empty($data)) {
                        Log::info('MarketService: Binance ticker OK' . ($symbol ? " ($symbol)" : ''));
                        return $data;
                    }
                }
                Log::warning('MarketService: Binance ticker empty/failed, trying CoinGecko.');
            } catch (\Exception $e) {
                Log::warning('MarketService: Binance ticker exception — ' . $e->getMessage());
            }

            // 2. Fallback: CoinGecko
            try {
                $response = $this->coinGeckoGet('/coins/markets', [
                    'vs_currency'             => 'usd',
                    'order'                   => 'market_cap_desc',
                    'per_page'                => 250,
                    'page'                    => 1,
                    'sparkline'               => false,
                    'price_change_percentage' => '24h',
                ]);

                if ($response->successful()) {
                    $coins = $response->json();
                    if (!empty($coins) && is_array($coins) && isset($coins[0]['current_price'])) {
                        Log::info('MarketService: CoinGecko ticker fallback OK (' . count($coins) . ' coins).');
                        $tickers = collect($coins)->map(fn($c) => $this->normalizeCoinGeckoTicker($c))->values()->toArray();

                        if ($symbol) {
                            $symUpper = strtoupper($symbol);
                            $filtered = array_filter($tickers, fn($t) => $t['symbol'] === $symUpper);
                            return array_values($filtered)[0] ?? [];
                        }
                        return $tickers;
                    }
                }
                Log::warning('MarketService: CoinGecko ticker also empty/invalid.');
            } catch (\Exception $e) {
                Log::warning('MarketService: CoinGecko ticker exception — ' . $e->getMessage());
            }

            Log::error('MarketService: All sources failed for ticker.');
            return [];
        })();

        // Only cache non-empty results
        if (!empty($result)) {
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }

    /**
     * Get Kline/candlestick data. Returns original-format array.
     * CoinGecko OHLC is available but lower resolution — used as fallback.
     */
    public function getKlines(string $symbol, string $interval = '1h', int $limit = 200): array
    {
        $cacheKey = "market_klines_{$symbol}_{$interval}_{$limit}";
        
        // Return cached value only if it's non-empty
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) {
            return $cached;
        }

        $result = (function () use ($symbol, $interval, $limit) {
            // 1. Try Binance klines FIRST (auto-detects working endpoint)
            try {
                $response = $this->binanceGet('/api/v3/klines', [
                    'symbol'   => strtoupper($symbol),
                    'interval' => $interval,
                    'limit'    => $limit,
                ]);
                if ($response->successful()) {
                    $data = $response->json();
                    if (!empty($data) && is_array($data) && isset($data[0][0])) {
                        Log::info("MarketService: Binance klines OK for $symbol/$interval (" . count($data) . " candles).");
                        return collect($data)->map(fn($k) => [
                            'time'         => intdiv($k[0], 1000),
                            'open'         => (float) $k[1],
                            'high'         => (float) $k[2],
                            'low'          => (float) $k[3],
                            'close'        => (float) $k[4],
                            'volume'       => (float) $k[5],
                            'close_time'   => intdiv($k[6], 1000),
                            'quote_volume' => (float) $k[7],
                            'trades'       => (int)   $k[8],
                        ])->toArray();
                    }
                }
                Log::warning("MarketService: Binance klines failed for $symbol/$interval, trying CoinGecko OHLC.");
            } catch (\Exception $e) {
                Log::warning('MarketService: Binance klines exception — ' . $e->getMessage());
            }

            // 2. Fallback: CoinGecko OHLC (lower resolution but works without Binance)
            try {
                $geckoId = $this->coinGeckoIds[strtoupper($symbol)] ?? null;
                if ($geckoId) {
                    $days     = $this->intervalToDays($interval);
                    $response = $this->coinGeckoGet("/coins/{$geckoId}/ohlc", [
                        'vs_currency' => 'usd',
                        'days'        => $days,
                    ]);
                    if ($response->successful()) {
                        $ohlc = $response->json();
                        if (!empty($ohlc) && is_array($ohlc) && isset($ohlc[0])) {
                            Log::info("MarketService: CoinGecko OHLC fallback OK for $geckoId ($days days).");
                            return collect($ohlc)->map(fn($k) => [
                                'time'         => intdiv($k[0], 1000),
                                'open'         => (float) $k[1],
                                'high'         => (float) $k[2],
                                'low'          => (float) $k[3],
                                'close'        => (float) $k[4],
                                'volume'       => 0,
                                'close_time'   => intdiv($k[0], 1000) + 3600,
                                'quote_volume' => 0,
                                'trades'       => 0,
                            ])->sortBy('time')->values()->take(-$limit)->values()->toArray();
                        }
                    }
                    Log::warning("MarketService: CoinGecko OHLC also failed for $geckoId.");
                }
            } catch (\Exception $e) {
                Log::warning('MarketService: CoinGecko OHLC exception — ' . $e->getMessage());
            }

            // All real sources failed — return empty. No synthetic/dummy data.
            Log::error("MarketService: ALL real klines sources failed for $symbol/$interval. Returning empty — no dummy data generated.");
            return [];
        })();

        // Only cache non-empty results
        if (!empty($result)) {
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }


    /**
     * Get order book depth.
     */
    public function getDepth(string $symbol, int $limit = 20): array
    {
        $cacheKey = "market_depth_{$symbol}_{$limit}";
        
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) {
            return $cached;
        }

        $result = (function () use ($symbol, $limit) {
            try {
                $response = $this->binanceGet('/api/v3/depth', [
                    'symbol' => strtoupper($symbol),
                    'limit'  => $limit,
                ]);
                if ($response->successful()) {
                    $data = $response->json();
                    if (!empty($data)) return $data;
                }
            } catch (\Exception $e) {
                Log::warning('MarketService: Binance depth exception — ' . $e->getMessage());
            }
            return ['bids' => [], 'asks' => []];
        })();

        // Only cache if it has bids or asks
        if (!empty($result['bids']) || !empty($result['asks'])) {
            Cache::put($cacheKey, $result, 5);
        }

        return $result;
    }

    /**
     * Get recent trades.
     */
    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $cacheKey = "market_trades_{$symbol}";
        
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) {
            return $cached;
        }

        $result = (function () use ($symbol, $limit) {
            try {
                $response = $this->binanceGet('/api/v3/trades', [
                    'symbol' => strtoupper($symbol),
                    'limit'  => $limit,
                ]);
                if ($response->successful()) {
                    return $response->json() ?? [];
                }
            } catch (\Exception $e) {
                Log::warning('MarketService: Binance trades exception — ' . $e->getMessage());
            }
            return [];
        })();

        if (!empty($result)) {
            Cache::put($cacheKey, $result, 5);
        }

        return $result;
    }

    /**
     * Get top USDT pairs by volume.
     */
    public function getTopPairs(int $limit = 50): array
    {
        $tickers = $this->getTicker24hr();
        if (empty($tickers)) return [];

        // Handle both array-of-arrays (Binance all) and single-item (from CoinGecko)
        if (isset($tickers['symbol'])) {
            $tickers = [$tickers];
        }

        // Symbols to skip — stablecoin-vs-stablecoin pairs and bare USDT
        $skipBases = ['USDT', 'USDC', 'BUSD', 'FDUSD', 'TUSD', 'USDP', 'DAI', 'USD1', 'U'];

        return collect($tickers)
            ->filter(function ($t) use ($skipBases) {
                if (!isset($t['symbol']) || !str_ends_with($t['symbol'], 'USDT')) return false;
                if ((float)($t['quoteVolume'] ?? 0) <= 10000) return false;
                // Derive base currency and skip stablecoins
                $base = rtrim(str_replace('USDT', '', $t['symbol']), '/');
                if (empty($base) || in_array($base, $skipBases)) return false;
                return true;
            })
            ->sortByDesc(fn($t) => (float)($t['quoteVolume'] ?? 0))
            ->take($limit)
            ->map(fn($t) => [
                'symbol'      => $t['symbol'],
                'pair'        => rtrim(str_replace('USDT', '', $t['symbol']), '/') . '/USDT',
                'price'       => (float)($t['lastPrice'] ?? 0),
                'change'      => (float)($t['priceChangePercent'] ?? 0),
                'high'        => (float)($t['highPrice'] ?? 0),
                'low'         => (float)($t['lowPrice'] ?? 0),
                'volume'      => (float)($t['volume'] ?? 0),
                'quoteVolume' => (float)($t['quoteVolume'] ?? 0),
                'trades'      => (int)($t['count'] ?? 0),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get top gainers (24h).
     */
    public function getTopGainers(int $limit = 10): array
    {
        $tickers = $this->getTicker24hr();
        if (empty($tickers)) return [];
        if (isset($tickers['symbol'])) $tickers = [$tickers];

        return collect($tickers)
            ->filter(fn($t) => isset($t['symbol']) && str_ends_with($t['symbol'], 'USDT') && (float)($t['quoteVolume'] ?? 0) > 50000)
            ->sortByDesc(fn($t) => (float)($t['priceChangePercent'] ?? 0))
            ->take($limit)
            ->map(fn($t) => [
                'symbol' => $t['symbol'],
                'pair'   => str_replace('USDT', '/USDT', $t['symbol']),
                'price'  => (float)($t['lastPrice'] ?? 0),
                'change' => (float)($t['priceChangePercent'] ?? 0),
            ])
            ->values()->toArray();
    }

    /**
     * Get top losers (24h).
     */
    public function getTopLosers(int $limit = 10): array
    {
        $tickers = $this->getTicker24hr();
        if (empty($tickers)) return [];
        if (isset($tickers['symbol'])) $tickers = [$tickers];

        return collect($tickers)
            ->filter(fn($t) => isset($t['symbol']) && str_ends_with($t['symbol'], 'USDT') && (float)($t['quoteVolume'] ?? 0) > 50000)
            ->sortBy(fn($t) => (float)($t['priceChangePercent'] ?? 0))
            ->take($limit)
            ->map(fn($t) => [
                'symbol' => $t['symbol'],
                'pair'   => str_replace('USDT', '/USDT', $t['symbol']),
                'price'  => (float)($t['lastPrice'] ?? 0),
                'change' => (float)($t['priceChangePercent'] ?? 0),
            ])
            ->values()->toArray();
    }

    /**
     * Get Crypto Fear & Greed Index from alternative.me (Cached 5 mins)
     * Returns 0-100 (0 = Extreme Fear, 100 = Extreme Greed)
     */
    public function getFearAndGreedIndex(): int
    {
        return Cache::remember('macro_fear_greed', 300, function () {
            try {
                $res = Http::timeout(5)->get('https://api.alternative.me/fng/?limit=1');
                if ($res->successful()) {
                    $json = $res->json();
                    if (isset($json['data'][0]['value'])) {
                        return (int) $json['data'][0]['value'];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('FearAndGreed API Error: ' . $e->getMessage());
            }
            return 50; // Neutral fallback
        });
    }

    /**
     * Get Global Long/Short Account Ratio from Binance Futures (Cached 5 mins)
     * > 1 means more longs than shorts.
     */
    public function getGlobalLongShortRatio(string $symbol): float
    {
        $symbol = strtoupper($symbol);
        return Cache::remember("macro_ls_ratio_{$symbol}", 300, function () use ($symbol) {
            try {
                // Using Binance Futures API for Global Long/Short Ratio
                $res = Http::timeout(5)->get('https://fapi.binance.com/futures/data/globalLongShortAccountRatio', [
                    'symbol' => $symbol,
                    'period' => '5m',
                    'limit' => 1
                ]);
                if ($res->successful()) {
                    $json = $res->json();
                    if (is_array($json) && isset($json[0]['longShortRatio'])) {
                        return (float) $json[0]['longShortRatio'];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Binance L/S Ratio Error for {$symbol}: " . $e->getMessage());
            }
            return 1.0; // Neutral fallback
        });
    }

    /**
     * Get CoinGecko Trending Search List (Cached 10 mins)
     * Returns an array of trending ticker symbols ending in USDT (e.g. ['BTCUSDT', 'SOLUSDT', ...])
     */
    public function getTrendingTraffic(): array
    {
        $cacheKey = 'macro_trending_traffic';
        
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) {
            return $cached;
        }

        $result = (function () {
            try {
                $res = Http::timeout(8)->get('https://api.coingecko.com/api/v3/search/trending');
                if ($res->successful()) {
                    $json = $res->json();
                    if (isset($json['coins']) && is_array($json['coins'])) {
                        $trendingSymbols = [];
                        foreach ($json['coins'] as $coinWrapper) {
                            if (isset($coinWrapper['item']['symbol'])) {
                                $trendingSymbols[] = strtoupper($coinWrapper['item']['symbol']) . 'USDT';
                            }
                        }
                        return $trendingSymbols;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('CoinGecko Trending Traffic Error: ' . $e->getMessage());
            }
            return []; // Fallback
        })();

        if (!empty($result)) {
            Cache::put($cacheKey, $result, 600);
        }

        return $result;
    }

    // ─── PRIVATE HELPERS ─────────────────────────────────────────────────────

    /**
     * Normalize a CoinGecko market coin into the Binance ticker shape.
     */
    private function normalizeCoinGeckoTicker(array $coin): array
    {
        $symbol  = strtoupper($coin['symbol']) . 'USDT';
        $price   = (float)($coin['current_price'] ?? 0);
        $change  = (float)($coin['price_change_percentage_24h'] ?? 0);
        $high    = (float)($coin['high_24h'] ?? $price * 1.02);
        $low     = (float)($coin['low_24h']  ?? $price * 0.98);
        $vol     = (float)($coin['total_volume'] ?? 0);

        return [
            'symbol'             => $symbol,
            'lastPrice'          => (string) $price,
            'priceChangePercent' => (string) round($change, 4),
            'highPrice'          => (string) $high,
            'lowPrice'           => (string) $low,
            'volume'             => (string) $vol,
            'quoteVolume'        => (string) $vol,
            'count'              => 0,
        ];
    }

    /**
     * Map a Binance interval string to CoinGecko days parameter.
     */
    private function intervalToDays(string $interval): int
    {
        return match($interval) {
            '1m', '3m', '5m', '15m', '30m' => 1,
            '1h', '2h', '4h'               => 7,
            '1d', '3d'                      => 30,
            '1w', '1M'                      => 90,
            default                         => 7,
        };
    }
}
