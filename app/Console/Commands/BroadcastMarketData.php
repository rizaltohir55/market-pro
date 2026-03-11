<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MultiSourceMarketService;
use App\Services\ForexMarketService;
use App\Services\BondMarketService;
use App\Services\CommodityMarketService;
use App\Services\PredictionService;
use App\Events\MarketUpdated;
use Illuminate\Support\Facades\Log;

class BroadcastMarketData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:broadcast-market {--symbol=BTCUSDT}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Broadcast real-time market data to all WebSocket listeners';

    /**
     * Execute the console command.
     */
    public function handle(
        MultiSourceMarketService $market,
        ForexMarketService $forexMarket,
        BondMarketService $bondMarket,
        CommodityMarketService $commodityMarket
    ) {
        $symbol = strtoupper($this->option('symbol'));
        $this->info("Starting broadcast for $symbol...");

        while (true) {
            try {
                $payload = [];

                // Fetch data (similar to StreamController logic)
                $fearGreed       = $market->getFearAndGreedIndex();
                $lsRatio         = $market->getGlobalLongShortRatio($symbol);
                $trendingSymbols = $market->getTrendingTraffic();
                $isTrending      = in_array($symbol, $trendingSymbols);

                // Dashboard Snapshot
                $payload['dashboard'] = [
                    'market'      => $market->getTopPairs(10),
                    'gainers'     => $market->getTopGainers(5),
                    'forex'       => $forexMarket->getForexRates(),
                    'bonds'       => $bondMarket->getTreasuryYields(),
                    'commodities' => $commodityMarket->getCommodityPrices(),
                    'fear_greed'  => $fearGreed,
                ];

                // Trading Snapshot for specific symbol
                $ticker = $market->getTicker24hr($symbol);
                $klines15m = $market->getKlines($symbol, '15m', 200);
                $klines1h  = $market->getKlines($symbol, '1h', 200);

                $payload['trading'] = [
                    'ticker' => $ticker,
                ];

                // Watchlist & Shared
                $wlSymbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'PAXGUSDT', 'XRPUSDT', 'DOGEUSDT'];
                $allTickers = $market->getTicker24hr();
                $wlData = [];
                if (!empty($allTickers) && is_array($allTickers)) {
                    foreach ($allTickers as $t) {
                        if (isset($t['symbol']) && in_array($t['symbol'], $wlSymbols)) {
                            $wlData[] = $t;
                        }
                    }
                }
                $payload['watchlist'] = $wlData;
                $payload['top_pairs'] = $market->getTopPairs(30);

                // Broadcast
                event(new MarketUpdated($symbol, $payload));

                $this->info("Broadcast sent for $symbol at " . now()->toDateTimeString());

            } catch (\Exception $e) {
                $this->error("Broadcast Error: " . $e->getMessage());
                Log::error("BroadcastMarketData Error: " . $e->getMessage());
            }

            sleep(3);
        }
    }
}
