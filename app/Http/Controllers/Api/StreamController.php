<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MultiSourceMarketService;
use App\Services\ForexMarketService;
use App\Services\BondMarketService;
use App\Services\CommodityMarketService;
use App\Services\PredictionService;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    public function stream(Request $request, MultiSourceMarketService $market, ForexMarketService $forexMarket, BondMarketService $bondMarket, CommodityMarketService $commodityMarket, PredictionService $prediction)
    {
        $page     = $request->get('page', 'dashboard');
        $symbol   = strtoupper($request->get('symbol', 'BTCUSDT'));
        $interval = $request->get('interval', '15m');

        // Prevent PHP from timing out long-running SSE connections
        set_time_limit(0);

        return response()->stream(function () use ($page, $symbol, $interval, $market, $forexMarket, $bondMarket, $commodityMarket, $prediction) {
            // Tell the browser to reconnect after 3 seconds automatically
            echo "retry: 3000\n\n";

            $payload = [];
            try {
                // Fetch Macro Data (Cached)
                $fearGreed       = $market->getFearAndGreedIndex();
                $lsRatio         = $market->getGlobalLongShortRatio($symbol);
                $trendingSymbols = $market->getTrendingTraffic();
                $isTrending      = in_array(strtoupper($symbol), $trendingSymbols);

                if ($page === 'trading') {
                    $payload['ticker'] = $market->getTicker24hr($symbol);
                    $payload['depth']  = $market->getDepth($symbol, 20);
                    $payload['trades'] = $market->getRecentTrades($symbol, 50);
                    $payload['klines'] = $market->getKlines($symbol, $interval, 200);

                    $klines15m = $market->getKlines($symbol, '15m', 200);
                    $klines1h  = $market->getKlines($symbol, '1h', 200);

                    $payload['predictions'] = [
                        '15m' => $prediction->getScalpingSignal($klines15m, $klines1h, $fearGreed, $lsRatio, $isTrending),
                    ];
                }
                elseif ($page === 'dashboard') {
                    $payload['market']  = $market->getTopPairs(10);
                    $payload['gainers'] = $market->getTopGainers(5);
                    $payload['losers']  = $market->getTopLosers(5);
                    $payload['global']  = [
                        'volume24h'    => array_sum(array_column($payload['market'] ?? [], 'volume')),
                        'fear_greed'   => $fearGreed,
                    ];

                    // ── Real multi-asset snapshot for dashboard ─────────────────
                    // Forex (cached 1 hour, from Frankfurter/ECB — no key needed)
                    $payload['forex']       = $forexMarket->getForexRates();

                    // US Treasury yields (cached 4 hours — no key needed)
                    $payload['bonds']       = $bondMarket->getTreasuryYields();

                    // Commodities: Gold (Binance), Silver/Oil/Gas (OKX)
                    $payload['commodities'] = $commodityMarket->getCommodityPrices();
                }
                elseif ($page === 'scanner') {
                    $payload['pairs'] = $market->getTopPairs(50);
                }
                elseif ($page === 'analysis') {
                    $payload['ticker'] = $market->getTicker24hr($symbol);
                    $payload['klines'] = $market->getKlines($symbol, $interval, 200);

                    $klines15m = $market->getKlines($symbol, '15m', 200);
                    $klines1h  = $market->getKlines($symbol, '1h', 200);
                    $klines4h  = $market->getKlines($symbol, '4h', 200);
                    $klines1d  = $market->getKlines($symbol, '1d', 200);

                    $payload['predictions'] = [
                        '15m' => $prediction->getScalpingSignal($klines15m, $klines1h, $fearGreed, $lsRatio, $isTrending),
                        '1h'  => $prediction->getScalpingSignal($klines1h, $klines4h, $fearGreed, $lsRatio, $isTrending),
                        '4h'  => $prediction->getScalpingSignal($klines4h, $klines1d, $fearGreed, $lsRatio, $isTrending),
                    ];
                }

                // Shared components
                $payload['top_pairs'] = $market->getTopPairs(30);

                // Specific tickers for watchlist (We fetch PAXGUSDT for Gold)
                $wlSymbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'PAXGUSDT', 'XRPUSDT', 'DOGEUSDT'];

                // Fetch ALL tickers once (cached) to prevent sequential API requests
                $allTickers = $market->getTicker24hr();

                $wlData = [];
                if (!empty($allTickers) && is_array($allTickers)) {
                    if (isset($allTickers['symbol'])) {
                        if (in_array($allTickers['symbol'], $wlSymbols)) {
                            $wlData[] = $allTickers;
                        }
                    } else {
                        foreach ($allTickers as $t) {
                            if (isset($t['symbol']) && in_array($t['symbol'], $wlSymbols)) {
                                $wlData[] = $t;
                            }
                        }
                    }
                }

                $payload['watchlist'] = $wlData;

                echo "data: " . json_encode($payload) . "\n\n";
            } catch (\Exception $e) {
                echo "event: error\ndata: " . json_encode(['message' => $e->getMessage()]) . "\n\n";
            }

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            // We intentionally do NOT use an infinite while(true) loop here.
            // Why? The PHP built-in web server on Windows is strictly single-threaded.
            // If we hold this connection open infinitely, NO OTHER requests can be served.
            // By ending the stream here and returning, the server closes the connection.
            // The browser's native EventSource will automatically reconnect after the `retry` interval.
        }, 200, [
            'Content-Type'     => 'text/event-stream',
            'Cache-Control'    => 'no-cache',
            'Connection'       => 'keep-alive',
            'X-Accel-Buffering'=> 'no',
        ]);
    }
}
