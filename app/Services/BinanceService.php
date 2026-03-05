<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BinanceService
{
    private string $baseUrl = 'https://api.binance.com';

    /**
     * Make HTTP request to Binance with SSL handling.
     */
    private function request(string $path, array $params = [])
    {
        return Http::withOptions(['verify' => storage_path('cacert.pem')])
            ->timeout(15)
            ->retry(2, 500)
            ->get($this->baseUrl . $path, $params);
    }

    /**
     * Get 24hr ticker stats for a specific symbol or all symbols.
     */
    public function getTicker24hr(?string $symbol = null): array
    {
        $cacheKey = 'binance_ticker_' . ($symbol ?? 'all');
        return Cache::remember($cacheKey, 10, function () use ($symbol) {
            try {
                $params = $symbol ? ['symbol' => strtoupper($symbol)] : [];
                $response = $this->request('/api/v3/ticker/24hr', $params);
                if ($response->successful()) {
                    return $response->json() ?? [];
                }
            } catch (\Exception $e) {
                Log::warning('Binance API error (ticker): ' . $e->getMessage());
            }
            return [];
        }) ?? [];
    }

    /**
     * Get kline/candlestick data.
     */
    public function getKlines(string $symbol, string $interval = '1h', int $limit = 100): array
    {
        $cacheKey = "binance_klines_{$symbol}_{$interval}_{$limit}";
        return Cache::remember($cacheKey, 15, function () use ($symbol, $interval, $limit) {
            try {
                $response = $this->request('/api/v3/klines', [
                    'symbol' => strtoupper($symbol),
                    'interval' => $interval,
                    'limit' => $limit,
                ]);
                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data)) {
                        return collect($data)->map(function ($k) {
                            return [
                                'time' => intdiv($k[0], 1000),
                                'open' => (float) $k[1],
                                'high' => (float) $k[2],
                                'low' => (float) $k[3],
                                'close' => (float) $k[4],
                                'volume' => (float) $k[5],
                                'close_time' => intdiv($k[6], 1000),
                                'quote_volume' => (float) $k[7],
                                'trades' => (int) $k[8],
                            ];
                        })->toArray();
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Binance API error (klines): ' . $e->getMessage());
            }
            return [];
        }) ?? [];
    }

    /**
     * Get order book depth.
     */
    public function getDepth(string $symbol, int $limit = 20): array
    {
        $cacheKey = "binance_depth_{$symbol}_{$limit}";
        return Cache::remember($cacheKey, 5, function () use ($symbol, $limit) {
            try {
                $response = $this->request('/api/v3/depth', [
                    'symbol' => strtoupper($symbol),
                    'limit' => $limit,
                ]);
                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data)) return $data;
                }
            } catch (\Exception $e) {
                Log::warning('Binance API error (depth): ' . $e->getMessage());
            }
            return ['bids' => [], 'asks' => []];
        }) ?? ['bids' => [], 'asks' => []];
    }

    /**
     * Get recent trades.
     */
    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $cacheKey = "binance_trades_{$symbol}";
        return Cache::remember($cacheKey, 5, function () use ($symbol, $limit) {
            try {
                $response = $this->request('/api/v3/trades', [
                    'symbol' => strtoupper($symbol),
                    'limit' => $limit,
                ]);
                if ($response->successful()) {
                    return $response->json() ?? [];
                }
            } catch (\Exception $e) {
                Log::warning('Binance API error (trades): ' . $e->getMessage());
            }
            return [];
        }) ?? [];
    }

    /**
     * Get all USDT pairs with significant volume for the scanner.
     */
    public function getTopPairs(int $limit = 50): array
    {
        $tickers = $this->getTicker24hr();

        if (empty($tickers)) return [];

        return collect($tickers)
            ->filter(fn($t) => str_ends_with($t['symbol'], 'USDT') && (float)$t['quoteVolume'] > 1000000)
            ->sortByDesc(fn($t) => (float) $t['quoteVolume'])
            ->take($limit)
            ->map(function ($t) {
                return [
                    'symbol' => $t['symbol'],
                    'pair' => str_replace('USDT', '/USDT', $t['symbol']),
                    'price' => (float) $t['lastPrice'],
                    'change' => (float) $t['priceChangePercent'],
                    'high' => (float) $t['highPrice'],
                    'low' => (float) $t['lowPrice'],
                    'volume' => (float) $t['volume'],
                    'quoteVolume' => (float) $t['quoteVolume'],
                    'trades' => (int) $t['count'],
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get top gainers (24h change).
     */
    public function getTopGainers(int $limit = 10): array
    {
        $tickers = $this->getTicker24hr();
        if (empty($tickers)) return [];

        return collect($tickers)
            ->filter(fn($t) => str_ends_with($t['symbol'], 'USDT') && (float)$t['quoteVolume'] > 500000)
            ->sortByDesc(fn($t) => (float) $t['priceChangePercent'])
            ->take($limit)
            ->map(fn($t) => [
                'symbol' => $t['symbol'],
                'pair' => str_replace('USDT', '/USDT', $t['symbol']),
                'price' => (float) $t['lastPrice'],
                'change' => (float) $t['priceChangePercent'],
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get top losers (24h change).
     */
    public function getTopLosers(int $limit = 10): array
    {
        $tickers = $this->getTicker24hr();
        if (empty($tickers)) return [];

        return collect($tickers)
            ->filter(fn($t) => str_ends_with($t['symbol'], 'USDT') && (float)$t['quoteVolume'] > 500000)
            ->sortBy(fn($t) => (float) $t['priceChangePercent'])
            ->take($limit)
            ->map(fn($t) => [
                'symbol' => $t['symbol'],
                'pair' => str_replace('USDT', '/USDT', $t['symbol']),
                'price' => (float) $t['lastPrice'],
                'change' => (float) $t['priceChangePercent'],
            ])
            ->values()
            ->toArray();
    }
}
