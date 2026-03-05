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
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MarketController extends Controller
{
    // ─── CRYPTO ───────────────────────────────────────────────────────────────

    public function ticker(Request $request, MultiSourceMarketService $market): JsonResponse
    {
        $symbol = $request->get('symbol');
        $data   = $market->getTicker24hr($symbol);
        return response()->json($data);
    }

    public function klines(Request $request, MultiSourceMarketService $market): JsonResponse
    {
        $symbol   = strtoupper($request->get('symbol', 'BTCUSDT'));
        $interval = $request->get('interval', '1h');
        $limit    = min((int) $request->get('limit', 200), 500);

        $data = $market->getKlines($symbol, $interval, $limit);
        return response()->json($data);
    }

    public function depth(Request $request, MultiSourceMarketService $market): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'BTCUSDT'));
        $limit  = min((int) $request->get('limit', 20), 100);

        $data = $market->getDepth($symbol, $limit);
        return response()->json($data);
    }

    public function trades(Request $request, MultiSourceMarketService $market): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'BTCUSDT'));
        $limit  = min((int) $request->get('limit', 50), 100);

        $data = $market->getRecentTrades($symbol, $limit);
        return response()->json($data);
    }

    public function topPairs(MultiSourceMarketService $market): JsonResponse
    {
        $data = $market->getTopPairs(50);
        return response()->json($data);
    }

    public function prediction(Request $request, MultiSourceMarketService $market, PredictionService $prediction): JsonResponse
    {
        $symbol   = strtoupper($request->get('symbol', 'BTCUSDT'));
        $interval = $request->get('interval', '15m');
        $klines   = $market->getKlines($symbol, $interval, 200);
        $signal   = $prediction->getScalpingSignal($klines);
        return response()->json($signal);
    }

    /**
     * Get deep historical crypto klines with optional startTime/endTime.
     * Supports up to 1000 candles via Binance (real data, no synthetic fallback).
     */
    public function klinesHistory(Request $request, CryptoMarketService $cryptoMarket): JsonResponse
    {
        $symbol    = strtoupper($request->get('symbol', 'BTCUSDT'));
        $interval  = $request->get('interval', '1d');
        $limit     = min((int) $request->get('limit', 365), 1000);
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
    public function stockQuote(Request $request, StockMarketService $stockMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $data   = $stockMarket->getStockQuote($symbol);
        return response()->json($data);
    }

    /**
     * Get company profile + fundamental data for a stock.
     * Returns: name, exchange, industry, market_cap, PE, EPS, ROE, margins, etc.
     */
    public function stockProfile(Request $request, StockMarketService $stockMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $data   = $stockMarket->getStockProfile($symbol);
        return response()->json($data);
    }

    /**
     * Get stock OHLCV candles from Finnhub.
     * ?symbol=AAPL&resolution=D&from=UNIX_TS&to=UNIX_TS
     */
    public function stockCandles(Request $request, StockMarketService $stockMarket): JsonResponse
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
    public function forexHistory(Request $request, ForexMarketService $forexMarket): JsonResponse
    {
        $currency = strtoupper($request->get('currency', 'EUR'));
        $days     = min((int) $request->get('days', 30), 365);
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

    public function equityValuation(Request $request, StockMarketService $stockMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $data   = $stockMarket->getEquityValuation($symbol);
        return response()->json($data);
    }

    public function analystEstimates(Request $request, StockMarketService $stockMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $data   = $stockMarket->getAnalystEstimates($symbol);
        return response()->json($data);
    }

    public function peerComparison(Request $request, StockMarketService $stockMarket): JsonResponse
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

    public function optionsChain(Request $request, StockMarketService $stockMarket): JsonResponse
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

    public function terminal(Request $request, StockMarketService $stockMarket, NewsMarketService $newsMarket): JsonResponse
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
                    // Credit/Health Rating (using Valuation as proxy)
                    if (!$query) return response()->json(['error' => 'Symbol required'], 400);
                    $val = $stockMarket->getEquityValuation($query);
                    
                    // Synthesize a dummy "Score" for demo purposes based on real metrics
                    $score = 'BB';
                    $outlook = 'Stable';
                    if (!empty($val['ratios'])) {
                        $ro = $val['ratios'];
                        $pts = 0;
                        if (($ro['gross_margin'] ?? 0) > 30) $pts++;
                        if (($ro['net_margin'] ?? 0) > 10) $pts++;
                        if (($ro['current_ratio'] ?? 0) > 1.5) $pts++;
                        if (($ro['debt_equity'] ?? 100) < 50) $pts++;
                        if (($ro['roe'] ?? 0) > 15) $pts++;
                        
                        $map = [0 => 'C', 1 => 'B', 2 => 'BB', 3 => 'BBB', 4 => 'A', 5 => 'AA'];
                        $score = $map[$pts] ?? 'BB';
                        $outlook = $pts > 3 ? 'Positive' : ($pts < 2 ? 'Negative' : 'Stable');
                    }

                    return response()->json(['type' => 'crpr', 'data' => ['valuation' => $val, 'synthetic_rating' => $score, 'outlook' => $outlook]]);

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
    public function companyNews(Request $request, NewsMarketService $newsMarket): JsonResponse
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        $days   = min((int) $request->get('days', 7), 30);
        $data   = $newsMarket->getCompanyNews($symbol, $days);
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

        \App\Models\SavedArticle::create([
            'user_id'      => $userId,
            'article_id'   => $request->input('id'),
            'headline'     => $request->input('headline'),
            'summary'      => $request->input('summary'),
            'source'       => $request->input('source'),
            'url'          => $url,
            'image'        => $request->input('image'),
            'category'     => $request->input('category'),
            'published_at' => $request->input('datetime')
                                ? date('Y-m-d H:i:s', (int) $request->input('datetime'))
                                : null,
        ]);

        return response()->json(['status' => 'saved']);
    }
}
