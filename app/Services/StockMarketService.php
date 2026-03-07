<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StockMarketService extends BaseMarketService
{
    /**
     * Get single stock quote from Yahoo Finance (primary) or Stooq (fallback).
     * Returns real-time price during market hours, last close otherwise.
     *
     * @param string $symbol e.g. "AAPL"
     */
    public function getStockQuote(string $symbol): array
    {
        $symbol   = strtoupper($symbol);
        $cacheKey = "stock_quote_{$symbol}";

        return Cache::remember($cacheKey, 60, function () use ($symbol) {
            // ── Primary: Yahoo Finance v8 chart API ─────────────────────────
            try {
                $r = $this->yahooGet('/v8/finance/chart/' . urlencode($symbol), [
                    'interval' => '1d',
                    'range'    => '2d',
                ]);
                if ($r && $r->successful()) {
                    $d    = $r->json();
                    $meta = $d['chart']['result'][0]['meta'] ?? null;
                    if ($meta && !empty($meta['regularMarketPrice'])) {
                        $price    = (float) $meta['regularMarketPrice'];
                        $prev     = (float) ($meta['chartPreviousClose'] ?? $meta['previousClose'] ?? 0);
                        $change   = $prev > 0 ? $price - $prev : 0;
                        $changePct = $prev > 0 ? ($change / $prev) * 100 : 0;
                        Log::info("GlobalMarketService: Yahoo stock quote OK for $symbol @ \$$price");
                        return [
                            'symbol'     => $symbol,
                            'name'       => $meta['longName'] ?? $meta['shortName'] ?? $symbol,
                            'price'      => $price,
                            'open'       => (float) ($meta['regularMarketOpen'] ?? $price),
                            'high'       => (float) ($meta['regularMarketDayHigh'] ?? $price),
                            'low'        => (float) ($meta['regularMarketDayLow'] ?? $price),
                            'prev_close' => $prev,
                            'change'     => round($change, 4),
                            'change_pct' => round($changePct, 4),
                            'volume'     => (int) ($meta['regularMarketVolume'] ?? 0),
                            'market_cap' => (float) ($meta['marketCap'] ?? 0),
                            'currency'   => $meta['currency'] ?? 'USD',
                            'exchange'   => $meta['exchangeName'] ?? '',
                            'timestamp'  => $meta['regularMarketTime'] ?? time(),
                            'source'     => 'yahoo_finance',
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("GlobalMarketService: Yahoo quote exception for $symbol — " . $e->getMessage());
            }

            // ── Fallback: Stooq CSV (very reliable, no key) ───────────────
            try {
                $stooqSym = strtolower($symbol) . '.us';
                $r = Http::withOptions(['verify' => storage_path('cacert.pem'), 'curl' => [CURLOPT_FOLLOWLOCATION => true]])
                    ->withHeaders($this->browserHeaders())
                    ->timeout(8)
                    ->get('https://stooq.com/q/l/', [
                        's' => $stooqSym,
                        'f' => 'sd2t2ohlcv',
                        'h' => '',
                        'e' => 'csv',
                    ]);
                if ($r && $r->successful()) {
                    $csv   = $r->body();
                    $lines = array_filter(explode("\n", trim($csv)));
                    if (count($lines) >= 2) {
                        $headers = array_map('trim', explode(',', $lines[0]));
                        $values  = array_map('trim', explode(',', $lines[1]));
                        $row     = array_combine($headers, $values);
                        $close   = (float) ($row['Close'] ?? 0);
                        $open    = (float) ($row['Open'] ?? $close);
                        $change  = $close - $open;
                        $changePct = $open > 0 ? ($change / $open) * 100 : 0;
                        if ($close > 0) {
                            Log::info("GlobalMarketService: Stooq stock quote OK for $symbol @ \$$close");
                            return [
                                'symbol'     => $symbol,
                                'name'       => $symbol,
                                'price'      => $close,
                                'open'       => $open,
                                'high'       => (float) ($row['High'] ?? $close),
                                'low'        => (float) ($row['Low'] ?? $close),
                                'prev_close' => $open,
                                'change'     => round($change, 4),
                                'change_pct' => round($changePct, 4),
                                'volume'     => (int) ($row['Volume'] ?? 0),
                                'market_cap' => 0,
                                'currency'   => 'USD',
                                'exchange'   => 'US',
                                'timestamp'  => time(),
                                'source'     => 'stooq',
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("GlobalMarketService: Stooq quote exception for $symbol — " . $e->getMessage());
            }

            Log::warning("GlobalMarketService: All stock quote sources failed for $symbol");
            return [];
        }) ?? [];
    }

    /**
     * Get company profile from Yahoo Finance.
     * Returns name, sector, industry, website, market cap, etc.
     * Fundamental metrics (PE, EPS etc.) from Yahoo summary detail.
     */
    public function getStockProfile(string $symbol): array
    {
        $symbol   = strtoupper($symbol);
        $cacheKey = "stock_profile_{$symbol}_v2";

        return Cache::remember($cacheKey, 3600, function () use ($symbol) {
            // Yahoo Finance v10 quoteSummary with crumb
            $modules = 'summaryProfile,summaryDetail,defaultKeyStatistics,financialData,quoteType';
            $path = "/v10/finance/quoteSummary/{$symbol}";
            try {
                $r = $this->yahooGetWithCrumb($path, ['modules' => $modules]);
                if ($r && $r->successful()) {
                        $d   = $r->json();
                        $res = $d['quoteSummary']['result'][0] ?? null;
                        if ($res) {
                            $profile  = $res['summaryProfile']       ?? [];
                            $detail   = $res['summaryDetail']        ?? [];
                            $stats    = $res['defaultKeyStatistics'] ?? [];
                            $financial= $res['financialData']        ?? [];
                            $qt       = $res['quoteType']            ?? [];
                            $val = fn($obj, $key) => $obj[$key]['raw'] ?? $obj[$key] ?? null;
                            Log::info("GlobalMarketService: Yahoo profile OK for $symbol (v11)");
                            return [
                                'symbol'      => $symbol,
                                'name'        => $qt['longName'] ?? $qt['shortName'] ?? $symbol,
                                'sector'      => $profile['sector'] ?? '',
                                'industry'    => $profile['industry'] ?? '',
                                'website'     => $profile['website'] ?? '',
                                'country'     => $profile['country'] ?? '',
                                'employees'   => $val($profile, 'fullTimeEmployees'),
                                'market_cap'  => $val($detail, 'marketCap'),
                                'currency'    => $qt['currency'] ?? 'USD',
                                'exchange'    => $qt['exchange'] ?? '',
                                'source'      => 'yahoo_finance',
                                'fundamentals' => [
                                    'pe_ratio'        => $val($detail, 'trailingPE'),
                                    'forward_pe'      => $val($detail, 'forwardPE'),
                                    'eps_ttm'         => $val($stats, 'trailingEps'),
                                    'forward_eps'     => $val($stats, 'forwardEps'),
                                    'peg_ratio'       => $val($stats, 'pegRatio'),
                                    'beta'            => $val($stats, 'beta'),
                                    '52w_high'        => $val($detail, 'fiftyTwoWeekHigh'),
                                    '52w_low'         => $val($detail, 'fiftyTwoWeekLow'),
                                    'dividend_yield'  => $val($detail, 'dividendYield'),
                                    'payout_ratio'    => $val($detail, 'payoutRatio'),
                                    'book_value'      => $val($stats, 'bookValue'),
                                    'price_to_book'   => $val($stats, 'priceToBook'),
                                    'roe'             => $val($financial, 'returnOnEquity'),
                                    'roa'             => $val($financial, 'returnOnAssets'),
                                    'gross_margin'    => $val($financial, 'grossMargins'),
                                    'operating_margin'=> $val($financial, 'operatingMargins'),
                                    'net_margin'      => $val($financial, 'profitMargins'),
                                    'debt_equity'     => $val($financial, 'debtToEquity'),
                                    'current_ratio'   => $val($financial, 'currentRatio'),
                                    'revenue_growth'  => $val($financial, 'revenueGrowth'),
                                    'earnings_growth' => $val($financial, 'earningsGrowth'),
                                ],
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("GlobalMarketService: Yahoo profile exception for $symbol — " . $e->getMessage());
                }
            return [];
        }) ?? [];
    }

    /**
     * Get OHLCV candle data for a stock.
     * Uses Yahoo Finance v8 chart API (free, no key).
     *
     * @param string $symbol   e.g. "AAPL"
     * @param string $res      Resolution: "1m","5m","1h","1d","1wk","1mo"
     * @param int    $from     UNIX timestamp start
     * @param int    $to       UNIX timestamp end
     */
    public function getStockCandles(string $symbol, string $res = 'D', int $from = 0, int $to = 0): array
    {
        $symbol   = strtoupper($symbol);
        if ($to === 0) $to = time();
        if ($from === 0) $from = $to - (365 * 86400);

        // Map Finnhub resolution codes to Yahoo Finance intervals
        $intervalMap = [
            '1'  => '1m', '5' => '5m', '15' => '15m', '30' => '30m',
            '60' => '1h', 'D' => '1d', 'W'  => '1wk', 'M'  => '1mo',
        ];
        $yInterval = $intervalMap[$res] ?? '1d';

        $cacheKey = "stock_candles_{$symbol}_{$res}_{$from}_{$to}";
        return Cache::remember($cacheKey, 300, function () use ($symbol, $yInterval, $from, $to) {
            try {
                $r = $this->yahooGet('/v8/finance/chart/' . urlencode($symbol), [
                    'interval'  => $yInterval,
                    'period1'   => $from,
                    'period2'   => $to,
                ]);
                if ($r && $r->successful()) {
                    $d    = $r->json();
                    $res  = $d['chart']['result'][0] ?? null;
                    if ($res) {
                        $timestamps = $res['timestamp'] ?? [];
                        $quotes     = $res['indicators']['quote'][0] ?? [];
                        $candles    = [];
                        foreach ($timestamps as $i => $ts) {
                            $open  = $quotes['open'][$i]  ?? null;
                            $close = $quotes['close'][$i] ?? null;
                            if ($open === null || $close === null) continue;
                            $candles[] = [
                                'time'   => (int) $ts,
                                'open'   => (float) $open,
                                'high'   => (float) ($quotes['high'][$i]   ?? $close),
                                'low'    => (float) ($quotes['low'][$i]    ?? $close),
                                'close'  => (float) $close,
                                'volume' => (int)   ($quotes['volume'][$i] ?? 0),
                            ];
                        }
                        Log::info("GlobalMarketService: Yahoo candles OK for $symbol — " . count($candles) . " bars.");
                        return $candles;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("GlobalMarketService: Yahoo candles exception for $symbol — " . $e->getMessage());
            }
            return [];
        }) ?? [];
    }

    /**
     * Bulk-fetch quotes for multiple symbols using individual v8 chart calls.
     * Yahoo v7 bulk API is more rate-limited server-side; v8 chart is reliable.
     * Uses Stooq CSV as fallback per symbol.
     *
     * @param array $symbols e.g. ['AAPL','MSFT','NVDA']
     */
    public function getBulkStockQuotes(array $symbols): array
    {
        if (empty($symbols)) return [];
        $cacheKey = 'bulk_quotes_' . md5(implode(',', $symbols));
        return Cache::remember($cacheKey, 120, function () use ($symbols) {
            // Try Yahoo v7 with query1 AND query2 alternating
            $hosts = ['https://query1.finance.yahoo.com', 'https://query2.finance.yahoo.com'];
            foreach ($hosts as $host) {
                try {
                    $r = Http::withOptions(['verify'=>storage_path('cacert.pem'),'curl'=>[CURLOPT_FOLLOWLOCATION=>true]])
                        ->withHeaders($this->browserHeaders())
                        ->timeout(15)
                        ->get("$host/v7/finance/quote", [
                            'symbols' => implode(',', $symbols),
                        ]);
                    if ($r && $r->successful()) {
                        $d      = $r->json();
                        $result = $d['quoteResponse']['result'] ?? null;
                        if (!empty($result)) {
                            $quotes = [];
                            foreach ($result as $q) {
                                $price     = (float) ($q['regularMarketPrice'] ?? 0);
                                $prev      = (float) ($q['regularMarketPreviousClose'] ?? $price);
                                $change    = (float) ($q['regularMarketChange'] ?? 0);
                                $changePct = (float) ($q['regularMarketChangePercent'] ?? 0);
                                $quotes[]  = [
                                    'symbol'     => $q['symbol'] ?? '',
                                    'name'       => $q['shortName'] ?? $q['symbol'] ?? '',
                                    'price'      => $price,
                                    'open'       => (float) ($q['regularMarketOpen'] ?? $price),
                                    'high'       => (float) ($q['regularMarketDayHigh'] ?? $price),
                                    'low'        => (float) ($q['regularMarketDayLow'] ?? $price),
                                    'prev_close' => $prev,
                                    'change'     => round($change, 4),
                                    'change_pct' => round($changePct, 4),
                                    'volume'     => (int) ($q['regularMarketVolume'] ?? 0),
                                    'market_cap' => (float) ($q['marketCap'] ?? 0),
                                    'currency'   => $q['currency'] ?? 'USD',
                                    'exchange'   => $q['exchangeName'] ?? '',
                                    'timestamp'  => time(),
                                    'source'     => 'yahoo_finance',
                                ];
                            }
                            if (!empty($quotes)) {
                                Log::info('GlobalMarketService: Bulk Yahoo quotes OK — ' . count($quotes) . ' symbols.');
                                return $quotes;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("GlobalMarketService: Bulk Yahoo ($host) exception — " . $e->getMessage());
                }
            }

            // Fallback: individual v8 chart calls per symbol (using Http pool for speed)
            Log::warning('GlobalMarketService: Yahoo v7 bulk failed, falling back to individual v8 calls using Http::pool.');
            $quotes = [];
            
            $responses = Http::pool(function (Pool $pool) use ($symbols) {
                    $reqs = [];
                    foreach ($symbols as $sym) {
                        $reqs[] = $pool->as($sym)
                            ->withOptions(['verify' => storage_path('cacert.pem')])
                            ->get("https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($sym) . "?interval=1d&range=2d");
                    }
                    return $reqs;
                });

            foreach ($responses as $sym => $r) {
                if ($r instanceof \Illuminate\Http\Client\Response && $r->successful()) {
                    $d = $r->json();
                    $meta = $d['chart']['result'][0]['meta'] ?? null;
                    if ($meta && !empty($meta['regularMarketPrice'])) {
                        $price    = (float) $meta['regularMarketPrice'];
                        $prev     = (float) ($meta['chartPreviousClose'] ?? $meta['previousClose'] ?? 0);
                        $change   = $prev > 0 ? $price - $prev : 0;
                        $changePct = $prev > 0 ? ($change / $prev) * 100 : 0;
                        
                        $quotes[] = [
                            'symbol'     => strval($sym),
                            'name'       => $meta['longName'] ?? $meta['shortName'] ?? strval($sym),
                            'price'      => $price,
                            'open'       => (float) ($meta['regularMarketDayHigh'] ?? $price),
                            'high'       => (float) ($meta['regularMarketDayHigh'] ?? $price),
                            'low'        => (float) ($meta['regularMarketDayLow'] ?? $price),
                            'prev_close' => $prev,
                            'change'     => round($change, 4),
                            'change_pct' => round($changePct, 4),
                            'volume'     => (int) ($meta['regularMarketVolume'] ?? 0),
                            'market_cap' => (float) ($meta['marketCap'] ?? 0),
                            'currency'   => $meta['currency'] ?? 'USD',
                            'exchange'   => $meta['exchangeName'] ?? '',
                            'timestamp'  => $meta['regularMarketTime'] ?? time(),
                            'source'     => 'yahoo_finance_pool',
                        ];
                    }
                }
            }
            
            // For any that failed in pool, try Stooq synchronously
            $failedSymbols = array_diff($symbols, array_column($quotes, 'symbol'));
            foreach ($failedSymbols as $sym) {
                $q = $this->getStockQuote($sym); // Fallback to full method which tries Stooq
                if (!empty($q) && $q['source'] === 'stooq') {
                    $quotes[] = $q;
                }
            }
            
            Log::info('GlobalMarketService: Individual v8 pool fetching completed — ' . count($quotes) . '/' . count($symbols));
            return $quotes;
        }) ?? [];
    }

    /**
     * Get quotes for all major stocks in one efficient batch request.
     */
    public function getMajorStocksQuotes(): array
    {
        // Yahoo Finance bulk query is much more efficient — single request for all
        return $this->getBulkStockQuotes($this->majorStocks);
    }

    /**
     * Get major indices using Yahoo Finance (ETF proxies).
     * SPY=S&P500, QQQ=Nasdaq100, DIA=Dow Jones, IWM=Russell2000, EWJ=Nikkei, etc.
     */
    public function getMajorIndices(): array
    {
        $indices = [
            '^JKSE' => 'IHSG (^JKSE)',
            '^IXIC' => 'Nasdaq (^IXIC)',
            'SPY'  => 'S&P 500 (SPY)',
            'QQQ'  => 'Nasdaq 100 (QQQ)',
            'DIA'  => 'Dow Jones (DIA)',
            'IWM'  => 'Russell 2000 (IWM)',
            'EWJ'  => 'Nikkei 225 (EWJ)',
            'EWG'  => 'DAX (EWG)',
            'FXI'  => 'China A50 (FXI)',
            'EWZ'  => 'Brazil (EWZ)',
        ];
        $cacheKey = 'major_indices';
        return Cache::remember($cacheKey, 60, function () use ($indices) {
            $quotes = $this->getBulkStockQuotes(array_keys($indices));
            return array_map(function ($q) use ($indices) {
                $q['name'] = $indices[$q['symbol']] ?? $q['symbol'];
                return $q;
            }, $quotes);
        }) ?? [];
    }
    
    public function searchStocks(string $query): array
    {
        $query = urlencode(trim($query));
        $cacheKey = "stock_search_{$query}";
        
        return Cache::remember($cacheKey, 3600, function () use ($query) {
            try {
                $r = $this->finnhubGet('/search', ['q' => $query]);
                if ($r && $r->successful()) {
                    $d = $r->json();
                    if (!empty($d['result'])) {
                        // Filter out empty symbols or non-US common stocks to keep it clean, but Finnhub returns mixed types
                        // We will limit to 10 results and fetch bulk quotes for them to display real-time prices
                        $symbols = [];
                        $matches = [];
                        foreach ($d['result'] as $res) {
                            if (!empty($res['symbol']) && strpos($res['symbol'], '.') === false) { // basic filter for US primary
                                $symbols[] = strtoupper($res['symbol']);
                                $matches[$res['symbol']] = $res['description'] ?? $res['symbol'];
                                if (count($symbols) >= 10) break;
                            }
                        }
                        
                        if (!empty($symbols)) {
                            $quotes = $this->getBulkStockQuotes($symbols);
                            $final = [];
                            foreach ($quotes as $q) {
                                $sym = $q['symbol'];
                                $q['name'] = $matches[$sym] ?? $q['name']; // Use Finnhub's description
                                $final[] = $q;
                            }
                            return $final;
                        }
                    }
                }
            } catch (\Exception $e) {
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    throw $e;
                }
                Log::warning("GlobalMarketService: searchStocks exception for $query — " . $e->getMessage());
            }
            return [];
        }) ?? [];
    }

    // ─── EQUITY ANALYTICS ─────────────────────────────────────────────────────

    public function getEquityValuation(string $symbol): array
    {
        $symbol   = strtoupper($symbol);
        $cacheKey = "equity_valuation_{$symbol}_v3";

        return Cache::remember($cacheKey, 3600, function () use ($symbol) {
            try {
                $r = $this->finnhubGet('/stock/metric', ['symbol' => $symbol, 'metric' => 'all']);
                if ($r && $r->successful()) {
                    $d = $r->json();
                    if (isset($d['metric'])) {
                        $m = $d['metric'];
                        return [
                            'symbol' => $symbol,
                            'valuation' => [
                                'pe_ratio'   => $m['peTTM'] ?? null,
                                'forward_pe' => $m['forwardPE'] ?? null,
                                'peg_ratio'  => $m['pegTTM'] ?? null,
                                'price_to_book' => $m['pbAnnual'] ?? $m['pbQuarterly'] ?? null,
                                'price_to_sales' => $m['psTTM'] ?? $m['psAnnual'] ?? null,
                                'ev_ebitda'  => $m['evEbitdaTTM'] ?? null,
                                'ev_revenue' => $m['evRevenueTTM'] ?? null,
                            ],
                            'ratios' => [
                                'roe' => $m['roeTTM'] ?? null,
                                'roa' => $m['roaTTM'] ?? null,
                                'gross_margin' => $m['grossMarginTTM'] ?? null,
                                'operating_margin' => $m['operatingMarginTTM'] ?? null,
                                'net_margin' => $m['netProfitMarginTTM'] ?? null,
                                'debt_equity' => $m['longTermDebt/equityQuarterly'] ?? $m['totalDebt/totalEquityQuarterly'] ?? null,
                                'current_ratio' => $m['currentRatioQuarterly'] ?? null,
                                'revenue_growth' => $m['revenueGrowthQuarterlyYoy'] ?? null,
                                'dividend_yield' => $m['currentDividendYieldTTM'] ?? null,
                            ],
                            'financials' => [
                                'total_cash' => null, // Finnhub free doesn't provide absolute financials in metric
                                'total_debt' => null,
                                'total_revenue' => null,
                                'ebitda'    => null,
                                'free_cash_flow' => null,
                            ]
                        ];
                    }
                }
            } catch (\Exception $e) {
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    throw $e;
                }
                Log::warning("Equity valuation via Finnhub failed for $symbol — " . $e->getMessage());
            }
            return [];
        }) ?? [];
    }

    public function getAnalystEstimates(string $symbol): array
    {
        $symbol   = strtoupper($symbol);
        $cacheKey = "analyst_estimates_{$symbol}_v4";

        return Cache::remember($cacheKey, 7200, function () use ($symbol) {
            $baseRes = [
                'symbol' => $symbol,
                'has_recommendation' => false,
                'recommendation' => [
                    'strong_buy' => 0, 'buy' => 0, 'hold' => 0, 'sell' => 0, 'strong_sell' => 0,
                ],
                'target_price' => [
                    'current' => null, 'high' => null, 'low' => null, 'mean' => null,
                ],
                'has_earnings' => false,
                'earnings_estimates' => [],
                'revenue_estimates'  => []
            ];

            try {
                // 1. Primary: Finnhub Recommendations
                $r = $this->finnhubGet('/stock/recommendation', ['symbol' => $symbol]);
                if ($r && $r->successful()) {
                    $d = $r->json();
                    if (is_array($d) && count($d) > 0) {
                        $recs = $d[0];
                        $baseRes['has_recommendation'] = true;
                        $baseRes['recommendation'] = [
                            'strong_buy'  => $recs['strongBuy'] ?? 0,
                            'buy'         => $recs['buy'] ?? 0,
                            'hold'        => $recs['hold'] ?? 0,
                            'sell'        => $recs['sell'] ?? 0,
                            'strong_sell' => $recs['strongSell'] ?? 0,
                        ];
                    }
                }

                // 2. Finnhub Price Target (Free tier usually supports this)
                $rPT = $this->finnhubGet('/stock/price-target', ['symbol' => $symbol]);
                if ($rPT && $rPT->successful()) {
                    $pt = $rPT->json();
                    if (!empty($pt['targetMean'])) {
                        $baseRes['target_price'] = [
                            'current' => $pt['targetMean'] ?? null,
                            'high'    => $pt['targetHigh'] ?? null,
                            'low'     => $pt['targetLow'] ?? null,
                            'mean'    => $pt['targetMean'] ?? null,
                        ];
                    }
                }
            } catch (\Exception $e) {
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    throw $e;
                }
                Log::warning("Analyst estimates via Finnhub failed for $symbol — " . $e->getMessage());
            }

            // 3. Fallback: Robust Yahoo Finance Scrape (extract from JSON state)
            if (empty($baseRes['target_price']['mean'])) {
                try {
                    $url = "https://finance.yahoo.com/quote/{$symbol}";
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
                    curl_setopt($ch, CURLOPT_CAINFO, storage_path('cacert.pem'));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array_values($this->browserHeaders()));
                    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                    $html = curl_exec($ch);
                    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($status == 200 && $html) {
                        // Yahoo stores state in a JSON string inside a script tag
                        // We look for 'targetMeanPrice' or similar indicators in the JSON blob
                        if (preg_match('/"targetMeanPrice":\s*\{\s*"raw":\s*([\d\.]+)/i', $html, $m)) {
                            $baseRes['target_price']['mean'] = (float) $m[1];
                        } elseif (preg_match('/targetPrice.*?data.*?value.*?([\d\.]+)/i', $html, $m)) {
                            $baseRes['target_price']['mean'] = (float) $m[1];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Yahoo price target fallback failed for $symbol - " . $e->getMessage());
                }
            }

            return $baseRes;
        }) ?? [];
    }

    public function getPeerComparison(array $symbols): array
    {
        $quotes = $this->getBulkStockQuotes($symbols);
        $symbols = array_map('strtoupper', array_column($quotes, 'symbol'));
        
        // 1. Check cache first
        $valuations = [];
        $missing = [];
        foreach ($symbols as $sym) {
            $cacheKey = "equity_valuation_{$sym}_v3";
            $cached = Cache::get($cacheKey);
            if ($cached) {
                $valuations[$sym] = $cached;
            } else {
                $missing[] = $sym;
            }
        }

        // 2. Fetch missing sequentially with small delay to avoid rate limit (30 req/min)
        if (!empty($missing)) {
            foreach ($missing as $sym) {
                try {
                    $r = $this->finnhubGet('/stock/metric', ['symbol' => $sym, 'metric' => 'all']);
                    if ($r && $r->successful()) {
                        $d = $r->json();
                        if (isset($d['metric'])) {
                            $m = $d['metric'];
                            $valData = [
                                'symbol' => $sym,
                                'valuation' => [
                                    'pe_ratio'   => $m['peTTM'] ?? null,
                                    'forward_pe' => $m['forwardPE'] ?? null,
                                    'peg_ratio'  => $m['pegTTM'] ?? null,
                                    'price_to_book' => $m['pbAnnual'] ?? $m['pbQuarterly'] ?? null,
                                    'price_to_sales' => $m['psTTM'] ?? $m['psAnnual'] ?? null,
                                    'ev_ebitda'  => $m['evEbitdaTTM'] ?? null,
                                    'ev_revenue' => $m['evRevenueTTM'] ?? null,
                                ],
                                'ratios' => [
                                    'roe' => $m['roeTTM'] ?? null,
                                    'roa' => $m['roaTTM'] ?? null,
                                    'gross_margin' => $m['grossMarginTTM'] ?? null,
                                    'operating_margin' => $m['operatingMarginTTM'] ?? null,
                                    'net_margin' => $m['netProfitMarginTTM'] ?? null,
                                    'debt_equity' => $m['longTermDebt/equityQuarterly'] ?? $m['totalDebt/totalEquityQuarterly'] ?? null,
                                    'current_ratio' => $m['currentRatioQuarterly'] ?? null,
                                    'revenue_growth' => $m['revenueGrowthQuarterlyYoy'] ?? null,
                                    'dividend_yield' => $m['currentDividendYieldTTM'] ?? null,
                                ],
                                'financials' => [
                                    'total_cash' => null,
                                    'total_debt' => null,
                                    'total_revenue' => null,
                                    'ebitda'    => null,
                                    'free_cash_flow' => null,
                                ]
                            ];
                            $valuations[$sym] = $valData;
                            // Fundamental data changes slowly; cache for 24h to avoid API drain
                            Cache::put("equity_valuation_{$sym}_v3", $valData, 86400);
                        }
                    }
                    // Wait 250ms between requests to respect 30 req/min limit
                    usleep(250000); 
                } catch (\Exception $e) {
                    Log::warning("Peer comparison fetch failed for $sym: " . $e->getMessage());
                }
            }
        }

        // 3. Assemble results
        $enriched = [];
        foreach ($quotes as $q) {
            $sym = strtoupper($q['symbol']);
            $val = $valuations[$sym] ?? [];
            
            $enriched[] = [
                'symbol'     => $q['symbol'],
                'name'       => $q['name'],
                'price'      => $q['price'],
                'change_pct' => $q['change_pct'],
                'market_cap' => $q['market_cap'] ?? 0,
                'pe_ratio'   => $val['valuation']['pe_ratio'] ?? null,
                'price_to_book' => $val['valuation']['price_to_book'] ?? null,
            ];
        }
        return $enriched;
    }
    public function getOptionsChain(string $symbol, string $expiry = ''): array
    {
        $symbol   = strtoupper($symbol);
        $cacheKey = "options_chain_{$symbol}_{$expiry}_v3";

        return Cache::remember($cacheKey, 300, function () use ($symbol, $expiry) {
            $path = "/v7/finance/options/" . urlencode($symbol);
            $params = [];
            if ($expiry) {
                $params['date'] = $expiry;
            }
            
            try {
                $r = $this->yahooGetWithCrumb($path, $params);
                    
                if ($r && $r->successful()) {
                    $d = $r->json();
                    $res = $d['optionChain']['result'][0] ?? null;
                    if ($res && !empty($res['options'][0])) {
                        return [
                            'symbol' => $res['underlyingSymbol'] ?? $symbol,
                            'underlyingPrice' => $res['quote']['regularMarketPrice'] ?? 0,
                            'expirations' => $res['expirationDates'] ?? [],
                            'options' => $res['options'][0] ?? [],
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Options chain failed for $symbol — " . $e->getMessage());
            }

            return [];
        }) ?? [];
    }
}
