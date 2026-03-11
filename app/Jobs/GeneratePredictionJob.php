<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\MultiSourceMarketService;
use App\Services\PredictionService;
use Illuminate\Support\Facades\Log;

class GeneratePredictionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $symbol;
    public $horizon;
    public $interval;
    public $steps;

    public $timeout = 180; // 3 minutes timeout

    public function __construct(string $symbol, string $horizon, string $interval, int $steps)
    {
        $this->symbol = $symbol;
        $this->horizon = $horizon;
        $this->interval = $interval;
        $this->steps = $steps;
    }

    public function handle(MultiSourceMarketService $market, PredictionService $prediction)
    {
        try {
            // 500 klines is sufficient for all indicators
            $klines = $market->getKlines($this->symbol, $this->interval, 500);
            
            // This internally caches the signal and prevents it from being recalculated
            $prediction->getScalpingSignal($klines, $this->symbol, $this->interval, [], 50, 1.0, false, $this->steps);
            
            Log::info("GeneratePredictionJob completed for {$this->symbol} ({$this->horizon})");
        } catch (\Exception $e) {
            Log::error("GeneratePredictionJob failed for {$this->symbol}: " . $e->getMessage());
        }
    }
}
