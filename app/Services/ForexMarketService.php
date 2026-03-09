<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ForexMarketService extends BaseMarketService
{
    /**
     * Internal: Fetch and cache raw forex data from ExchangeRate-API.
     */
    private function getRawForexData(): array
    {
        $cacheKey = 'forex_rates_raw_v1';
        return Cache::remember($cacheKey, 3600, function () {
            try {
                $r = $this->exchangeRateGet('/latest/USD');
                if ($r && $r->successful()) {
                    $d = $r->json();
                    if (!empty($d['rates']) && $d['result'] === 'success') {
                        return [
                            'rates' => $d['rates'],
                            'date'  => $d['time_last_update_utc'] ?? '',
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('ForexMarketService: ExchangeRate-API fetch failed — ' . $e->getMessage());
            }
            return [];
        }) ?? [];
    }

    public function getForexRates(): array
    {
        $data = $this->getRawForexData();

        if (!empty($data)) {
            $rates = $data['rates'];
            $converted = [];
            foreach ($this->forexPairs as $cur => $display) {
                $rateFromUsd = $rates[$cur] ?? null;
                if ($rateFromUsd === null) continue;

                // Determine if USD is the Base or Quote currency based on display string
                // Convention: base/quote
                $isUsdBase = str_starts_with($display, 'USD/');
                
                $converted[$cur] = [
                    'pair'         => $display,
                    'rate'         => $isUsdBase ? round($rateFromUsd, 6) : ($rateFromUsd > 0 ? round(1 / $rateFromUsd, 6) : 0),
                    'rate_inverse' => $isUsdBase ? ($rateFromUsd > 0 ? round(1 / $rateFromUsd, 6) : 0) : round($rateFromUsd, 6),
                    'display'      => $display,
                ];
            }

            Log::info('GlobalMarketService: Forex rates OK (major) — ' . ($data['date'] ?? '?'));
            return [
                'base'   => 'USD',
                'date'   => $data['date'] ?? '',
                'source' => 'exchangerate_api_ecb',
                'rates'  => $converted,
            ];
        }

        // Fallback: derive forex from Binance stablecoin pairs
        $cacheKey = 'forex_rates_binance_fallback';
        return Cache::remember($cacheKey, 3600, function () {
            return $this->getForexFromBinanceCross();
        }) ?? [];
    }

    public function getFullForexRates(): array
    {
        $data = $this->getRawForexData();

        if (!empty($data)) {
            $rates = [];
            foreach ($data['rates'] as $cur => $rate) {
                $rates[$cur] = [
                    'pair'         => "USD/{$cur}",
                    'rate'         => $rate,
                    'rate_inverse' => $rate > 0 ? round(1 / $rate, 6) : 0,
                ];
            }

            return [
                'base'   => 'USD',
                'date'   => $data['date'] ?? '',
                'source' => 'exchangerate_api_ecb',
                'rates'  => $rates,
            ];
        }

        return [];
    }

    /**
     * Fallback: derive forex rates from Binance fiat stablecoin pairs.
     */
    private function getForexFromBinanceCross(): array
    {
        $crossMap = [
            'EUR' => 'EURUSDT', 'GBP' => 'GBPUSDT', 'AUD' => 'AUDUSDT',
            'JPY' => 'JPYUSDT', 'CAD' => 'CADUSDT', 'CHF' => 'CHFUSDT',
        ];

        $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($crossMap) {
            foreach ($crossMap as $cur => $sym) {
                $pool->as($cur)
                    ->withOptions($this->getHttpOptions(8))
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])
                    ->retry(2, 500)
                    ->get($this->binanceBase . '/api/v3/ticker/24hr', ['symbol' => $sym]);
            }
        });

        $rates = [];
        foreach ($crossMap as $cur => $sym) {
            try {
                $r = $responses[$cur] ?? null;
                if (!($r instanceof \Illuminate\Http\Client\Response) || !$r->successful()) {
                    $r = $this->binanceGet('/api/v3/ticker/24hr', ['symbol' => $sym]);
                }

                if ($r && $r->successful()) {
                    $d = $r->json();
                    if (!empty($d['lastPrice'])) {
                        $priceInUsd = (float) $d['lastPrice'];
                        $rates[$cur] = [
                            'pair'         => $cur . '/USD',
                            'rate'         => $priceInUsd,
                            'rate_inverse' => $priceInUsd > 0 ? round(1 / $priceInUsd, 6) : 0,
                            'display'      => $this->forexPairs[$cur] ?? ($cur . '/USD'),
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("ForexMarketService: Binance forex cross $sym exception — " . $e->getMessage());
            }
        }

        if (!empty($rates)) {
            Log::info('GlobalMarketService: Forex rates from Binance cross-pairs — ' . count($rates) . ' pairs.');
            return [
                'base'   => 'USD',
                'date'   => date('Y-m-d'),
                'source' => 'binance_cross_pairs',
                'rates'  => $rates,
            ];
        }
        return [];
    }

    /**
     * Get forex historical rates for a currency pair.
     */
    public function getForexHistory(string $fromCur, int $days = 30): array
    {
        $fromCur  = strtoupper($fromCur);
        $cacheKey = "forex_history_{$fromCur}_{$days}";
        return Cache::remember($cacheKey, 3600, function () use ($fromCur, $days) {
            $crossMap = [
                'EUR' => 'EURUSDT', 'GBP' => 'GBPUSDT', 'AUD' => 'AUDUSDT',
                'JPY' => 'JPYUSDT', 'CAD' => 'CADUSDT', 'CHF' => 'CHFUSDT',
            ];
            $sym = $crossMap[$fromCur] ?? null;
            if ($sym) {
                try {
                    $r = $this->binanceGet('/api/v3/klines', [
                        'symbol'   => $sym,
                        'interval' => '1d',
                        'limit'    => min($days, 365),
                    ]);
                    if ($r && $r->successful()) {
                        $data = $r->json();
                        if (!empty($data) && isset($data[0][0])) {
                            return collect($data)->map(fn($k) => [
                                'date' => date('Y-m-d', intdiv($k[0], 1000)),
                                'rate' => (float) $k[4],
                            ])->toArray();
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("GlobalMarketService: Forex history exception for $fromCur — " . $e->getMessage());
                }
            }
            return [];
        }) ?? [];
    }
}
