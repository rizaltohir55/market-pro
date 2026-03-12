<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PredictionService
{
    private TechnicalAnalysisService $ta;
    private EconomicCalendarService $calendar;
    private MachineLearningService $ml;

    public function __construct(TechnicalAnalysisService $ta, EconomicCalendarService $calendar, MachineLearningService $ml)
    {
        $this->ta = $ta;
        $this->calendar = $calendar;
        $this->ml = $ml;
    }

    public function getCacheKey(string $symbol, string $interval, int $fearGreed, float $lsRatio, bool $isTrending, int $forecastSteps): string
    {
        $lsStr = number_format($lsRatio, 1, '.', '');
        $contextHash = md5($interval . '_' . $fearGreed . '_' . $lsStr . '_' . ($isTrending ? 'T' : 'R') . '_' . $forecastSteps);
        return "prediction_v4_" . strtoupper($symbol) . "_" . $contextHash;
    }

    public function getScalpingSignal(array $klines, string $symbol = 'UNKNOWN', string $interval = '15m', array $htfKlines = [], int $fearGreed = 50, float $lsRatio = 1.0, bool $isTrending = false, int $forecastSteps = 5): array
    {
        $symbol = strtoupper($symbol);
        $cacheKey = $this->getCacheKey($symbol, $interval, $fearGreed, $lsRatio, $isTrending, $forecastSteps);
        
        return Cache::remember($cacheKey, 300, function() use ($klines, $symbol, $interval, $htfKlines, $fearGreed, $lsRatio, $isTrending, $forecastSteps) {
            return $this->calculateSignal($klines, $symbol, $interval, $htfKlines, $fearGreed, $lsRatio, $isTrending, null, $forecastSteps);
        });
    }

    public function getBatchSignals(array $symbolData, string $interval = '15m', int $forecastSteps = 5): array
    {
        $batchResults = [];
        $mlBatchInput = [];
        
        foreach ($symbolData as $symbol => $data) {
            $symbol = strtoupper($symbol);
            $cacheKey = $this->getCacheKey($symbol, $interval, 50, 1.0, false, $forecastSteps);
            
            $cached = Cache::get($cacheKey);
            if ($cached) {
                $batchResults[$symbol] = $cached;
                continue;
            }

            if (!isset($data['klines']) || count($data['klines']) < 50) {
                $batchResults[$symbol] = $this->getDefaultResponse('Insufficient data for batch analysis');
                continue;
            }

            $mlBatchInput[$symbol] = $data['klines'];
        }

        if (empty($mlBatchInput)) {
            return $batchResults;
        }

        $mlResults = $this->ml->predictBatchXGBoost($mlBatchInput, $forecastSteps);

        foreach ($mlBatchInput as $symbol => $klines) {
            $htfKlines = $symbolData[$symbol]['htfKlines'] ?? [];
            $mlResult = $mlResults[$symbol] ?? ['error' => 'ML result missing or failed'];
            
            $signal = $this->calculateSignal($klines, $symbol, $interval, $htfKlines, 50, 1.0, false, $mlResult, $forecastSteps);
            
            $cacheKey = $this->getCacheKey($symbol, $interval, 50, 1.0, false, $forecastSteps);
            Cache::put($cacheKey, $signal, 300);
            $batchResults[$symbol] = $signal;
        }

        return $batchResults;
    }

    private function getDefaultResponse(string $reason = 'Insufficient data'): array
    {
        return [
            'signal' => 'NEUTRAL',
            'confidence' => 0,
            'indicators' => [],
            'summary' => $reason,
            'categories' => [],
            'price' => 0,
            'tp' => 0,
            'sl' => 0,
            'target' => 0,
            'stop_loss' => 0,
            'trailing_stop' => 0,
            'risk_reward' => 0,
            'trend_strength' => 'UNKNOWN',
            'market_regime' => 'UNKNOWN',
            'ml_forecast' => ['linear_regression' => [], 'monte_carlo' => []]
        ];
    }

    private function calculateSignal(array $klines, string $symbol, string $interval, array $htfKlines, int $fearGreed, float $lsRatio, bool $isTrending, ?array $precomputedML = null, int $forecastSteps = 5): array
    {
        if (count($klines) < 50) { 
            Log::warning("PredictionService: Insufficient klines for {$symbol}/{$interval} (count: " . count($klines) . ")");
            return $this->getDefaultResponse();
        }

        $closes = array_column($klines, 'close');
        $currentPrice = end($closes);

        $kalmanPrices = $this->ta->calculateKalmanFilter($closes);
        $denoisedPrice = end($kalmanPrices) ?: $currentPrice;

        $superTrend = $this->ta->calculateSuperTrend($klines);
        $currSt = !empty($superTrend) ? end($superTrend) : ['trend' => 0, 'value' => 0];

        $adxData = $this->ta->calculateADX($klines);
        $lastAdx = !empty($adxData['adx']) ? end($adxData['adx']) : 20.0;
        
        $trendStrength = $this->determineTrendStrength($lastAdx);
        $isStrongTrend = $lastAdx > 30 || $currSt['trend'] != 0; 
        
        $categories = $this->initializeCategories($lastAdx, $currSt);
        $indicatorsList = [];

        $this->evaluateTrendIndicators($categories, $indicatorsList, $klines, $closes, $currentPrice, $adxData, $lastAdx);
        $this->evaluateMomentumIndicators($categories, $indicatorsList, $klines, $closes, $isStrongTrend);
        $this->evaluatePriceActionIndicators($categories, $indicatorsList, $klines);
        $this->evaluateMacdIndicators($categories, $indicatorsList, $closes);
        $this->evaluateVolumeIndicators($categories, $indicatorsList, $klines, $closes, $currentPrice);
        
        $bb = $this->ta->calculateBollingerBands($closes);
        $fibPivots = $this->ta->calculateFibonacciPivots($klines);
        $this->evaluateStructureIndicators($categories, $indicatorsList, $currentPrice, $bb, $fibPivots);

        $fractalPenalty = 1.0;
        $hurst = 0.5;
        $this->evaluateAdvancedIndicators($categories, $indicatorsList, $closes, $currentPrice, $denoisedPrice, $currSt, $fractalPenalty, $hurst);

        // ML Predictions
        $lr = ['slope' => 0, 'intercept' => 0, 'r_squared' => 0]; 
        $mc = ['median' => $currentPrice];
        $mlResult = $precomputedML ?? $this->ml->predictXGBoost($klines, $forecastSteps);
        
        $machineLearningConfidence = $mlResult['confidence_score'] ?? 50;
        $mlUpperBounds = $mlResult['upper_bounds'] ?? [];
        $mlLowerBounds = $mlResult['lower_bounds'] ?? [];
        $mlExplainability = $mlResult['explainability'] ?? [];
        
        $this->evaluateMachineLearning($categories, $indicatorsList, $closes, $currentPrice, $forecastSteps, $mlResult, $lr, $mc);

        $totalBuy = 0.0; $totalSell = 0.0;
        $categoryScores = [];
        $this->computeCategoryScores($categories, $totalBuy, $totalSell, $categoryScores);

        $currSqueeze = $this->evaluateSqueezeMomentum($indicatorsList, $klines, $totalBuy, $totalSell);

        $confidence = max($totalBuy, $totalSell) * $fractalPenalty;
        $overallSignal = $this->determineBaseSignal($totalBuy, $totalSell);

        $volatilityFilter = $this->applyVolatilityFilter($indicatorsList, $klines, $confidence, $overallSignal);
        $this->applyContextualConfluenceCheck($overallSignal, $confidence, $volatilityFilter, $currSt, $lr, $currSqueeze, $denoisedPrice, $currentPrice, $totalBuy, $totalSell);

        $marketRegime = $this->determineMarketRegime($currSqueeze, $trendStrength, $bb, $currentPrice);

        $htfAligned = $this->applyHTFConfluence($indicatorsList, $htfKlines, $overallSignal, $confidence);
        $persistence = $this->handleSignalPersistence($symbol, $interval, $overallSignal);
        $this->applyNewsImpactFilter($indicatorsList, $overallSignal, $confidence);

        $isBuy = str_contains($overallSignal, 'BUY');
        
        $k_scaling = max(1.0, $forecastSteps / 5);
        $levels = $this->ta->calculateDynamicTPSL($klines, $isBuy ? 'BUY' : 'SELL', 1.5 * $k_scaling, 1.0 * $k_scaling);
        $trailingStop = $this->ta->calculateTrailingStopATR($klines, $isBuy ? 'BUY' : 'SELL', 2.0);
        
        $tp = $levels['tp'] ?? $currentPrice * 1.02;
        $sl = $levels['sl'] ?? $currentPrice * 0.98;
        
        $riskReward = $this->calculateRiskReward($currentPrice, $tp, $sl);
        $this->applyRiskRewardFilter($indicatorsList, $overallSignal, $confidence, $riskReward);

        // Advanced forecast corridor based on ML bounds if available
        $forecastPath = [];
        if (!empty($mlUpperBounds) && !empty($mlLowerBounds) && !empty($mlResult['forecast'])) {
            for ($i=0; $i < count($mlResult['forecast']); $i++) {
                $forecastPath[] = [
                    'price' => $mlResult['forecast'][$i],
                    'upper' => $mlUpperBounds[$i],
                    'lower' => $mlLowerBounds[$i]
                ];
            }
        } else {
            $forecastPath = $this->ta->calculateForecastCorridor(
                $currentPrice, 
                $mlResult['forecast'] ?? ($lr['slope'] != 0 ? array_map(fn($i) => $currentPrice + ($lr['slope'] * $i), range(1, $forecastSteps)) : []),
                $levels['atr'] ?? ($currentPrice * 0.01),
                $isBuy ? 'BUY' : 'SELL'
            );
        }

        return [
            'signal' => $overallSignal,
            'confidence' => round($confidence, 1),
            'ml_confidence' => $machineLearningConfidence,
            'ml_explainability' => $mlExplainability,
            'buy_score' => round($totalBuy, 1),
            'sell_score' => round($totalSell, 1),
            'indicators' => $indicatorsList,
            'categories' => $categoryScores,
            'price' => $currentPrice,
            'tp' => $tp,
            'sl' => $sl,
            'target' => $tp, 
            'stop_loss' => $sl, 
            'trailing_stop' => round($trailingStop, 2),
            'persistence' => $persistence,
            'htf_aligned' => $htfAligned,
            'risk_reward' => round($riskReward, 2),
            'trend_strength' => $trendStrength,
            'market_regime' => $marketRegime,
            'ml_forecast' => [
                'linear_regression' => $lr,
                'monte_carlo' => $mc
            ],
            'hurst' => $hurst,
            'forecast_path' => $forecastPath,
            'summary' => "Engine V4.2 (Multi-Step): {$overallSignal}. RR: " . round($riskReward, 2) . ". Consistency: {$persistence} bars. Market is {$marketRegime}. R2: " . round($mlResult['r_squared'] ?? 0, 2) . "."
        ];
    }
    
    private function determineTrendStrength(float $lastAdx): string 
    {
        if ($lastAdx < 20) return 'WEAK';
        if ($lastAdx < 40) return 'MODERATE';
        if ($lastAdx < 60) return 'STRONG';
        return 'VERY STRONG';
    }

    private function initializeCategories(float $lastAdx, array $currSt): array 
    {
        $adxWeightBoost = ($lastAdx > 35) ? 10 : (($lastAdx > 25) ? 5 : 0);

        if ($lastAdx < 25 && $currSt['trend'] == 0) {
            return [
                'Trend' => ['buy' => 0, 'sell' => 0, 'weight' => 10],
                'Momentum' => ['buy' => 0, 'sell' => 0, 'weight' => 30],
                'MACD' => ['buy' => 0, 'sell' => 0, 'weight' => 10],
                'Volume' => ['buy' => 0, 'sell' => 0, 'weight' => 15],
                'Structure' => ['buy' => 0, 'sell' => 0, 'weight' => 20],
                'Price Action' => ['buy' => 0, 'sell' => 0, 'weight' => 15],
                'Advanced' => ['buy' => 0, 'sell' => 0, 'weight' => 25],
            ];
        }

        return [
            'Trend' => ['buy' => 0, 'sell' => 0, 'weight' => 40 + $adxWeightBoost],
            'Momentum' => ['buy' => 0, 'sell' => 0, 'weight' => 10],
            'MACD' => ['buy' => 0, 'sell' => 0, 'weight' => 15],
            'Volume' => ['buy' => 0, 'sell' => 0, 'weight' => 15],
            'Structure' => ['buy' => 0, 'sell' => 0, 'weight' => 10],
            'Price Action' => ['buy' => 0, 'sell' => 0, 'weight' => 10],
            'Advanced' => ['buy' => 0, 'sell' => 0, 'weight' => 25],
        ];
    }

    private function evaluateTrendIndicators(array &$categories, array &$indicatorsList, array $klines, array $closes, float $currentPrice, array $adxData, float $lastAdx): void
    {
        $wEmaShort = $categories['Trend']['weight'] * 0.3;
        $ema9Arr = $this->ta->calculateEMA($closes, 9);
        $ema9 = end($ema9Arr) ?: $currentPrice;
        $ema21Arr = $this->ta->calculateEMA($closes, 21);
        $ema21 = end($ema21Arr) ?: $currentPrice;
        
        if ($ema9 > $ema21) { $categories['Trend']['buy'] += $wEmaShort; $sig = 'BUY'; }
        else { $categories['Trend']['sell'] += $wEmaShort; $sig = 'SELL'; }
        $indicatorsList[] = ['name' => 'EMA 9/21', 'value' => round($ema9,2).'/'.round($ema21,2), 'signal' => $sig];

        $wEmaLong = $categories['Trend']['weight'] * 0.3;
        $ema50Arr = $this->ta->calculateEMA($closes, 50);
        $ema50 = end($ema50Arr) ?: $currentPrice;
        $ema200Arr = $this->ta->calculateEMA($closes, 200);
        $ema200 = end($ema200Arr) ?: $currentPrice;
        
        if ($ema50 > $ema200) { $categories['Trend']['buy'] += $wEmaLong; $sig = 'BUY'; }
        else { $categories['Trend']['sell'] += $wEmaLong; $sig = 'SELL'; }
        $indicatorsList[] = ['name' => 'EMA 50/200', 'value' => round($ema50,2).'/'.round($ema200,2), 'signal' => $sig];

        $wIchi = $categories['Trend']['weight'] * 0.2;
        $ichi = $this->ta->calculateIchimoku($klines);
        if (!empty($ichi['senkou_a'])) {
            $sa = end($ichi['senkou_a']);
            $sb = end($ichi['senkou_b']);
            if ($currentPrice > max($sa, $sb)) { $categories['Trend']['buy'] += $wIchi; $sig = 'BUY'; }
            elseif ($currentPrice < min($sa, $sb)) { $categories['Trend']['sell'] += $wIchi; $sig = 'SELL'; }
            else { $sig = 'NEUTRAL'; }
            $indicatorsList[] = ['name' => 'Ichimoku Cloud', 'value' => 'A:'.round($sa,2).' B:'.round($sb,2), 'signal' => $sig];
        }

        $wSar = $categories['Trend']['weight'] * 0.1;
        $sarArr = $this->ta->calculateParabolicSAR($klines);
        if (!empty($sarArr)) {
            $lastSar = end($sarArr);
            if ($currentPrice > $lastSar) { $categories['Trend']['buy'] += $wSar; $sig = 'BUY'; }
            else { $categories['Trend']['sell'] += $wSar; $sig = 'SELL'; }
            $indicatorsList[] = ['name' => 'Parabolic SAR', 'value' => round($lastSar, 2), 'signal' => $sig];
        }

        if (!empty($adxData['adx'])) {
            $lastPlusDI = end($adxData['plus_di']);
            $lastMinusDI = end($adxData['minus_di']);
            
            $wAdx = $categories['Trend']['weight'] * 0.1;
            if ($lastAdx > 25) {
                if ($lastPlusDI > $lastMinusDI) { $categories['Trend']['buy'] += $wAdx; $sig = 'BUY'; }
                else { $categories['Trend']['sell'] += $wAdx; $sig = 'SELL'; }
            } else { $sig = 'NEUTRAL'; }
            $indicatorsList[] = ['name' => 'ADX', 'value' => round($lastAdx,1), 'signal' => $sig];
        }
    }

    private function evaluateMomentumIndicators(array &$categories, array &$indicatorsList, array $klines, array $closes, bool $isStrongTrend): void
    {
        $weightMomentum = $categories['Momentum']['weight'];
        
        $wRsi = $weightMomentum * 0.3;
        $rsiArr = $this->ta->calculateRSI($closes);
        $rsi = end($rsiArr) ?: 50;
        
        if ($isStrongTrend) {
            if ($rsi > 60) { $categories['Momentum']['buy'] += $wRsi; $sig = 'BUY (Mom)'; }
            elseif ($rsi < 40) { $categories['Momentum']['sell'] += $wRsi; $sig = 'SELL (Mom)'; }
            else { $sig = 'NEUTRAL'; }
        } else {
            if ($rsi < 30) { $categories['Momentum']['buy'] += $wRsi; $sig = 'BUY'; }
            elseif ($rsi > 70) { $categories['Momentum']['sell'] += $wRsi; $sig = 'SELL'; }
            else { $sig = 'NEUTRAL'; }
        }
        $indicatorsList[] = ['name' => 'RSI', 'value' => round($rsi, 1), 'signal' => $sig];

        $wDiv = $weightMomentum * 0.4;
        $div = $this->ta->detectDivergence($closes, $rsiArr);
        if ($div === 'BULLISH_DIVERGENCE') {
            $categories['Momentum']['buy'] += $wDiv;
            $indicatorsList[] = ['name' => 'RSI Divergence', 'value' => 'Bullish', 'signal' => 'STRONG BUY'];
        } elseif ($div === 'BEARISH_DIVERGENCE') {
            $categories['Momentum']['sell'] += $wDiv;
            $indicatorsList[] = ['name' => 'RSI Divergence', 'value' => 'Bearish', 'signal' => 'STRONG SELL'];
        }

        $wStoch = $weightMomentum * 0.3;
        $stoch = $this->ta->calculateStochastic($klines);
        if (!empty($stoch['k'])) {
            $k = end($stoch['k']); $d = end($stoch['d']);
            if ($isStrongTrend) {
                if ($k > 50 && $k > $d) { $categories['Momentum']['buy'] += $wStoch; $sig = 'BUY (Mom)'; }
                elseif ($k < 50 && $k < $d) { $categories['Momentum']['sell'] += $wStoch; $sig = 'SELL (Mom)'; }
                else { $sig = 'NEUTRAL'; }
            } else {
                if ($k < 20 && $k > $d) { $categories['Momentum']['buy'] += $wStoch; $sig = 'BUY'; }
                elseif ($k > 80 && $k < $d) { $categories['Momentum']['sell'] += $wStoch; $sig = 'SELL'; }
                else { $sig = 'NEUTRAL'; }
            }
            $indicatorsList[] = ['name' => 'Stochastic', 'value' => round($k,1).'/'.round($d,1), 'signal' => $sig];
        }
    }

    private function evaluatePriceActionIndicators(array &$categories, array &$indicatorsList, array $klines): void
    {
        $weightPriceAction = $categories['Price Action']['weight'];
        $patterns = $this->ta->detectCandlestickPatterns($klines);
        foreach ($patterns as $pattern) {
            if ($pattern === 'BULLISH_ENGULFING') {
                $categories['Price Action']['buy'] += ($weightPriceAction * 0.6);
                $indicatorsList[] = ['name' => 'Price Action', 'value' => 'Bullish Engulfing', 'signal' => 'BUY'];
            } elseif ($pattern === 'BEARISH_ENGULFING') {
                $categories['Price Action']['sell'] += ($weightPriceAction * 0.6);
                $indicatorsList[] = ['name' => 'Price Action', 'value' => 'Bearish Engulfing', 'signal' => 'SELL'];
            } elseif ($pattern === 'HAMMER') {
                $categories['Price Action']['buy'] += ($weightPriceAction * 0.4);
                $indicatorsList[] = ['name' => 'Price Action', 'value' => 'Hammer (Bullish)', 'signal' => 'BUY'];
            } elseif ($pattern === 'SHOOTING_STAR') {
                $categories['Price Action']['sell'] += ($weightPriceAction * 0.4);
                $indicatorsList[] = ['name' => 'Price Action', 'value' => 'Shooting Star', 'signal' => 'SELL'];
            }
        }
    }

    private function evaluateMacdIndicators(array &$categories, array &$indicatorsList, array $closes): void
    {
        $wMacd = $categories['MACD']['weight'];
        $macdData = $this->ta->calculateMACD($closes);
        if (!empty($macdData['histogram'])) {
            $macdLine = end($macdData['macd']);
            $sigLine = end($macdData['signal']);
            $hist = end($macdData['histogram']);
            $prevHist = $macdData['histogram'][count($macdData['histogram'])-2] ?? 0;

            if ($macdLine > $sigLine && $hist > $prevHist) { $categories['MACD']['buy'] += $wMacd; $sig = 'BUY'; }
            elseif ($macdLine < $sigLine && $hist < $prevHist) { $categories['MACD']['sell'] += $wMacd; $sig = 'SELL'; }
            elseif ($macdLine > $sigLine) { $categories['MACD']['buy'] += ($wMacd/2); $sig = 'BULLISH'; }
            else { $categories['MACD']['sell'] += ($wMacd/2); $sig = 'BEARISH'; }
            $indicatorsList[] = ['name' => 'MACD', 'value' => round($macdLine, 3), 'signal' => $sig];
        }
    }

    private function evaluateVolumeIndicators(array &$categories, array &$indicatorsList, array $klines, array $closes, float $currentPrice): void
    {
        $weightVolume = $categories['Volume']['weight'];
        
        $wObv = $weightVolume * 0.25;
        $obv = $this->ta->calculateOBV($klines);
        $obvM = end($obv); $prevObv = $obv[count($obv)-2] ?? $obvM;
        if ($obvM > $prevObv && $closes[count($closes)-1] > $closes[count($closes)-2]) { $categories['Volume']['buy'] += $wObv; $sig = 'BUY'; }
        elseif ($obvM < $prevObv && $closes[count($closes)-1] < $closes[count($closes)-2]) { $categories['Volume']['sell'] += $wObv; $sig = 'SELL'; }
        else { $sig = 'NEUTRAL'; }
        $indicatorsList[] = ['name' => 'OBV', 'value' => 'Trend', 'signal' => $sig];

        $wVwap = $weightVolume * 0.25;
        $vwapArr = $this->ta->calculateVWAP($klines);
        $vwap = end($vwapArr) ?: $currentPrice;
        if ($currentPrice > $vwap) { $categories['Volume']['buy'] += $wVwap; $sig = 'BUY'; }
        else { $categories['Volume']['sell'] += $wVwap; $sig = 'SELL'; }
        $indicatorsList[] = ['name' => 'VWAP', 'value' => round($vwap, 2), 'signal' => $sig];

        $wCmf = $weightVolume * 0.25;
        $cmfArr = $this->ta->calculateCMF($klines);
        $cmf = end($cmfArr) ?: 0;
        if ($cmf > 0.1) { $categories['Volume']['buy'] += $wCmf; $sig = 'BUY'; }
        elseif ($cmf < -0.1) { $categories['Volume']['sell'] += $wCmf; $sig = 'SELL'; }
        else { $sig = 'NEUTRAL'; }
        $indicatorsList[] = ['name' => 'CMF', 'value' => round($cmf, 2), 'signal' => $sig];

        $wSpike = $weightVolume * 0.25;
        $volSpike = $this->ta->detectVolumeSpike($klines);
        if ($volSpike === 'BULLISH_SPIKE') { 
            $categories['Volume']['buy'] += $wSpike; 
            $indicatorsList[] = ['name' => 'Volume Anomaly', 'value' => 'Bullish Climax', 'signal' => 'STRONG BUY'];
        } elseif ($volSpike === 'BEARISH_SPIKE') { 
            $categories['Volume']['sell'] += $wSpike; 
            $indicatorsList[] = ['name' => 'Volume Anomaly', 'value' => 'Bearish Dump', 'signal' => 'STRONG SELL'];
        }
    }

    private function evaluateStructureIndicators(array &$categories, array &$indicatorsList, float $currentPrice, array $bb, array $fibPivots): void
    {
        $weightStructure = $categories['Structure']['weight'];
        
        $wBB = $weightStructure * 0.50;
        if (!empty($bb['upper'])) {
            $upperArr = $bb['upper'];
            $upper = end($upperArr);
            $lowerArr = $bb['lower'];
            $lower = end($lowerArr);
            if ($currentPrice <= $lower * 1.005) { $categories['Structure']['buy'] += $wBB; $sig = 'BUY'; }
            elseif ($currentPrice >= $upper * 0.995) { $categories['Structure']['sell'] += $wBB; $sig = 'SELL'; }
            else { $sig = 'NEUTRAL'; }
            $indicatorsList[] = ['name' => 'Bollinger Bands', 'value' => 'StdDev 2.0', 'signal' => $sig];
        }
        
        $wFib = $weightStructure * 0.50;
        if (!empty($fibPivots)) {
            $distToS1 = abs($currentPrice - $fibPivots['s1']);
            $distToR1 = abs($currentPrice - $fibPivots['r1']);
            if ($distToS1 < ($currentPrice * 0.005)) { $categories['Structure']['buy'] += $wFib; $sig = 'BUY (Fib S1)'; }
            elseif ($distToR1 < ($currentPrice * 0.005)) { $categories['Structure']['sell'] += $wFib; $sig = 'SELL (Fib R1)'; }
            else { $sig = 'NEUTRAL'; }
            $indicatorsList[] = ['name' => 'Fibonacci Pivots', 'value' => 'S1/R1 Zone', 'signal' => $sig];
        }
    }

    private function evaluateAdvancedIndicators(array &$categories, array &$indicatorsList, array $closes, float $currentPrice, float $denoisedPrice, array $currSt, float &$fractalPenalty, float &$hurst): void
    {
        $hurst = $this->ta->calculateHurstExponent($closes);
        $fractal = $this->ta->calculateFractalDimension($closes);
        
        $hurstSig = 'NEUTRAL';
        if ($hurst > 0.6) { $categories['Advanced']['buy'] += 8; $hurstSig = 'STRONG TREND'; }
        elseif ($hurst < 0.4) { $categories['Advanced']['sell'] += 8; $hurstSig = 'MEAN REVERTING'; }
        $indicatorsList[] = ['name' => 'Hurst Exponent', 'value' => round($hurst, 2), 'signal' => $hurstSig];

        $fractalSig = 'NEUTRAL';
        if ($fractal > 1.65) { 
            $fractalSig = 'COMPLEX/VOLATILE'; 
            $fractalPenalty = 0.7; 
        } elseif ($fractal < 1.4) { 
            $fractalSig = 'EFFICIENT'; 
            $fractalPenalty = 1.1; 
        }
        $indicatorsList[] = ['name' => 'Fractal Dim', 'value' => round($fractal, 2), 'signal' => $fractalSig];

        $stSig = 'NEUTRAL';
        if ($currSt['trend'] == 1) { $categories['Advanced']['buy'] += 12; $stSig = 'BULLISH'; }
        elseif ($currSt['trend'] == -1) { $categories['Advanced']['sell'] += 12; $stSig = 'BEARISH'; }
        $indicatorsList[] = ['name' => 'SuperTrend', 'value' => round($currSt['value'], 2), 'signal' => $stSig];

        $kalmanSig = 'NEUTRAL';
        if ($denoisedPrice > $currentPrice * 1.001) { $categories['Trend']['buy'] += 6; $kalmanSig = 'UPWARD'; }
        elseif ($denoisedPrice < $currentPrice * 0.999) { $categories['Trend']['sell'] += 6; $kalmanSig = 'DOWNWARD'; }
        $indicatorsList[] = ['name' => 'Kalman Denoise', 'value' => 'Smooth', 'signal' => $kalmanSig];
    }

    private function evaluateMachineLearning(array &$categories, array &$indicatorsList, array $closes, float $currentPrice, int $forecastSteps, array $mlResult, array &$lr, array &$mc): void
    {
        $mlSig = 'NEUTRAL';
        
        if (!isset($mlResult['error'])) {
            $forecast = $mlResult['forecast'];
            $r2 = $mlResult['r_squared'] ?? 0;
            $conf = $mlResult['confidence_score'] ?? 50;
            $lastPred = !empty($forecast) ? end($forecast) : $currentPrice;
            
            // Adjust weights based on ML confidence
            $weightMult = ($conf > 80) ? 2 : (($conf > 60) ? 1.5 : 1);
            
            if ($lastPred > $currentPrice && $r2 > 0.3) { 
                $categories['Advanced']['buy'] += (15 * $weightMult); 
                $mlSig = 'BULLISH (XGB)'; 
            } elseif ($lastPred < $currentPrice && $r2 > 0.3) { 
                $categories['Advanced']['sell'] += (15 * $weightMult); 
                $mlSig = 'BEARISH (XGB)'; 
            }
            $indicatorsList[] = ['name' => 'XGBoost ML', 'value' => "Conf: {$conf}%", 'signal' => $mlSig];
            $lr = $this->ml->predictLinearRegression($closes, $forecastSteps);
        } else {
            $lr = $this->ml->predictLinearRegression($closes, $forecastSteps);
            if ($lr['slope'] > 0 && $lr['r_squared'] > 0.7) { $categories['Advanced']['buy'] += 10; $mlSig = 'BULLISH (LR)'; }
            elseif ($lr['slope'] < 0 && $lr['r_squared'] > 0.7) { $categories['Advanced']['sell'] += 10; $mlSig = 'BEARISH (LR)'; }
            $indicatorsList[] = ['name' => 'Legacy ML', 'value' => 'Fallback', 'signal' => $mlSig];
        }

        $mc = $this->ml->runMonteCarlo($closes, 20, 1000);
        $mcSig = 'NEUTRAL';
        if ($mc['median'] > $currentPrice) { $categories['Advanced']['buy'] += 5; $mcSig = 'PROB BULLISH'; }
        else { $categories['Advanced']['sell'] += 5; $mcSig = 'PROB BEARISH'; }
        $indicatorsList[] = ['name' => 'Monte Carlo', 'value' => '$'.round($mc['median'], 2), 'signal' => $mcSig];
    }

    private function computeCategoryScores(array $categories, float &$totalBuy, float &$totalSell, array &$categoryScores): void
    {
        foreach ($categories as $cat => $sc) {
            $totalBuy += $sc['buy'];
            $totalSell += $sc['sell'];
            $maxPossible = $sc['weight'];
            $catBuyPct = $maxPossible > 0 ? min(100, ($sc['buy'] / $maxPossible) * 100) : 0;
            $catSellPct = $maxPossible > 0 ? min(100, ($sc['sell'] / $maxPossible) * 100) : 0;
            if ($catBuyPct > $catSellPct && $catBuyPct > 40) $catSig = 'BUY';
            elseif ($catSellPct > $catBuyPct && $catSellPct > 40) $catSig = 'SELL';
            else $catSig = 'NEUTRAL';

            $categoryScores[$cat] = [
                'buy' => round($catBuyPct, 1),
                'sell' => round($catSellPct, 1),
                'signal' => $catSig
            ];
        }
    }

    private function evaluateSqueezeMomentum(array &$indicatorsList, array $klines, float &$totalBuy, float &$totalSell): array
    {
        $squeezeArr = $this->ta->calculateSqueezeMomentum($klines);
        $currSqueeze = !empty($squeezeArr) ? end($squeezeArr) : ['is_squeeze' => false, 'momentum' => 0];
        $squeezeSig = 'NEUTRAL';
        if ($currSqueeze['is_squeeze']) {
            $squeezeSig = 'SQUEEZE (Low Vol)';
            $totalBuy *= 0.8; $totalSell *= 0.8; 
        } else {
            if ($currSqueeze['momentum'] > 0) { $totalBuy += 10; $squeezeSig = 'BULLISH RELEASE'; }
            elseif ($currSqueeze['momentum'] < 0) { $totalSell += 10; $squeezeSig = 'BEARISH RELEASE'; }
        }
        $indicatorsList[] = ['name' => 'Squeeze Momentum', 'value' => $currSqueeze['is_squeeze'] ? 'ON' : 'OFF', 'signal' => $squeezeSig];
        return $currSqueeze;
    }

    private function determineBaseSignal(float $totalBuy, float $totalSell): string
    {
        if ($totalBuy > $totalSell && $totalBuy >= 40) {
            return ($totalBuy >= 75) ? 'ULTRA BUY' : (($totalBuy >= 60) ? 'STRONG BUY' : 'BUY');
        } elseif ($totalSell > $totalBuy && $totalSell >= 40) {
            return ($totalSell >= 75) ? 'ULTRA SELL' : (($totalSell >= 60) ? 'STRONG SELL' : 'SELL');
        }
        return 'NEUTRAL';
    }

    private function applyVolatilityFilter(array &$indicatorsList, array $klines, float &$confidence, string &$overallSignal): bool
    {
        $atrHist = $this->ta->calculateATR($klines, 14);
        if (!empty($atrHist)) {
            $lastAtr = end($atrHist);
            $slicedAtr = array_slice($atrHist, -50);
            $countAtr = count($slicedAtr);
            $meanAtr = $countAtr > 0 ? array_sum($slicedAtr) / $countAtr : 0;
            if ($lastAtr > $meanAtr * 2.5) {
                $confidence *= 0.5;
                $overallSignal .= " (High Vol Risk)";
                $indicatorsList[] = ['name' => 'Volatility Filter', 'value' => 'Extreme', 'signal' => 'DANGER'];
                return true;
            }
        }
        return false;
    }

    private function applyContextualConfluenceCheck(string &$overallSignal, float &$confidence, bool $volatilityFilter, array $currSt, array $lr, array $currSqueeze, float $denoisedPrice, float $currentPrice, float $totalBuy, float $totalSell): void
    {
        if ($overallSignal !== 'NEUTRAL' && !$volatilityFilter) {
            $isBuy = str_contains($overallSignal, 'BUY');
            
            if (($isBuy && $currSt['trend'] == -1) || (!$isBuy && $currSt['trend'] == 1)) {
                $confidence *= 0.6; 
                $overallSignal .= " (C-Trend)";
            }
            
            if (($isBuy && $lr['slope'] < 0) || (!$isBuy && $lr['slope'] > 0)) {
                $confidence *= 0.7;
                $overallSignal .= " (ML Div)";
            }
            
            $hasSqueezeConfluence = !$currSqueeze['is_squeeze'] && (($isBuy && $currSqueeze['momentum'] > 0) || (!$isBuy && $currSqueeze['momentum'] < 0));
            
            if ($isBuy && $currSt['trend'] == 1 && $denoisedPrice > $currentPrice && $totalBuy > 80 && $hasSqueezeConfluence) {
                $overallSignal = 'ULTRA BUY';
                $confidence = min(100, $confidence + 15);
            } elseif (!$isBuy && $currSt['trend'] == -1 && $denoisedPrice < $currentPrice && $totalSell > 80 && $hasSqueezeConfluence) {
                $overallSignal = 'ULTRA SELL';
                $confidence = min(100, $confidence + 15);
            }
        }
    }

    private function determineMarketRegime(array $currSqueeze, string $trendStrength, array $bb, float $currentPrice): string
    {
        $marketRegime = $currSqueeze['is_squeeze'] ? 'SQUEEZING' : 'TRENDING';
        if ($trendStrength === 'WEAK' && !$currSqueeze['is_squeeze']) {
            $marketRegime = 'RANGING';
        } elseif (isset($bb['upper'], $bb['lower'])) {
            $upperArr = $bb['upper'];
            $lowerArr = $bb['lower'];
            if ((end($upperArr) - end($lowerArr)) / max($currentPrice, 1) > 0.05) {
                $marketRegime = 'VOLATILE';
            }
        }
        return $marketRegime;
    }

    private function applyHTFConfluence(array &$indicatorsList, array $htfKlines, string &$overallSignal, float &$confidence): bool
    {
        $htfAligned = false;
        if (!empty($htfKlines) && count($htfKlines) >= 200) {
            $htfCloses = array_column($htfKlines, 'close');
            $htfEma50Arr = $this->ta->calculateEMA($htfCloses, 50);
            $htfEma50 = end($htfEma50Arr);
            $htfEma200Arr = $this->ta->calculateEMA($htfCloses, 200);
            $htfEma200 = end($htfEma200Arr);

            if ($htfEma50 > $htfEma200) {
                if (in_array($overallSignal, ['SELL', 'STRONG SELL', 'ULTRA SELL'])) {
                    $confidence *= 0.5;
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Against HTF Bull', 'signal' => 'DANGER'];
                } elseif (str_contains($overallSignal, 'BUY')) {
                    $confidence = min(100, $confidence + 15);
                    $htfAligned = true;
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Bullish Align', 'signal' => 'BUY'];
                }
            } elseif ($htfEma50 < $htfEma200) {
                if (in_array($overallSignal, ['BUY', 'STRONG BUY', 'ULTRA BUY'])) {
                    $confidence *= 0.5;
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Against HTF Bear', 'signal' => 'DANGER'];
                } elseif (str_contains($overallSignal, 'SELL')) {
                    $confidence = min(100, $confidence + 15);
                    $htfAligned = true;
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Bearish Align', 'signal' => 'SELL'];
                }
            }
        }
        return $htfAligned;
    }

    private function handleSignalPersistence(string $symbol, string $interval, string $overallSignal): int
    {
        $prevSignal = Cache::get("last_sig_{$symbol}_{$interval}");
        $persistence = (int) Cache::get("sig_persist_{$symbol}_{$interval}", 0);
        
        if ($prevSignal === $overallSignal && $overallSignal !== 'NEUTRAL') {
            $persistence++;
        } else {
            $persistence = 0;
            Cache::put("last_sig_{$symbol}_{$interval}", $overallSignal, 600);
        }
        Cache::put("sig_persist_{$symbol}_{$interval}", $persistence, 600);
        return $persistence;
    }

    private function applyNewsImpactFilter(array &$indicatorsList, string &$overallSignal, float &$confidence): void
    {
        $events = $this->calendar->getEconomicCalendar();
        $now = time();
        foreach ($events as $event) {
            $timeDiff = $event['timestamp'] - $now;
            if ($timeDiff > -900 && $timeDiff < 3600 && $event['importance'] === 'high') {
                $confidence *= 0.4;
                $indicatorsList[] = ['name' => 'High Impact News', 'value' => $event['event'], 'signal' => 'CAUTION'];
                $overallSignal .= " (News Risk)";
                break; 
            }
        }
    }

    private function calculateRiskReward(float $currentPrice, float $tp, float $sl): float
    {
        $risk = abs($currentPrice - $sl);
        $reward = abs($tp - $currentPrice);
        return $risk > 0.0000001 ? $reward / $risk : 0;
    }

    private function applyRiskRewardFilter(array &$indicatorsList, string &$overallSignal, float &$confidence, float $riskReward): void
    {
        if ($overallSignal !== 'NEUTRAL' && $riskReward < 1.3 && $riskReward > 0) {
            $confidence *= 0.5;
            $overallSignal .= " (Low RR)";
            $indicatorsList[] = ['name' => 'RR Filter', 'value' => round($riskReward, 2), 'signal' => 'POOR'];
        }
    }
}