<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MultiSourceMarketService;
use App\Services\StockMarketService;
use App\Services\ForexMarketService;
use App\Services\BondMarketService;
use App\Services\CommodityMarketService;
use App\Services\CryptoMarketService;
use App\Services\NewsMarketService;
use App\Services\PredictionService;
use App\Services\EconomicCalendarService;
use App\Http\Requests\MarketRequest;
use App\Http\Requests\BatchPredictionRequest;
use App\Http\Requests\TerminalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MarketController extends Controller
{
    // ─── CRYPTO ───────────────────────────────────────────────────────────────

    public function ticker(MarketRequest $request, MultiSourceMarketService $market): JsonResponse
    {
        $symbol = $request->get('symbol');
        $data   = $market->getTicker24hr($symbol);
        return response()->json($data);
    }

    public function klines(MarketRequest $request, MultiSourceMarketService $market): JsonResponse
    {
        $symbol   = strtoupper($request->get('symbol', 'BTCUSDT'));
        $interval = $request->get('interval', '1h');
        $limit    = max(1, min((int) $request->get('limit', 200), 500));

        $data = $market->getKlines($symbol, $interval, $limit);
        return response()->json($data);
    }

    public function depth(MarketRequest $request, MultiSourceMarketService $market): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'BTCUSDT'));
        $limit  = max(1, min((int) $request->get('limit', 20), 100));

        $data = $market->getDepth($symbol, $limit);
        return response()->json($data);
    }

    public function trades(MarketRequest $request, MultiSourceMarketService $market): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'BTCUSDT'));
        $limit  = max(1, min((int) $request->get('limit', 50), 100));

        $data = $market->getRecentTrades($symbol, $limit);
        return response()->json($data);
    }

    public function topPairs(MultiSourceMarketService $market): JsonResponse
    {
        $data = $market->getTopPairs(50);
        return response()->json($data);
    }

    public function prediction(MarketRequest $request, MultiSourceMarketService $market, PredictionService $prediction): JsonResponse
    {
        set_time_limit(120); // Prevent PHP killing request before XGBoost finishes on cold start
        $symbol   = strtoupper($request->get('symbol', 'BTCUSDT'));
        $interval = $request->get('interval', '15m');
        // 500 klines is sufficient for all indicators (EMA-200, ADX, Ichimoku, etc.)
        // and shares the same cache key as the page-load chart fetch (limit=500),
        // avoiding a redundant 40-120 s cold Binance API call.
        $klines   = $market->getKlines($symbol, $interval, 500);
        $signal   = $prediction->getScalpingSignal($klines, $symbol, $interval);
        return response()->json($signal);
    }

    public function batchPredictions(BatchPredictionRequest $request, MultiSourceMarketService $market, PredictionService $prediction): JsonResponse
    {
        $symbols  = $request->get('symbols', []);
        $interval = $request->get('interval', '15m');
        
        if (is_string($symbols)) {
            $symbols = explode(',', $symbols);
        }

        if (!is_array($symbols)) {
            $symbols = [];
        }

        $symbols = array_slice(array_filter(array_map(function($s) {
            return is_scalar($s) ? trim((string)$s) : '';
        }, $symbols)), 0, 50);
        
        $batchKlines = [];
        foreach ($symbols as $symbol) {
            $batchKlines[strtoupper($symbol)] = [
                'klines' => $market->getKlines($symbol, $interval, 1000)
            ];
        }

        $results = $prediction->getBatchSignals($batchKlines, $interval);

        return response()->json($results);
    }

    /**
     * Get deep historical crypto klines with optional startTime/endTime.
     * Supports up to 1000 candles via Binance (real data, no synthetic fallback).
     */
    public function klinesHistory(Request $request, CryptoMarketService $cryptoMarket): JsonResponse
    {
        $request->validate([
            'symbol'   => 'nullable|string|max:20',
            'interval' => 'nullable|string|max:10',
            'limit'    => 'nullable|integer|min:1|max:1000',
            'from'     => 'nullable|integer',
            'to'       => 'nullable|integer',
        ]);

        $symbol    = strtoupper($request->get('symbol', 'BTCUSDT'));
        $interval  = $request->get('interval', '1d');
        $limit     = max(1, min((int) $request->get('limit', 365), 1000));
        $startTime = (int) $request->get('from', 0);  // UNIX ms
        $endTime   = (int) $request->get('to', 0);    // UNIX ms

        $data = $cryptoMarket->getCryptoHistoricalKlines($symbol, $interval, $limit, $startTime, $endTime);
        return response()->json($data);
    }

    // ─── STOCKS ───────────────────────────────────────────────────────────────

    /**
     * Get major stock symbols list with real-time quotes from Finnhub.
     * Requires FINNHUB_API_KEY in .env
     */
    public function stocks(StockMarketService $stockMarket): JsonResponse
    {
        $data = $stockMarket->getMajorStocksQuotes();
        return response()->json($data);
    }

    /**
     * Get real-time quote for a single stock symbol.
     */
    public function stockQuote(MarketRequest $request, StockMarketService $stockMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $data   = $stockMarket->getStockQuote($symbol);
        return response()->json($data);
    }

    /**
     * Get company profile + fundamental data for a stock.
     * Returns: name, exchange, industry, market_cap, PE, EPS, ROE, margins, etc.
     */
    public function stockProfile(MarketRequest $request, StockMarketService $stockMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $data   = $stockMarket->getStockProfile($symbol);
        return response()->json($data);
    }

    /**
     * Get stock OHLCV candles from Finnhub.
     * ?symbol=AAPL&resolution=D&from=UNIX_TS&to=UNIX_TS
     */
    public function stockCandles(MarketRequest $request, StockMarketService $stockMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $res    = $request->get('resolution', 'D');
        $from   = (int) $request->get('from', time() - 365 * 86400);
        $to     = (int) $request->get('to', time());

        $data = $stockMarket->getStockCandles($symbol, $res, $from, $to);
        return response()->json($data);
    }

    /**
     * Get major indices (SPY, QQQ, DIA, IWM, EWJ, etc.) via Finnhub.
     */
    public function indices(StockMarketService $stockMarket): JsonResponse
    {
        $data = $stockMarket->getMajorIndices();
        return response()->json($data);
    }

    // ─── FOREX ────────────────────────────────────────────────────────────────

    /**
     * Get latest forex rates (major pairs vs USD) from Frankfurter/ECB.
     * No API key required. Data updated daily ~16:00 CET.
     */
    public function forex(ForexMarketService $forexMarket): JsonResponse
    {
        $data = $forexMarket->getForexRates();
        return response()->json($data);
    }

    /**
     * Get forex historical rates for a currency.
     * ?currency=EUR&days=30
     */
    public function forexHistory(MarketRequest $request, ForexMarketService $forexMarket): JsonResponse
    {
        $currency = strtoupper($request->get('currency', 'EUR'));
        $days     = max(1, min((int) $request->get('days', 30), 365));
        $data     = $forexMarket->getForexHistory($currency, $days);
        return response()->json($data);
    }

    // ─── BONDS ────────────────────────────────────────────────────────────────

    /**
     * Get US Treasury bond/yield rates from Treasury Fiscal Data API.
     * No API key required. Returns T-Bill, T-Note, T-Bond, TIPS rates.
     */
    public function bonds(BondMarketService $bondMarket): JsonResponse
    {
        $data = $bondMarket->getTreasuryYields();
        return response()->json($data);
    }

    // ─── COMMODITIES ─────────────────────────────────────────────────────────

    /**
     * Get real commodity prices from Binance PAXGUSDT (gold) + OKX (silver, oil, gas).
     */
    public function commodities(CommodityMarketService $commodityMarket): JsonResponse
    {
        $data = $commodityMarket->getCommodityPrices();
        return response()->json($data);
    }

    // ─── NEW MARKET FEATURES ──────────────────────────────────────────────────

    public function equityValuation(MarketRequest $request, StockMarketService $stockMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $data   = $stockMarket->getEquityValuation($symbol);
        return response()->json($data);
    }

    public function analystEstimates(MarketRequest $request, StockMarketService $stockMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $data   = $stockMarket->getAnalystEstimates($symbol);
        return response()->json($data);
    }

    public function peerComparison(MarketRequest $request, StockMarketService $stockMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $peersMap = [
            'AAPL'  => ['MSFT', 'GOOGL', 'META', 'AMZN'],
            'MSFT'  => ['AAPL', 'GOOGL', 'AMZN', 'ORCL'],
            'TSLA'  => ['F', 'GM', 'RIVN', 'LCID'],
            'NVDA'  => ['AMD', 'INTC', 'TSM', 'QCOM'],
            'JPM'   => ['BAC', 'WFC', 'C', 'GS'],
        ];
        $peers = $peersMap[$symbol] ?? ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'NVDA'];
        if (!in_array($symbol, $peers)) {
            array_unshift($peers, $symbol);
        }
        
        $data = $stockMarket->getPeerComparison(array_slice($peers, 0, 5));
        return response()->json($data);
    }

    public function forexFull(ForexMarketService $forexMarket): JsonResponse
    {
        $data = $forexMarket->getFullForexRates();
        return response()->json($data);
    }

    public function optionsChain(MarketRequest $request, StockMarketService $stockMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $expiry = $request->get('expiry', '');
        $data   = $stockMarket->getOptionsChain($symbol, $expiry);
        return response()->json($data);
    }

    public function cryptoFutures(CryptoMarketService $cryptoMarket): JsonResponse
    {
        $data = $cryptoMarket->getCryptoFutures();
        return response()->json($data);
    }

    public function commoditiesExtended(CommodityMarketService $commodityMarket): JsonResponse
    {
        $data = $commodityMarket->getExtendedCommodityPrices();
        return response()->json($data);
    }

    // ─── TERMINAL COMMANDS ───────────────────────────────────────────────────

    public function terminal(TerminalRequest $request, StockMarketService $stockMarket, NewsMarketService $newsMarket): JsonResponse
    {
        $cmd   = strtolower($request->get('command', ''));
        $query = strtoupper($request->get('query', ''));

        if (!$cmd) {
            return response()->json(['error' => 'No command provided'], 400);
        }

        try {
            switch ($cmd) {
                case 'srch':
                    // Just return major stocks or a filtered list based on query
                    $data = $stockMarket->searchStocks($query);
                    return response()->json(['type' => 'srch', 'data' => $data]);
                
                case 'bi':
                    // Bloomberg Intelligence: Company Profile + News
                    if (!$query) return response()->json(['error' => 'Symbol required'], 400);
                    $profile = $stockMarket->getStockProfile($query);
                    $news    = $newsMarket->getCompanyNews($query, 7);
                    return response()->json(['type' => 'bi', 'data' => ['profile' => $profile, 'news' => $news]]);

                case 'anr':
                    // Analyst Recommendations
                    if (!$query) return response()->json(['error' => 'Symbol required'], 400);
                    $anr = $stockMarket->getAnalystEstimates($query);
                    return response()->json(['type' => 'anr', 'data' => $anr]);

                case 'crpr':
                    // Quantitative Fundamental Rating based on Real-time Ratios
                    if (!$query) return response()->json(['error' => 'Symbol required'], 400);
                    $val = $stockMarket->getEquityValuation($query);
                    
                    $score = 'BB';
                    $outlook = 'Stable';
                    if (!empty($val['ratios'])) {
                        $ro = $val['ratios'] ?? [];
                        $pts = 0;
                        
                        // Gross Margin Check
                        $gm = (float) ($ro['gross_margin'] ?? data_get($val, 'valuation.gross_margin', 0));
                        if ($gm > 30) $pts++;
                        
                        // Net Margin Check
                        $nm = (float) ($ro['net_margin'] ?? data_get($val, 'valuation.net_margin', 0));
                        if ($nm > 10) $pts++;
                        
                        // Current Ratio Check
                        $cr = (float) ($ro['current_ratio'] ?? data_get($val, 'valuation.current_ratio', 0));
                        if ($cr > 1.5) $pts++;
                        
                        // Debt to Equity Check
                        $de = (float) ($ro['debt_equity'] ?? data_get($val, 'valuation.debt_equity', 100));
                        if ($de < 50) $pts++;
                        
                        // ROE Check
                        $roe = (float) ($ro['roe'] ?? data_get($val, 'valuation.roe', 0));
                        if ($roe > 15) $pts++;
                        
                        $map = [0 => 'C', 1 => 'B', 2 => 'BB', 3 => 'BBB', 4 => 'A', 5 => 'AA'];
                        $score = $map[$pts] ?? 'BB';
                        $outlook = $pts > 3 ? 'Positive' : ($pts < 2 ? 'Negative' : 'Stable');
                    }

                    return response()->json([
                        'type' => 'crpr', 
                        'data' => [
                            'valuation' => $val, 
                            'fundamental_score' => $score, 
                            'outlook' => $outlook,
                            'methodology' => 'Quantitative Ratio Analysis'
                        ]
                    ]);

                default:
                    return response()->json(['error' => 'Unknown command'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── NEWS & MEDIA ────────────────────────────────────────────────────────

    /**
     * Get market news by category.
     * Uses Finnhub for forex/crypto/merger, Google News RSS for markets/economy/tech/politics/business/general.
     */
    public function news(Request $request, NewsMarketService $newsMarket): JsonResponse
    {
        $category = strtolower($request->get('category', 'general'));
        
        $finnhubCategories = ['forex', 'crypto', 'merger'];
        if (in_array($category, $finnhubCategories)) {
            $data = $newsMarket->getMarketNews($category);
        } else {
            $data = $newsMarket->getNewsByCategory($category);
        }
        
        return response()->json($data);
    }

    /**
     * Get company-specific news from Finnhub.
     * ?symbol=AAPL&days=7
     */
    public function companyNews(MarketRequest $request, NewsMarketService $newsMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $days   = max(1, min((int) $request->get('days', 7), 30));
        $data   = $newsMarket->getCompanyNews($symbol, $days);
        return response()->json($data);
    }

    /**
     * Get global economic calendar.
     */
    public function economicCalendar(MarketRequest $request, EconomicCalendarService $calendar): JsonResponse
    {
        $from = $request->get('from');
        $to   = $request->get('to');
        $data = $calendar->getEconomicCalendar($from, $to);
        return response()->json($data);
    }

    // ─── BOOKMARKS ───────────────────────────────────────────────────────────

    public function getBookmarks(Request $request): JsonResponse
    {
        // For guests, return empty array instead of 401 to avoid console errors
        if (! $request->user()) {
            return response()->json([]);
        }

        $bookmarks = \App\Models\SavedArticle::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookmarks);
    }

    public function toggleBookmark(Request $request): JsonResponse
    {
        // Auth guard: bookmarks require an authenticated user
        if (! $request->user()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $userId = $request->user()->id;
        $url    = $request->input('url');

        if (!$url) {
            return response()->json(['error' => 'URL is required'], 400);
        }

        // Sanitize: only allow http/https URLs
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
            return response()->json(['error' => 'Invalid URL'], 422);
        }

        $existing = \App\Models\SavedArticle::where('user_id', $userId)
                        ->where('url', $url)
                        ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['status' => 'removed']);
        }

        $ts = (int) $request->input('datetime');
        if ($ts > 2000000000) { $ts = (int) ($ts / 1000); }

        \App\Models\SavedArticle::create([
            'user_id'      => $userId,
            'article_id'   => $request->input('id'),
            'headline'     => $request->input('headline'),
            'summary'      => $request->input('summary'),
            'source'       => $request->input('source'),
            'url'          => $url,
            'image'        => $request->input('image'),
            'category'     => $request->input('category'),
            'published_at' => $ts ? date('Y-m-d H:i:s', $ts) : null,
        ]);

        return response()->json(['status' => 'saved']);
    }
}
