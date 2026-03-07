<?php

namespace App\Services;

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

    /**
     * Get composite scalping signal with caching and confluence.
     */
    public function getScalpingSignal(array $klines, string $symbol = 'UNKNOWN', string $interval = '15m', array $htfKlines = [], int $fearGreed = 50, float $lsRatio = 1.0, bool $isTrending = false): array
    {
        $symbol = strtoupper($symbol);
        $cacheKey = "prediction_v3_{$symbol}_{$interval}";
        
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function() use ($klines, $symbol, $interval, $htfKlines, $fearGreed, $lsRatio, $isTrending) {
            return $this->calculateSignal($klines, $symbol, $interval, $htfKlines, $fearGreed, $lsRatio, $isTrending);
        });
    }

    private function calculateSignal(array $klines, string $symbol, string $interval, array $htfKlines, int $fearGreed, float $lsRatio, bool $isTrending): array
    {
        $defaultResponse = [
            'signal' => 'NEUTRAL',
            'confidence' => 0,
            'indicators' => [],
            'summary' => 'Insufficient data',
            'categories' => [],
            'price_target_buy' => 0,
            'price_target_sell' => 0,
            'stop_loss_buy' => 0,
            'stop_loss_sell' => 0,
            'risk_reward' => 0,
            'trend_strength' => 'UNKNOWN',
            'market_regime' => 'UNKNOWN'
        ];

        if (count($klines) < 50) { 
            \Illuminate\Support\Facades\Log::warning("PredictionService: Insufficient klines for {$symbol}/{$interval} (count: " . count($klines) . ")");
            return $defaultResponse;
        }

        $closes = array_column($klines, 'close');
        $currentPrice = end($closes);

        // 0. Kalman Filter Denoising
        $kalmanPrices = $this->ta->calculateKalmanFilter($closes);
        $denoisedPrice = end($kalmanPrices);

        // 1. Calculate Market Regime (SuperTrend + ADX)
        $superTrend = $this->ta->calculateSuperTrend($klines);
        $currSt = !empty($superTrend) ? end($superTrend) : ['trend' => 0, 'value' => 0];

        $adxData = $this->ta->calculateADX($klines);
        $lastAdx = !empty($adxData['adx']) ? end($adxData['adx']) : 20.0;
        $trendStrength = 'UNKNOWN';
        $isStrongTrend = $lastAdx > 30 || $currSt['trend'] != 0; 

        if ($lastAdx < 20) $trendStrength = 'WEAK';
        elseif ($lastAdx < 40) $trendStrength = 'MODERATE';
        elseif ($lastAdx < 60) $trendStrength = 'STRONG';
        else $trendStrength = 'VERY STRONG';

        // NEW: Dynamic ADX Weighting
        $adxWeightBoost = 0;
        if ($lastAdx > 35) $adxWeightBoost = 10;
        elseif ($lastAdx > 25) $adxWeightBoost = 5;

        $regimeForWeighting = 'UNKNOWN';
        if ($lastAdx < 25 && $currSt['trend'] == 0) {
            $regimeForWeighting = 'RANGING';
            $weightTrend = 10;
            $weightMomentum = 30;
            $weightMACD = 10;
            $weightVolume = 15;
            $weightStructure = 20;
            $weightPriceAction = 15;
        } else {
            $regimeForWeighting = 'TRENDING';
            $weightTrend = 40 + $adxWeightBoost;
            $weightMomentum = 10; 
            $weightMACD = 15;
            $weightVolume = 15;
            $weightStructure = 10;
            $weightPriceAction = 10;
        }

        $categories = [
            'Trend' => ['buy' => 0, 'sell' => 0, 'weight' => $weightTrend, 'indicators' => []],
            'Momentum' => ['buy' => 0, 'sell' => 0, 'weight' => $weightMomentum, 'indicators' => []],
            'MACD' => ['buy' => 0, 'sell' => 0, 'weight' => $weightMACD, 'indicators' => []],
            'Volume' => ['buy' => 0, 'sell' => 0, 'weight' => $weightVolume, 'indicators' => []],
            'Structure' => ['buy' => 0, 'sell' => 0, 'weight' => $weightStructure, 'indicators' => []],
            'Price Action' => ['buy' => 0, 'sell' => 0, 'weight' => $weightPriceAction, 'indicators' => []],
            'Advanced' => ['buy' => 0, 'sell' => 0, 'weight' => 25, 'indicators' => []],
        ];

        $indicatorsList = [];

        // --- 1. TREND ---
        $wEmaShort = $categories['Trend']['weight'] * 0.3;
        $ema9Arr = $this->ta->calculateEMA($closes, 9);
        $ema9 = end($ema9Arr);
        $ema21Arr = $this->ta->calculateEMA($closes, 21);
        $ema21 = end($ema21Arr);
        if ($ema9 > $ema21) { $categories['Trend']['buy'] += $wEmaShort; $sig = 'BUY'; }
        else { $categories['Trend']['sell'] += $wEmaShort; $sig = 'SELL'; }
        $indicatorsList[] = ['name' => 'EMA 9/21', 'value' => round($ema9,2).'/'.round($ema21,2), 'signal' => $sig];

        $wEmaLong = $categories['Trend']['weight'] * 0.3;
        $ema50Arr = $this->ta->calculateEMA($closes, 50);
        $ema50 = end($ema50Arr);
        $ema200Arr = $this->ta->calculateEMA($closes, 200);
        $ema200 = end($ema200Arr);
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

        // --- 2. MOMENTUM ---
        $wRsi = $weightMomentum * 0.3;
        $rsiArr = $this->ta->calculateRSI($closes);
        $rsi = end($rsiArr);
        
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

        // NEW: RSI Divergence
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

        // --- 3. PRICE ACTION ---
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

        // --- 4. MACD ---
        $wMacd = $weightMACD;
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

        // --- 5. VOLUME ---
        $wObv = $weightVolume * 0.25;
        $obv = $this->ta->calculateOBV($klines);
        $obvM = end($obv); $prevObv = $obv[count($obv)-2] ?? $obvM;
        if ($obvM > $prevObv && $closes[count($closes)-1] > $closes[count($closes)-2]) { $categories['Volume']['buy'] += $wObv; $sig = 'BUY'; }
        elseif ($obvM < $prevObv && $closes[count($closes)-1] < $closes[count($closes)-2]) { $categories['Volume']['sell'] += $wObv; $sig = 'SELL'; }
        else { $sig = 'NEUTRAL'; }
        $indicatorsList[] = ['name' => 'OBV', 'value' => 'Trend', 'signal' => $sig];

        $wVwap = $weightVolume * 0.25;
        $vwapArr = $this->ta->calculateVWAP($klines);
        $vwap = end($vwapArr);
        if ($currentPrice > $vwap) { $categories['Volume']['buy'] += $wVwap; $sig = 'BUY'; }
        else { $categories['Volume']['sell'] += $wVwap; $sig = 'SELL'; }
        $indicatorsList[] = ['name' => 'VWAP', 'value' => round($vwap, 2), 'signal' => $sig];

        $wCmf = $weightVolume * 0.25;
        $cmfArr = $this->ta->calculateCMF($klines);
        $cmf = end($cmfArr);
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

        // --- 6. STRUCTURE ---
        $wBB = $weightStructure * 0.50;
        $bb = $this->ta->calculateBollingerBands($closes);
        if (!empty($bb['upper'])) {
            $upper = end($bb['upper']); $lower = end($bb['lower']);
            if ($currentPrice <= $lower * 1.005) { $categories['Structure']['buy'] += $wBB; $sig = 'BUY'; }
            elseif ($currentPrice >= $upper * 0.995) { $categories['Structure']['sell'] += $wBB; $sig = 'SELL'; }
            else { $sig = 'NEUTRAL'; }
            $indicatorsList[] = ['name' => 'Bollinger Bands', 'value' => 'StdDev 2.0', 'signal' => $sig];
        }
        
        $wFib = $weightStructure * 0.50;
        $fibPivots = $this->ta->calculateFibonacciPivots($klines);
        if (!empty($fibPivots)) {
            $distToS1 = abs($currentPrice - $fibPivots['s1']);
            $distToR1 = abs($currentPrice - $fibPivots['r1']);
            if ($distToS1 < ($currentPrice * 0.005)) { $categories['Structure']['buy'] += $wFib; $sig = 'BUY (Fib S1)'; }
            elseif ($distToR1 < ($currentPrice * 0.005)) { $categories['Structure']['sell'] += $wFib; $sig = 'SELL (Fib R1)'; }
            else { $sig = 'NEUTRAL'; }
            $indicatorsList[] = ['name' => 'Fibonacci Pivots', 'value' => 'S1/R1 Zone', 'signal' => $sig];
        }

        // --- 7. ADVANCED & STATISTICAL ---
        $hurst = $this->ta->calculateHurstExponent($closes);
        $fractal = $this->ta->calculateFractalDimension($closes);
        
        $hurstSig = 'NEUTRAL';
        if ($hurst > 0.6) { $categories['Advanced']['buy'] += 8; $hurstSig = 'STRONG TREND'; }
        elseif ($hurst < 0.4) { $categories['Advanced']['sell'] += 8; $hurstSig = 'MEAN REVERTING'; }
        $indicatorsList[] = ['name' => 'Hurst Exponent', 'value' => round($hurst, 2), 'signal' => $hurstSig];

        $fractalSig = 'NEUTRAL';
        // NEW: Fractal complexity penalty/bonus
        $fractalPenalty = 1.0;
        if ($fractal > 1.65) { 
            $fractalSig = 'COMPLEX/VOLATILE'; 
            $fractalPenalty = 0.7; // Market is too complex, reduce overall confidence
        } elseif ($fractal < 1.4) { 
            $fractalSig = 'EFFICIENT'; 
            $fractalPenalty = 1.1; // Market is efficient, boost confidence
        }
        $indicatorsList[] = ['name' => 'Fractal Dim', 'value' => round($fractal, 2), 'signal' => $fractalSig];

        // NEW: SuperTrend Signal (Calibration)
        $stSig = 'NEUTRAL';
        if ($currSt['trend'] == 1) { $categories['Advanced']['buy'] += 12; $stSig = 'BULLISH'; }
        elseif ($currSt['trend'] == -1) { $categories['Advanced']['sell'] += 12; $stSig = 'BEARISH'; }
        $indicatorsList[] = ['name' => 'SuperTrend', 'value' => round($currSt['value'], 2), 'signal' => $stSig];

        // NEW: Kalman Alignment
        $kalmanSig = 'NEUTRAL';
        if ($denoisedPrice > $currentPrice * 1.001) { $categories['Trend']['buy'] += 6; $kalmanSig = 'UPWARD'; }
        elseif ($denoisedPrice < $currentPrice * 0.999) { $categories['Trend']['sell'] += 6; $kalmanSig = 'DOWNWARD'; }
        $indicatorsList[] = ['name' => 'Kalman Denoise', 'value' => 'Smooth', 'signal' => $kalmanSig];

        // XGBoost Forecast (Real ML)
        $mlResult = $this->ml->predictXGBoost($klines, 5);
        $mlSig = 'NEUTRAL';
        $lr = ['slope' => 0, 'intercept' => 0, 'r_squared' => 0]; 
        
        if (!isset($mlResult['error'])) {
            $forecast = $mlResult['forecast'];
            $r2 = $mlResult['r_squared'];
            $lastPred = end($forecast);
            if ($lastPred > $currentPrice && $r2 > 0.5) { $categories['Advanced']['buy'] += 15; $mlSig = 'BULLISH (XGB)'; }
            elseif ($lastPred < $currentPrice && $r2 > 0.5) { $categories['Advanced']['sell'] += 15; $mlSig = 'BEARISH (XGB)'; }
            $indicatorsList[] = ['name' => 'XGBoost ML', 'value' => 'R2:'.round($r2, 2), 'signal' => $mlSig];
            $lr = $this->ml->predictLinearRegression($closes, 5);
        } else {
            $lr = $this->ml->predictLinearRegression($closes, 5);
            if ($lr['slope'] > 0 && $lr['r_squared'] > 0.7) { $categories['Advanced']['buy'] += 10; $mlSig = 'BULLISH (LR)'; }
            elseif ($lr['slope'] < 0 && $lr['r_squared'] > 0.7) { $categories['Advanced']['sell'] += 10; $mlSig = 'BEARISH (LR)'; }
            $indicatorsList[] = ['name' => 'Legacy ML', 'value' => 'Fallback', 'signal' => $mlSig];
        }

        // Monte Carlo Simulation
        $mc = $this->ml->runMonteCarlo($closes, 20, 1000);
        $mcSig = 'NEUTRAL';
        if ($mc['median'] > $currentPrice) { $categories['Advanced']['buy'] += 5; $mcSig = 'PROB BULLISH'; }
        else { $categories['Advanced']['sell'] += 5; $mcSig = 'PROB BEARISH'; }
        $indicatorsList[] = ['name' => 'Monte Carlo', 'value' => '$'.round($mc['median'], 2), 'signal' => $mcSig];

        // Compute Multi-Layer Scores
        $totalBuy = 0; $totalSell = 0;
        $categoryScores = [];
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

        // --- 8. SQUEEZE MOMENTUM CONFLUENCE ---
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

        // Apply Fractal Penalty to Confidence
        $confidence = max($totalBuy, $totalSell) * $fractalPenalty;
        if ($totalBuy > $totalSell && $totalBuy >= 40) {
            $overallSignal = ($totalBuy >= 75) ? 'ULTRA BUY' : (($totalBuy >= 60) ? 'STRONG BUY' : 'BUY');
        } elseif ($totalSell > $totalBuy && $totalSell >= 40) {
            $overallSignal = ($totalSell >= 75) ? 'ULTRA SELL' : (($totalSell >= 60) ? 'STRONG SELL' : 'SELL');
        } else {
            $overallSignal = 'NEUTRAL';
        }

        // --- 9. VOLATILITY FILTER (Whipsaw Prevention) ---
        $volatilityFilter = false;
        $atrHist = $this->ta->calculateATR($klines, 14);
        if (!empty($atrHist)) {
            $lastAtr = end($atrHist);
            // Calculate mean ATR of last 50 periods
            $meanAtr = array_sum(array_slice($atrHist, -50)) / 50;
            if ($lastAtr > $meanAtr * 2.5) {
                $volatilityFilter = true;
                $confidence *= 0.5;
                $overallSignal .= " (High Vol Risk)";
                $indicatorsList[] = ['name' => 'Volatility Filter', 'value' => 'Extreme', 'signal' => 'DANGER'];
            }
        }

        // Confluence Check (Multi-Stage)
        if ($overallSignal !== 'NEUTRAL' && !$volatilityFilter) {
            $isBuy = str_contains($overallSignal, 'BUY');
            
            // 1. Trend/Regime Alignment
            if (($isBuy && $currSt['trend'] == -1) || (!$isBuy && $currSt['trend'] == 1)) {
                $confidence *= 0.6; // Counter-trend penalty increased
                $overallSignal .= " (C-Trend)";
            }
            
            // 2. ML Divergence
            if (($isBuy && $lr['slope'] < 0) || (!$isBuy && $lr['slope'] > 0)) {
                $confidence *= 0.7;
                $overallSignal .= " (ML Div)";
            }
            
            // 3. Absolute Confluence -> Upgrade to ULTRA
            // Require Squeeze Release or strong momentum
            $hasSqueezeConfluence = !$currSqueeze['is_squeeze'] && (($isBuy && $currSqueeze['momentum'] > 0) || (!$isBuy && $currSqueeze['momentum'] < 0));
            
            if ($isBuy && $currSt['trend'] == 1 && $denoisedPrice > $currentPrice && $totalBuy > 80 && $hasSqueezeConfluence) {
                $overallSignal = 'ULTRA BUY';
                $confidence = min(100, $confidence + 15);
            } elseif (!$isBuy && $currSt['trend'] == -1 && $denoisedPrice < $currentPrice && $totalSell > 80 && $hasSqueezeConfluence) {
                $overallSignal = 'ULTRA SELL';
                $confidence = min(100, $confidence + 15);
            }
        }

        $marketRegime = $currSqueeze['is_squeeze'] ? 'SQUEEZING' : 'TRENDING';
        if ($trendStrength === 'WEAK' && !$currSqueeze['is_squeeze']) $marketRegime = 'RANGING';
        elseif (isset($upper, $lower) && ($upper - $lower) / $currentPrice > 0.05) $marketRegime = 'VOLATILE';

        // --- 10. MULTI-TIMEFRAME (HTF) CONFLUENCE ---
        $htfAligned = false;
        if (!empty($htfKlines) && count($htfKlines) >= 200) {
            $htfCloses = array_column($htfKlines, 'close');
            $htfEma50Arr = $this->ta->calculateEMA($htfCloses, 50); $htfEma50 = end($htfEma50Arr);
            $htfEma200Arr = $this->ta->calculateEMA($htfCloses, 200); $htfEma200 = end($htfEma200Arr);

            if ($htfEma50 > $htfEma200) {
                if (in_array($overallSignal, ['SELL', 'STRONG SELL', 'ULTRA SELL'])) {
                    $confidence *= 0.5; // Heavy HTF penalty
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Against HTF Bull', 'signal' => 'DANGER'];
                } elseif (str_contains($overallSignal, 'BUY')) {
                    $confidence = min(100, $confidence + 15);
                    $htfAligned = true;
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Bullish Align', 'signal' => 'BUY'];
                }
            } elseif ($htfEma50 < $htfEma200) {
                if (in_array($overallSignal, ['BUY', 'STRONG BUY', 'ULTRA BUY'])) {
                    $confidence *= 0.5; // Heavy HTF penalty
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Against HTF Bear', 'signal' => 'DANGER'];
                } elseif (str_contains($overallSignal, 'SELL')) {
                    $confidence = min(100, $confidence + 15);
                    $htfAligned = true;
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Bearish Align', 'signal' => 'SELL'];
                }
            }
        }

        // --- 11. SIGNAL PERSISTENCE ---
        $prevSignal = \Illuminate\Support\Facades\Cache::get("last_sig_{$symbol}_{$interval}");
        $persistence = (int) \Illuminate\Support\Facades\Cache::get("sig_persist_{$symbol}_{$interval}", 0);
        
        if ($prevSignal === $overallSignal && $overallSignal !== 'NEUTRAL') {
            $persistence++;
        } else {
            $persistence = 0;
            \Illuminate\Support\Facades\Cache::put("last_sig_{$symbol}_{$interval}", $overallSignal, 600);
        }
        \Illuminate\Support\Facades\Cache::put("sig_persist_{$symbol}_{$interval}", $persistence, 600);

        // --- 12. NEWS IMPACT FILTER ---
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
        // --- 13. DYNAMIC ACCURACY TP/SL ---
        $isBuy = str_contains($overallSignal, 'BUY');
        $levels = $this->ta->calculateDynamicTPBL($klines, $isBuy ? 'BUY' : 'SELL');
        
        $trailingStop = $this->ta->calculateTrailingStopATR($klines, $isBuy ? 'BUY' : 'SELL', 2.0);
        
        $tp = $levels['tp'];
        $sl = $levels['sl'];
        
        // Calculate actual Risk/Reward
        $risk = abs($currentPrice - $sl);
        $reward = abs($tp - $currentPrice);
        $riskReward = $risk > 0 ? $reward / $risk : 0;

        // --- 14. RISK-REWARD FILTER ---
        if ($overallSignal !== 'NEUTRAL' && $riskReward < 1.3) {
            $confidence *= 0.5;
            $overallSignal .= " (Low RR)";
            $indicatorsList[] = ['name' => 'RR Filter', 'value' => round($riskReward, 2), 'signal' => 'POOR'];
        }
         return [
             'signal' => $overallSignal,
             'confidence' => round($confidence, 1),
             'buy_score' => round($totalBuy, 1),
             'sell_score' => round($totalSell, 1),
             'indicators' => $indicatorsList,
             'categories' => $categoryScores,
             'price' => $currentPrice,
            'tp' => $tp,
            'sl' => $sl,
            'target' => $tp, // Alias for frontend
            'stop_loss' => $sl, // Alias for frontend
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
            'summary' => "Engine V4.1 (High Accuracy): {$overallSignal}. RR: " . round($riskReward, 2) . ". Consistency: {$persistence} bars. Market is {$marketRegime}. R2: " . round($mlResult['r_squared'] ?? 0, 2) . "."
         ];
     }
 }
