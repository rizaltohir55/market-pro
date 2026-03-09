<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\StockMarketService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchPeerData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $symbols;

    /**
     * Create a new job instance.
     */
    public function __construct(array $symbols)
    {
        $this->symbols = $symbols;
    }

    /**
     * Execute the job.
     */
    public function handle(StockMarketService $marketService): void
    {
        foreach ($this->symbols as $symbol) {
            try {
                // Using the unified method in StockMarketService would be ideal, 
                // but we moved the logic here to avoid recursion or duplication.
                // We'll directly call the API logic or a helper in the service.
                
                // For simplicity and respecting the service boundaries, 
                // we'll implement the fetch here or call a private/public method in service.
                $this->fetchAndCache($marketService, $symbol);
                
                // Respect rate limits (30 req/min for free tier)
                usleep(2000000); // 2 seconds delay between symbols to be safe
            } catch (\Exception $e) {
                Log::error("FetchPeerData Job failed for $symbol: " . $e->getMessage());
            }
        }
    }

    protected function fetchAndCache(StockMarketService $service, string $symbol): void
    {
        // Reflection or public access to finnhub logic
        $r = $service->getExternalMetric($symbol);
        
        if ($r && isset($r['metric'])) {
            $m = $r['metric'];
            $valData = [
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
                    'total_cash' => null,
                    'total_debt' => null,
                    'total_revenue' => null,
                    'ebitda'    => null,
                    'free_cash_flow' => null,
                ]
            ];
            
            Cache::put("equity_valuation_{$symbol}_v3", $valData, 86400); // 24h
            Log::info("Async Peer Data cached for $symbol");
        }
    }
}
