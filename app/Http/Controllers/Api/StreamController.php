<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MultiSourceMarketService;
use App\Services\ForexMarketService;
use App\Services\BondMarketService;
use App\Services\CommodityMarketService;
use App\Services\PredictionService;
use Illuminate\Http\Request;

/**
 * StreamController
 * 
 * @deprecated Use WebSockets (Laravel Reverb) + app:broadcast-market command instead.
 * This SSE implementation is kept for backward compatibility but is resource-intensive.
 */
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
            // Heartbeat/Retry setting for browser reconnection
            echo "retry: 3000\n\n";

            // Properly stream data using a while loop
            while (true) {
                // If the client disconnected, stop the loop immediately
                if (connection_aborted()) {
                    break;
                }

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
                        $klinesCurrent = $market->getKlines($symbol, $interval, 200);
                        $payload['klines'] = $klinesCurrent;

                        if ($interval === '15m') {
                            $klines15m = $klinesCurrent;
                        } else {
                            $klines15m = $market->getKlines($symbol, '15m', 200);
                        }

                        if ($interval === '1h') {
                            $klines1h = $klinesCurrent;
                        } else {
                            $klines1h = $market->getKlines($symbol, '1h', 200);
                        }

                        $payload['predictions'] = [
                            '15m' => $prediction->getScalpingSignal($klines15m, $symbol, '15m', $klines1h, $fearGreed, $lsRatio, $isTrending),
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
                        $payload['forex']       = $forexMarket->getForexRates();
                        $payload['bonds']       = $bondMarket->getTreasuryYields();
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
                            '15m' => $prediction->getScalpingSignal($klines15m, $symbol, '15m', $klines1h, $fearGreed, $lsRatio, $isTrending),
                            '1h'  => $prediction->getScalpingSignal($klines1h,  $symbol, '1h',  $klines4h, $fearGreed, $lsRatio, $isTrending),
                            '4h'  => $prediction->getScalpingSignal($klines4h,  $symbol, '4h',  $klines1d, $fearGreed, $lsRatio, $isTrending),
                        ];
                    }

                    // Shared components
                    $payload['top_pairs'] = $market->getTopPairs(30);

                    // Specific tickers for watchlist
                    $wlSymbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'PAXGUSDT', 'XRPUSDT', 'DOGEUSDT'];
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

                // Wait for the next update cycle (3 seconds)
                sleep(3);

                // Note on php artisan serve: If the PHP built-in web server on Windows is single-threaded,
                // this infinite loop may block other requests. However, this is the correct SSE implementation
                // for production environments (PHP-FPM/Nginx).
            }
        }, 200, [
            'Content-Type'     => 'text/event-stream',
            'Cache-Control'    => 'no-cache',
            'Connection'       => 'keep-alive',
            'X-Accel-Buffering'=> 'no',
        ]);
    }
}
