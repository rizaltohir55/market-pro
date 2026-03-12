<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BondMarketService extends BaseMarketService
{
    /**
     * Get US Treasury yield rates from FRED (St. Louis Federal Reserve).
     * Free, no API key. CSV endpoint: fred.stlouisfed.org/graph/fredgraph.csv
     *
     * Series IDs:
     *   DGS3MO = 3-Month Treasury Bill
     *   DGS2   = 2-Year Treasury Note
     *   DGS5   = 5-Year Treasury Note
     *   DGS10  = 10-Year Treasury Note
     *   DGS30  = 30-Year Treasury Bond
     */
    public function getTreasuryYields(): array
    {
        $cacheKey = 'treasury_yields';
        return Cache::remember($cacheKey, 14400, function () {
            $series = [
                'DGS3MO' => ['type' => 'T-Bill',  'label' => '3-Month Treasury Bill'],
                'DGS2'   => ['type' => 'T-Note 2Y', 'label' => '2-Year Treasury Note'],
                'DGS5'   => ['type' => 'T-Note 5Y', 'label' => '5-Year Treasury Note'],
                'DGS10'  => ['type' => 'T-Note 10Y','label' => '10-Year Treasury Note'],
                'DGS30'  => ['type' => 'T-Bond',    'label' => '30-Year Treasury Bond'],
            ];

            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($series) {
                foreach ($series as $seriesId => $meta) {
                    $pool->as($seriesId)
                        ->withOptions($this->getHttpOptions(10))
                        ->withHeaders(array_merge($this->browserHeaders(), ['Accept' => '*/*']))
                        ->retry(2, 500)
                        ->get($this->fredBase, ['id' => $seriesId]);
                }
            });

            $yields = [];
            foreach ($series as $seriesId => $meta) {
                try {
                    $r = $responses[$seriesId] ?? null;

                    // Fallback to sequential if pool failed for this series
                    if (!($r instanceof \Illuminate\Http\Client\Response) || !$r->successful()) {
                        $r = Http::withOptions($this->getHttpOptions(10))
                            ->withHeaders(array_merge($this->browserHeaders(), ['Accept' => '*/*']))
                            ->retry(2, 500)
                            ->get($this->fredBase, ['id' => $seriesId]);
                    }

                    if ($r instanceof \Illuminate\Http\Client\Response && $r->successful()) {
                        $csv   = $r->body();
                        $lines = array_filter(array_map('trim', explode("\n", $csv)));
                        // Skip header, find the last non-empty data row with a numeric value
                        $rate  = null;
                        $date  = null;
                        foreach (array_reverse($lines) as $line) {
                            if (str_starts_with($line, 'observation_date') || str_starts_with($line, 'DATE')) continue;
                            $parts = explode(',', $line);
                            if (count($parts) >= 2 && is_numeric($parts[1]) && $parts[1] !== '.') {
                                $date = trim($parts[0]);
                                $rate = (float) trim($parts[1]);
                                break;
                            }
                        }
                        if ($rate !== null) {
                            $yields[] = [
                                'type'   => $meta['type'],
                                'label'  => $meta['label'],
                                'rate'   => $rate,
                                'date'   => $date,
                                'source' => 'fred_stlouisfed',
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("BondMarketService: FRED {$seriesId} exception — " . $e->getMessage());
                }
            }

            if (!empty($yields)) {
                Log::info('BondMarketService: US Treasury yields OK from FRED — ' . count($yields) . ' maturities.');
                return [
                    'source' => 'fred_stlouisfed',
                    'yields' => $yields,
                ];
            }

            // Fallback to Yahoo Finance indices if FRED failed
            Log::warning('BondMarketService: FRED fell back, trying Yahoo Finance indices.');
            $yahooYields = $this->getTreasuryYieldsFromYahoo();
            if (!empty($yahooYields)) {
                return [
                    'source' => 'yahoo_finance',
                    'yields' => $yahooYields,
                ];
            }

            Log::warning('GlobalMarketService: Treasury yields all failed.');
            return [];
        }) ?? [];
    }

    /**
     * Fallback: Get Treasury Yield proxies from Yahoo Finance.
     * ^IRX = 13-Week, ^FVX = 5-Year, ^TNX = 10-Year, ^TYX = 30-Year.
     */
    private function getTreasuryYieldsFromYahoo(): array
    {
        $map = [
            '^IRX' => ['type' => 'T-Bill',   'label' => '3-Month Treasury Bill'],
            '^FVX' => ['type' => 'T-Note 5Y', 'label' => '5-Year Treasury Note'],
            '^TNX' => ['type' => 'T-Note 10Y', 'label' => '10-Year Treasury Note'],
            '^TYX' => ['type' => 'T-Bond',    'label' => '30-Year Treasury Bond'],
        ];

        try {
            $r = $this->yahooGet('/v7/finance/quote', ['symbols' => implode(',', array_keys($map))]);
            if ($r && $r->successful()) {
                $d = $r->json();
                $results = $d['quoteResponse']['result'] ?? [];
                $yields = [];
                foreach ($results as $q) {
                    $sym = $q['symbol'];
                    $yields[] = [
                        'type'   => $map[$sym]['type'],
                        'label'  => $map[$sym]['label'],
                        'rate'   => (float) ($q['regularMarketPrice'] ?? 0),
                        'date'   => date('Y-m-d'),
                        'source' => 'yahoo_finance',
                    ];
                }
                return $yields;
            }
        } catch (\Exception $e) {
            Log::warning("BondMarketService: Yahoo fallback exception — " . $e->getMessage());
        }
        return [];
    }
}
