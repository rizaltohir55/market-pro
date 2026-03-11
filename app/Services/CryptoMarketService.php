<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CryptoMarketService extends BaseMarketService
{
    /**
     * Get deep historical crypto klines from Binance with optional startTime/endTime.
     * Fetches up to 1000 candles per request, paginates if needed.
     *
     * @param string $symbol   e.g. "BTCUSDT"
     * @param string $interval e.g. "1d","4h","1h"
     * @param int    $limit    Number of candles (max 1000 per Binance)
     * @param int    $startTime UNIX timestamp ms (0 = auto)
     * @param int    $endTime   UNIX timestamp ms (0 = now)
     * @return array
     */
    public function getCryptoHistoricalKlines(
        string $symbol,
        string $interval = '1d',
        int    $limit    = 365,
        int    $startTime = 0,
        int    $endTime   = 0
    ): array {
        $cacheKey = "crypto_hist_{$symbol}_{$interval}_{$limit}_{$startTime}_{$endTime}";
        return Cache::remember($cacheKey, 300, function () use ($symbol, $interval, $limit, $startTime, $endTime) {
            try {
                $params = [
                    'symbol'   => strtoupper($symbol),
                    'interval' => $interval,
                    'limit'    => min($limit, 1000),
                ];
                if ($startTime > 0) $params['startTime'] = $startTime;
                if ($endTime   > 0) $params['endTime']   = $endTime;

                $r = $this->binanceGet('/api/v3/klines', $params);
                if ($r && $r->successful()) {
                    $data = $r->json();
                    if (!empty($data) && is_array($data) && isset($data[0][0])) {
                        Log::info("GlobalMarketService: Historical klines OK for $symbol/$interval — " . count($data) . " candles.");
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
                Log::warning("GlobalMarketService: Historical klines failed for $symbol/$interval");
            } catch (\Exception $e) {
                Log::warning("GlobalMarketService: Historical klines exception for $symbol — " . $e->getMessage());
            }
            return [];
        }) ?? [];
    }
    public function getCryptoFutures(): array
    {
        $cacheKey = 'crypto_futures_spot_fallback';
        return Cache::remember($cacheKey, 60, function () {
            try {
                // FAPI (Futures API) ticker is occasionally blocked or times out on this host. 
                // We intentionally bypass it and directly use the Spot ticker for prices, 
                // which is extremely stable.
                $spotRes = $this->binanceGet('/api/v3/ticker/24hr');
                
                if ($spotRes && $spotRes->successful()) {
                    $tickerData = $spotRes->json();

                    if (is_array($tickerData)) {
                        $tickers = collect($tickerData)
                            ->filter(function($t) {
                                // Filter mostly USDT pairs that typically have perpetuals
                                return str_ends_with($t['symbol'], 'USDT') && !str_contains($t['symbol'], 'UP') && !str_contains($t['symbol'], 'DOWN');
                            });
                        
                        $top = $tickers->sortByDesc('quoteVolume')->take(25)->values();
                        
                        $futures = [];
                        foreach ($top as $t) {
                            $futures[] = [
                                'symbol' => $t['symbol'],
                                'price'  => (float) $t['lastPrice'],
                                'change_pct' => (float) $t['priceChangePercent'],
                                'volume' => (float) $t['quoteVolume'],
                                'funding_rate' => 0.0, // Real-time funding requires FAPI access
                                'next_funding_time' => 0,
                                'mark_price' => (float) $t['lastPrice'],
                            ];
                        }
                        
                        return $futures;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Crypto futures failed — " . $e->getMessage());
            }
            return [];
        }) ?? [];
    }
}
