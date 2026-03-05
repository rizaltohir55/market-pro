<?php

namespace App\Services;

class PredictionService
{
    private TechnicalAnalysisService $ta;

    public function __construct(TechnicalAnalysisService $ta)
    {
        $this->ta = $ta;
    }

    /**
     * Get composite scalping signal for a symbol with optional High Time Frame (HTF) Confluence and Macro Data.
     */
    public function getScalpingSignal(array $klines, array $htfKlines = [], int $fearGreed = 50, float $lsRatio = 1.0, bool $isTrending = false): array
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

        if (count($klines) < 200) { // Needs sufficient data for long EMAs
            return $defaultResponse;
        }

        $closes = array_column($klines, 'close');
        $highs = array_column($klines, 'high');
        $lows = array_column($klines, 'low');
        $volumes = array_column($klines, 'volume');
        $currentPrice = end($closes);

        // 1. Calculate Market Regime First (using ADX)
        $adxData = $this->ta->calculateADX($klines);
        $lastAdx = !empty($adxData['adx']) ? end($adxData['adx']) : 20.0;

        $regimeForWeighting = 'UNKNOWN';
        if ($lastAdx < 25) {
            $regimeForWeighting = 'RANGING';
            // In a ranging market, momentum and structure matter most. Trend indicators lag and fail.
            $weightTrend = 10;
            $weightMomentum = 40;
            $weightMACD = 10;
            $weightVolume = 15;
            $weightStructure = 25;
        } else {
            $regimeForWeighting = 'TRENDING';
            // In a trending market, trend-following and MACD are king. Momentum oscillates falsely.
            $weightTrend = 45;
            $weightMomentum = 10;
            $weightMACD = 20;
            $weightVolume = 15;
            $weightStructure = 10;
        }

        $categories = [
            'Trend' => ['buy' => 0, 'sell' => 0, 'weight' => $weightTrend],
            'Momentum' => ['buy' => 0, 'sell' => 0, 'weight' => $weightMomentum],
            'MACD' => ['buy' => 0, 'sell' => 0, 'weight' => $weightMACD],
            'Volume' => ['buy' => 0, 'sell' => 0, 'weight' => $weightVolume],
            'Structure' => ['buy' => 0, 'sell' => 0, 'weight' => $weightStructure],
        ];

        $indicatorsList = [];

        // --- 1. TREND ---
        // EMA 9/21 (approx 30% of Trend weight)
        $wEmaShort = $weightTrend * 0.3;
        $ema9Arr = $this->ta->calculateEMA($closes, 9);
        $ema9 = end($ema9Arr);
        $ema21Arr = $this->ta->calculateEMA($closes, 21);
        $ema21 = end($ema21Arr);
        if ($ema9 > $ema21) { $categories['Trend']['buy'] += $wEmaShort; $sig = 'BUY'; }
        else { $categories['Trend']['sell'] += $wEmaShort; $sig = 'SELL'; }
        $indicatorsList[] = ['name' => 'EMA 9/21', 'value' => round($ema9,2).'/'.round($ema21,2), 'signal' => $sig];

        // EMA 50/200 (approx 30% of Trend weight)
        $wEmaLong = $weightTrend * 0.3;
        $ema50Arr = $this->ta->calculateEMA($closes, 50);
        $ema50 = end($ema50Arr);
        $ema200Arr = $this->ta->calculateEMA($closes, 200);
        $ema200 = end($ema200Arr);
        if ($ema50 > $ema200) { $categories['Trend']['buy'] += $wEmaLong; $sig = 'BUY'; }
        else { $categories['Trend']['sell'] += $wEmaLong; $sig = 'SELL'; }
        $indicatorsList[] = ['name' => 'EMA 50/200', 'value' => round($ema50,2).'/'.round($ema200,2), 'signal' => $sig];

        // Ichimoku Cloud (approx 20% of Trend Weight)
        $wIchi = $weightTrend * 0.2;
        $ichi = $this->ta->calculateIchimoku($klines);
        if (!empty($ichi['senkou_a'])) {
            $sa = end($ichi['senkou_a']);
            $sb = end($ichi['senkou_b']);
            if ($currentPrice > max($sa, $sb)) { $categories['Trend']['buy'] += $wIchi; $sig = 'BUY'; }
            elseif ($currentPrice < min($sa, $sb)) { $categories['Trend']['sell'] += $wIchi; $sig = 'SELL'; }
            else { $sig = 'NEUTRAL'; }
            $indicatorsList[] = ['name' => 'Ichimoku Cloud', 'value' => 'A:'.round($sa,2).' B:'.round($sb,2), 'signal' => $sig];
        }

        // Parabolic SAR (approx 10% of Trend Weight)
        $wSar = $weightTrend * 0.1;
        $sarArr = $this->ta->calculateParabolicSAR($klines);
        if (!empty($sarArr)) {
            $lastSar = end($sarArr);
            if ($currentPrice > $lastSar) { $categories['Trend']['buy'] += $wSar; $sig = 'BUY'; }
            else { $categories['Trend']['sell'] += $wSar; $sig = 'SELL'; }
            $indicatorsList[] = ['name' => 'Parabolic SAR', 'value' => round($lastSar, 2), 'signal' => $sig];
        }

        // ADX / Trend Strength (Weight 4 for direction)
        $trendStrength = 'UNKNOWN';
        if (!empty($adxData['adx'])) {
            $lastAdxVal = end($adxData['adx']);
            $lastPlusDI = end($adxData['plus_di']);
            $lastMinusDI = end($adxData['minus_di']);
            
            if ($lastAdxVal > 25) {
                if ($lastPlusDI > $lastMinusDI) { $categories['Trend']['buy'] += 4; $sig = 'BUY'; }
                else { $categories['Trend']['sell'] += 4; $sig = 'SELL'; }
            } else { $sig = 'NEUTRAL'; }

            if ($lastAdxVal < 20) $trendStrength = 'WEAK';
            elseif ($lastAdxVal < 40) $trendStrength = 'MODERATE';
            elseif ($lastAdxVal < 60) $trendStrength = 'STRONG';
            else $trendStrength = 'VERY STRONG';

            $indicatorsList[] = ['name' => 'ADX', 'value' => round($lastAdxVal,1), 'signal' => $sig];
        }

        // --- 2. MOMENTUM ---
        // RSI (approx 30% of Momentum Weight)
        $wRsi = $weightMomentum * 0.32;
        $rsiArr = $this->ta->calculateRSI($closes);
        $rsi = end($rsiArr);
        if ($rsi < 30) { $categories['Momentum']['buy'] += $wRsi; $sig = 'BUY'; }
        elseif ($rsi > 70) { $categories['Momentum']['sell'] += $wRsi; $sig = 'SELL'; }
        elseif ($rsi > 50) { $categories['Momentum']['buy'] += ($wRsi/2); $sig = 'BULLISH'; }
        else { $categories['Momentum']['sell'] += ($wRsi/2); $sig = 'BEARISH'; }
        $indicatorsList[] = ['name' => 'RSI', 'value' => round($rsi, 1), 'signal' => $sig];

        // Stochastic (approx 28% of Momentum Weight)
        $wStoch = $weightMomentum * 0.28;
        $stoch = $this->ta->calculateStochastic($klines);
        if (!empty($stoch['k'])) {
            $k = end($stoch['k']); $d = end($stoch['d']);
            if ($k < 20 && $k > $d) { $categories['Momentum']['buy'] += $wStoch; $sig = 'BUY'; }
            elseif ($k > 80 && $k < $d) { $categories['Momentum']['sell'] += $wStoch; $sig = 'SELL'; }
            elseif ($k > $d) { $categories['Momentum']['buy'] += ($wStoch/2); $sig = 'BULLISH'; }
            else { $categories['Momentum']['sell'] += ($wStoch/2); $sig = 'BEARISH'; }
            $indicatorsList[] = ['name' => 'Stochastic', 'value' => round($k,1).'/'.round($d,1), 'signal' => $sig];
        }

        // CCI (approx 20% of Momentum Weight)
        $wCci = $weightMomentum * 0.20;
        $cciArr = $this->ta->calculateCCI($klines);
        $cci = end($cciArr);
        if ($cci < -100) { $categories['Momentum']['buy'] += $wCci; $sig = 'BUY'; }
        elseif ($cci > 100) { $categories['Momentum']['sell'] += $wCci; $sig = 'SELL'; }
        else { $sig = 'NEUTRAL'; }
        $indicatorsList[] = ['name' => 'CCI', 'value' => round($cci, 1), 'signal' => $sig];

        // Williams %R (approx 20% of Momentum Weight)
        $wWr = $weightMomentum * 0.20;
        $wrArr = $this->ta->calculateWilliamsR($klines);
        $wr = end($wrArr);
        if ($wr < -80) { $categories['Momentum']['buy'] += $wWr; $sig = 'BUY'; }
        elseif ($wr > -20) { $categories['Momentum']['sell'] += $wWr; $sig = 'SELL'; }
        else { $sig = 'NEUTRAL'; }
        $indicatorsList[] = ['name' => 'Williams %R', 'value' => round($wr, 1), 'signal' => $sig];

        // --- 3. MACD ---
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

        // --- 4. VOLUME/MONEY FLOW ---
        // OBV (approx 33% of Volume Weight)
        $wObv = $weightVolume * 0.33;
        $obv = $this->ta->calculateOBV($klines);
        $obvM = end($obv); $prevObv = $obv[count($obv)-2] ?? $obvM;
        if ($obvM > $prevObv && $closes[count($closes)-1] > $closes[count($closes)-2]) { $categories['Volume']['buy'] += $wObv; $sig = 'BUY'; }
        elseif ($obvM < $prevObv && $closes[count($closes)-1] < $closes[count($closes)-2]) { $categories['Volume']['sell'] += $wObv; $sig = 'SELL'; }
        else { $sig = 'NEUTRAL'; }
        $indicatorsList[] = ['name' => 'OBV', 'value' => 'Trend', 'signal' => $sig];

        // VWAP (approx 33% of Volume Weight)
        $wVwap = $weightVolume * 0.33;
        $vwapArr = $this->ta->calculateVWAP($klines);
        $vwap = end($vwapArr);
        if ($currentPrice > $vwap) { $categories['Volume']['buy'] += $wVwap; $sig = 'BUY'; }
        else { $categories['Volume']['sell'] += $wVwap; $sig = 'SELL'; }
        $indicatorsList[] = ['name' => 'VWAP', 'value' => round($vwap, 2), 'signal' => $sig];

        // CMF (approx 34% of Volume Weight)
        $wCmf = $weightVolume * 0.34;
        $cmfArr = $this->ta->calculateCMF($klines);
        $cmf = end($cmfArr);
        if ($cmf > 0.1) { $categories['Volume']['buy'] += $wCmf; $sig = 'BUY'; }
        elseif ($cmf < -0.1) { $categories['Volume']['sell'] += $wCmf; $sig = 'SELL'; }
        else { $sig = 'NEUTRAL'; }
        $indicatorsList[] = ['name' => 'CMF', 'value' => round($cmf, 2), 'signal' => $sig];

        // --- 5. STRUCTURE/S&R ---
        // Bollinger Bands (approx 50% of Structure Weight)
        $wBB = $weightStructure * 0.50;
        $bb = $this->ta->calculateBollingerBands($closes);
        if (!empty($bb['upper'])) {
            $upper = end($bb['upper']); $lower = end($bb['lower']);
            if ($currentPrice <= $lower * 1.005) { $categories['Structure']['buy'] += $wBB; $sig = 'BUY'; }
            elseif ($currentPrice >= $upper * 0.995) { $categories['Structure']['sell'] += $wBB; $sig = 'SELL'; }
            else { $sig = 'NEUTRAL'; }
            $indicatorsList[] = ['name' => 'Bollinger Bands', 'value' => 'StdDev 2.0', 'signal' => $sig];
        }
        
        // Pivot Points (approx 50% of Structure Weight)
        $wPivot = $weightStructure * 0.50;
        $pivots = $this->ta->calculatePivotPoints($klines);
        if (!empty($pivots)) {
            $distToS1 = abs($currentPrice - $pivots['s1']);
            $distToR1 = abs($currentPrice - $pivots['r1']);
            if ($distToS1 < $distToR1 && $currentPrice < $pivots['pivot']) { $categories['Structure']['buy'] += $wPivot; $sig = 'BUY'; }
            elseif ($distToR1 < $distToS1 && $currentPrice > $pivots['pivot']) { $categories['Structure']['sell'] += $wPivot; $sig = 'SELL'; }
            else { $sig = 'NEUTRAL'; }
            $indicatorsList[] = ['name' => 'Pivot Points', 'value' => 'Classic', 'signal' => $sig];
        }

        // Compute Multi-Layer Scores
        $totalBuy = 0; $totalSell = 0;
        $categoryScores = [];
        foreach ($categories as $cat => $sc) {
            $totalBuy += $sc['buy'];
            $totalSell += $sc['sell'];
            $maxPossible = $sc['weight'];
            $catBuyPct = $maxPossible > 0 ? ($sc['buy'] / $maxPossible) * 100 : 0;
            $catSellPct = $maxPossible > 0 ? ($sc['sell'] / $maxPossible) * 100 : 0;
            if ($catBuyPct > $catSellPct && $catBuyPct > 40) $catSig = 'BUY';
            elseif ($catSellPct > $catBuyPct && $catSellPct > 40) $catSig = 'SELL';
            else $catSig = 'NEUTRAL';

            $categoryScores[$cat] = [
                'buy' => round($catBuyPct, 1),
                'sell' => round($catSellPct, 1),
                'signal' => $catSig
            ];
        }

        $confidence = max($totalBuy, $totalSell);
        if ($totalBuy > $totalSell && $totalBuy >= 40) {
            $overallSignal = ($totalBuy >= 70) ? 'STRONG BUY' : 'BUY';
        } elseif ($totalSell > $totalBuy && $totalSell >= 40) {
            $overallSignal = ($totalSell >= 70) ? 'STRONG SELL' : 'SELL';
        } else {
            $overallSignal = 'NEUTRAL';
        }

        // Market Regime
        $marketRegime = 'RANGING';
        if ($trendStrength === 'STRONG' || $trendStrength === 'VERY STRONG') $marketRegime = 'TRENDING';
        elseif (isset($upper, $lower) && ($upper - $lower) / $currentPrice > 0.05) $marketRegime = 'VOLATILE';

        // --- 6. MULTI-TIMEFRAME (HTF) CONFLUENCE ---
        $htfTrend = 'UNKNOWN';
        if (!empty($htfKlines) && count($htfKlines) >= 200) {
            $htfCloses = array_column($htfKlines, 'close');
            $ema50Arr = $this->ta->calculateEMA($htfCloses, 50);
            $htfEma50 = end($ema50Arr);
            $ema200Arr = $this->ta->calculateEMA($htfCloses, 200);
            $htfEma200 = end($ema200Arr);

            if ($htfEma50 > $htfEma200) {
                $htfTrend = 'BULLISH';
                if (in_array($overallSignal, ['SELL', 'STRONG SELL'])) {
                    // Trading against HTF Trend penalty
                    $confidence = max(0, $confidence - 25);
                    $overallSignal = 'SELL (Counter-Trend)';
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Bearish vs HTF Bull', 'signal' => 'DANGER'];
                } elseif (in_array($overallSignal, ['BUY', 'STRONG BUY'])) {
                    // Trend alignment bonus
                    $confidence = min(100, $confidence + 15);
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Bullish Alignment', 'signal' => 'BUY'];
                }
            } elseif ($htfEma50 < $htfEma200) {
                $htfTrend = 'BEARISH';
                if (in_array($overallSignal, ['BUY', 'STRONG BUY'])) {
                    // Trading against HTF Trend penalty
                    $confidence = max(0, $confidence - 25);
                    $overallSignal = 'BUY (Counter-Trend)';
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Bullish vs HTF Bear', 'signal' => 'DANGER'];
                } elseif (in_array($overallSignal, ['SELL', 'STRONG SELL'])) {
                    // Trend alignment bonus
                    $confidence = min(100, $confidence + 15);
                    $indicatorsList[] = ['name' => 'HTF Confluence', 'value' => 'Bearish Alignment', 'signal' => 'SELL'];
                }
            }
        }

        // --- 7. MACRO & SENTIMENT MULTIPLIERS ---
        // Fear & Greed Index
        if ($fearGreed <= 25) {
            // Extreme Fear: Contrarian Buy
            $confidence = min(100, $confidence + 10);
            if (!in_array($overallSignal, ['BUY', 'STRONG BUY'])) $overallSignal = 'BUY (Contrarian Bottom)';
            $indicatorsList[] = ['name' => 'Fear & Greed', 'value' => "{$fearGreed} (Fear)", 'signal' => 'BUY'];
        } elseif ($fearGreed >= 75) {
            // Extreme Greed: Contrarian Sell
            $confidence = min(100, $confidence + 10);
            if (!in_array($overallSignal, ['SELL', 'STRONG SELL'])) $overallSignal = 'SELL (Contrarian Top)';
            $indicatorsList[] = ['name' => 'Fear & Greed', 'value' => "{$fearGreed} (Greed)", 'signal' => 'SELL'];
        } else {
            $indicatorsList[] = ['name' => 'Fear & Greed', 'value' => "{$fearGreed} (Neutral)", 'signal' => 'NEUTRAL'];
        }

        // Long/Short Account Ratio
        if ($lsRatio > 2.5) {
            // Too many longs, high risk of long-squeeze / dump
            $confidence = max(0, $confidence - 15);
            $indicatorsList[] = ['name' => 'Whale L/S Ratio', 'value' => "{$lsRatio} (Long Heavy)", 'signal' => 'SELL'];
        } elseif ($lsRatio < 0.8) {
            // More shorts, short-squeeze potential
            $confidence = min(100, $confidence + 10);
            $indicatorsList[] = ['name' => 'Whale L/S Ratio', 'value' => "{$lsRatio} (Short Heavy)", 'signal' => 'BUY'];
        } else {
            $indicatorsList[] = ['name' => 'Whale L/S Ratio', 'value' => "{$lsRatio} (Balanced)", 'signal' => 'NEUTRAL'];
        }

        // Targets using ATR
        $atrHist = $this->ta->calculateATR($klines);
        $atr = end($atrHist) ?: ($currentPrice * 0.02); // fallback 2% 

        // Traffic Volatility Multiplier
        $multiplier = $isTrending ? 1.2 : 1.0;
        if ($isTrending) {
            $indicatorsList[] = ['name' => 'Retail Traffic', 'value' => 'High Search Volume', 'signal' => 'VOLATILE'];
        } else {
            $indicatorsList[] = ['name' => 'Retail Traffic', 'value' => 'Normal', 'signal' => 'NEUTRAL'];
        }

        $targetBuy = $currentPrice + (1.5 * $atr * $multiplier);
        $stopLossBuy = $currentPrice - (1.0 * $atr * $multiplier);
        $targetSell = $currentPrice - (1.5 * $atr * $multiplier);
        $stopLossSell = $currentPrice + (1.0 * $atr * $multiplier);
        $riskReward = 1.5;

        return [
            'signal' => $overallSignal,
            'confidence' => round($confidence, 1),
            'buy_score' => round($totalBuy, 1),
            'sell_score' => round($totalSell, 1),
            'indicators' => $indicatorsList,
            'categories' => $categoryScores,
            'price' => $currentPrice,
            'price_target_buy' => $targetBuy,
            'price_target_sell' => $targetSell,
            'stop_loss_buy' => $stopLossBuy,
            'stop_loss_sell' => $stopLossSell,
            'risk_reward' => $riskReward,
            'trend_strength' => $trendStrength,
            'market_regime' => $marketRegime,
            'summary' => "Engine Output: {$overallSignal}. Total Buy: {$totalBuy} / Total Sell: {$totalSell}. Market is {$marketRegime}."
        ];
    }
}
