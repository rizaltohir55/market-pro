<?php

namespace App\Http\Controllers;

use App\Services\StockMarketService;
use App\Services\ForexMarketService;
use App\Services\CommodityMarketService;
use App\Services\MultiSourceMarketService;
use App\Services\PredictionService;
use App\Services\TechnicalAnalysisService;
use App\Services\BondMarketService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Return skeleton view immediately. Data will be loaded via AJAX.
        return view('dashboard.index');
    }

    public function getMarketSummary(MultiSourceMarketService $market)
    {
        return response()->json([
            'topPairs' => $market->getTopPairs(20),
            'gainers'  => $market->getTopGainers(10),
            'losers'   => $market->getTopLosers(10),
        ]);
    }

    public function getStockSummary(StockMarketService $stockMarket, MultiSourceMarketService $market)
    {
        $indices = $stockMarket->getMajorIndices();
        $stocks  = $stockMarket->getMajorStocksQuotes();
        
        // Group KPIs
        $btcTicker = $market->getTicker24hr('BTCUSDT');
        $xauTicker = $market->getTicker24hr('PAXGUSDT');

        $stockCollection = collect($stocks);

        // Pre-filter fields to reduce JSON payload size
        $summaryMapper = fn($s) => [
            'symbol'     => $s['symbol'],
            'name'       => $s['name'],
            'price'      => (float) $s['price'],
            'change_pct' => (float) $s['change_pct'],
            'volume'     => (int)   $s['volume'],
        ];

        return response()->json([
            'indices'      => $indices,
            'stocks'       => $stocks,
            'btcTicker'    => $btcTicker,
            'xauTicker'    => $xauTicker,
            'stockGainers' => $stockCollection->sortByDesc('change_pct')->take(10)->map($summaryMapper)->values(),
            'stockLosers'  => $stockCollection->sortBy('change_pct')->take(10)->map($summaryMapper)->values(),
            'activeStocks' => $stockCollection->sortByDesc('volume')->take(10)->map($summaryMapper)->values(),
        ]);
    }

    public function getGlobalRates(ForexMarketService $forexMarket, CommodityMarketService $commodityMarket)
    {
        return response()->json([
            'forex' => $forexMarket->getForexRates(),
            'commodities' => $commodityMarket->getCommodityPrices(),
        ]);
    }

    public function getGlobalOverview(BondMarketService $bondMarket, CommodityMarketService $commodityMarket)
    {
        return response()->json([
            'bonds' => $bondMarket->getTreasuryYields(),
            'commodities' => $commodityMarket->getCommodityPrices(),
        ]);
    }

    public function trading(Request $request, MultiSourceMarketService $market, StockMarketService $stockMarket, ForexMarketService $forexMarket)
    {
        $symbol = strtoupper($request->get('symbol', 'BTCUSDT'));
        $interval = $request->get('interval', '15m');

        // Determine Asset Type
        $type = 'crypto';
        $cleanSymbol = $symbol;
        
        if (str_starts_with($symbol, 'stock:')) {
            $type = 'stock';
            $cleanSymbol = str_replace('stock:', '', $symbol);
        } elseif (str_starts_with($symbol, 'forex:')) {
            $type = 'forex';
            $cleanSymbol = str_replace('forex:', '', $symbol);
        } elseif (str_ends_with($symbol, 'USDT') || str_ends_with($symbol, 'USD')) {
            $type = 'crypto';
        }
        
        if ($cleanSymbol === 'XAU' || $cleanSymbol === 'PAXGUSDT' || $cleanSymbol === 'XAG') {
            $type = 'commodity';
        }

        $quote = null;
        $profile = null;
        $klines = [];
        $depth = ['bids' => [], 'asks' => []];
        $trades = [];
        $ticker = null;

        if ($type === 'stock') {
            $quote = $stockMarket->getStockQuote($cleanSymbol);
            $profile = $stockMarket->getStockProfile($cleanSymbol);
            // Klines for stocks will be fetched via AJAX in the view for consistency with Asset page
        } elseif ($type === 'crypto' || $type === 'commodity') {
            $klines = $market->getKlines($cleanSymbol, $interval, 200);
            $depth  = $market->getDepth($cleanSymbol, 20);
            $trades = $market->getRecentTrades($cleanSymbol, 30);
            $ticker = $market->getTicker24hr($cleanSymbol);
        } elseif ($type === 'forex') {
            $rates = $forexMarket->getForexRates();
            $parts = explode(':', $symbol);
            $cur = str_replace(['/', 'USD'], '', $parts[0]);
            if (empty($cur)) $cur = $cleanSymbol;
            
            if (isset($rates['rates'][$cur])) {
                $r = $rates['rates'][$cur];
                $quote = [
                    'symbol' => $r['display'],
                    'price' => $r['rate'],
                    'change_pct' => 0,
                ];
            }
        }

        return view('trading.index', compact(
            'symbol', 'cleanSymbol', 'interval', 'type', 
            'klines', 'depth', 'trades', 'ticker', 'quote', 'profile'
        ));
    }

    public function scanner(MultiSourceMarketService $market)
    {
        $pairs = $market->getTopPairs(50);
        return view('scanner.index', compact('pairs'));
    }

    public function analysis(Request $request, MultiSourceMarketService $market, PredictionService $prediction)
    {
        $symbol = strtoupper($request->get('symbol', 'BTCUSDT'));

        $klines15m = $market->getKlines($symbol, '15m', 200);
        $klines1h  = $market->getKlines($symbol, '1h',  200);
        $klines4h  = $market->getKlines($symbol, '4h',  200);

        $signal15m = $prediction->getScalpingSignal($klines15m, $symbol, '15m');
        $signal1h  = $prediction->getScalpingSignal($klines1h,  $symbol, '1h');
        $signal4h  = $prediction->getScalpingSignal($klines4h,  $symbol, '4h');

        $tickerData = $market->getTicker24hr($symbol);
        $ta         = app(TechnicalAnalysisService::class);
        $sr         = $ta->findSupportResistance($klines15m, 10);

        $ticker = is_array($tickerData) && isset($tickerData['lastPrice']) ? $tickerData : ['lastPrice' => 0];

        return view('analysis.index', compact(
            'symbol', 'ticker', 'klines15m',
            'signal15m', 'signal1h', 'signal4h', 'sr'
        ));
    }

    public function settings()
    {
        return view('settings.index');
    }

    public function equity(Request $request)
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        return view('equity.index', compact('symbol'));
    }

    public function fxRates()
    {
        return view('fx.index');
    }

    public function derivatives(Request $request)
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        return view('derivatives.index', compact('symbol'));
    }

    public function commodityMarkets()
    {
        return view('commodity.index');
    }

    public function news()
    {
        return view('news.index');
    }

    public function chartBuilder(Request $request)
    {
        $symbolsParam = $request->get('symbols', 'BTCUSDT');
        $symbols = array_filter(array_map('trim', explode(',', $symbolsParam)));
        if (empty($symbols)) $symbols = ['BTCUSDT'];
        return view('chart_builder.index', compact('symbols'));
    }
}
