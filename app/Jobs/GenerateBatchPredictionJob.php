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

class GenerateBatchPredictionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $symbols;
    public $horizon;
    public $interval;
    public $steps;

    public $timeout = 300; // 5 minutes timeout for batch

    public function __construct(array $symbols, string $horizon, string $interval, int $steps)
    {
        $this->symbols = $symbols;
        $this->horizon = $horizon;
        $this->interval = $interval;
        $this->steps = $steps;
    }

    public function handle(MultiSourceMarketService $market, PredictionService $prediction)
    {
        try {
            ini_set('memory_limit', '512M');
            
            $batchKlines = [];
            foreach ($this->symbols as $symbol) {
                $batchKlines[strtoupper($symbol)] = [
                    'klines' => $market->getKlines($symbol, $this->interval, 1000)
                ];
            }

            // This internally caches the signals for batch and individual cache keys
            $prediction->getBatchSignals($batchKlines, $this->interval, $this->steps);
            
            Log::info("GenerateBatchPredictionJob completed for " . count($this->symbols) . " symbols ({$this->horizon})");
        } catch (\Exception $e) {
            Log::error("GenerateBatchPredictionJob failed: " . $e->getMessage());
        }
    }
}
