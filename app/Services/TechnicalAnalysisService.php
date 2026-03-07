<?php

namespace App\Services;

class TechnicalAnalysisService
{
    /**
     * Calculate Exponential Moving Average.
     */
    public function calculateEMA(array $closes, int $period): array
    {
        $ema = [];
        if (count($closes) < $period) return $ema;

        // SMA for first value
        $sma = array_sum(array_slice($closes, 0, $period)) / $period;
        $ema[] = $sma;

        $multiplier = 2 / ($period + 1);

        for ($i = $period; $i < count($closes); $i++) {
            $prev = end($ema);
            $ema[] = ($closes[$i] - $prev) * $multiplier + $prev;
        }

        return $ema;
    }

    /**
     * Calculate Relative Strength Index.
     */
    public function calculateRSI(array $closes, int $period = 14): array
    {
        $rsi = [];
        if (count($closes) < $period + 1) return $rsi;

        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }

        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = ($avgGain * ($period - 1) + $gains[$i]) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$i]) / $period;

            if ($avgLoss == 0) {
                $rsi[] = 100;
            } else {
                $rs = $avgGain / $avgLoss;
                $rsi[] = 100 - (100 / (1 + $rs));
            }
        }

        return $rsi;
    }

    /**
     * Calculate MACD (12, 26, 9).
     */
    public function calculateMACD(array $closes): array
    {
        $ema12 = $this->calculateEMA($closes, 12);
        $ema26 = $this->calculateEMA($closes, 26);

        if (empty($ema12) || empty($ema26)) return ['macd' => [], 'signal' => [], 'histogram' => []];

        $offset = count($ema12) - count($ema26);
        $macdLine = [];

        for ($i = 0; $i < count($ema26); $i++) {
            $macdLine[] = $ema12[$i + $offset] - $ema26[$i];
        }

        $signal = $this->calculateEMA($macdLine, 9);
        $histOffset = count($macdLine) - count($signal);
        $histogram = [];

        for ($i = 0; $i < count($signal); $i++) {
            $histogram[] = $macdLine[$i + $histOffset] - $signal[$i];
        }

        return [
            'macd' => $macdLine,
            'signal' => $signal,
            'histogram' => $histogram,
        ];
    }

    /**
     * Calculate Bollinger Bands (20, 2).
     */
    public function calculateBollingerBands(array $closes, int $period = 20, float $stdDev = 2.0): array
    {
        $bands = ['upper' => [], 'middle' => [], 'lower' => []];
        if (count($closes) < $period) return $bands;

        for ($i = $period - 1; $i < count($closes); $i++) {
            $slice = array_slice($closes, $i - $period + 1, $period);
            $sma = array_sum($slice) / $period;
            $variance = array_sum(array_map(fn($v) => pow($v - $sma, 2), $slice)) / $period;
            $sd = sqrt($variance);

            $bands['upper'][] = $sma + ($stdDev * $sd);
            $bands['middle'][] = $sma;
            $bands['lower'][] = $sma - ($stdDev * $sd);
        }

        return $bands;
    }

    /**
     * Find support and resistance levels.
     */
    public function findSupportResistance(array $klines, int $lookback = 20): array
    {
        $highs = array_column($klines, 'high');
        $lows = array_column($klines, 'low');
        $levels = ['support' => [], 'resistance' => []];

        if (count($highs) < $lookback) return $levels;

        for ($i = $lookback; $i < count($highs) - $lookback; $i++) {
            $leftHighs = array_slice($highs, $i - $lookback, $lookback);
            $rightHighs = array_slice($highs, $i + 1, min($lookback, count($highs) - $i - 1));

            if ($highs[$i] >= max($leftHighs) && !empty($rightHighs) && $highs[$i] >= max($rightHighs)) {
                $levels['resistance'][] = $highs[$i];
            }

            $leftLows = array_slice($lows, $i - $lookback, $lookback);
            $rightLows = array_slice($lows, $i + 1, min($lookback, count($lows) - $i - 1));

            if ($lows[$i] <= min($leftLows) && !empty($rightLows) && $lows[$i] <= min($rightLows)) {
                $levels['support'][] = $lows[$i];
            }
        }

        $levels['resistance'] = array_unique(array_slice(array_reverse($levels['resistance']), 0, 5));
        $levels['support'] = array_unique(array_slice(array_reverse($levels['support']), 0, 5));

        return $levels;
    }

    /**
     * Calculate Average True Range.
     */
    public function calculateATR(array $klines, int $period = 14): array
    {
        $atr = [];
        if (count($klines) < $period + 1) return $atr;

        $trueRanges = [];
        for ($i = 1; $i < count($klines); $i++) {
            $high = $klines[$i]['high'];
            $low = $klines[$i]['low'];
            $prevClose = $klines[$i - 1]['close'];

            $trueRanges[] = max($high - $low, abs($high - $prevClose), abs($low - $prevClose));
        }

        $atr[] = array_sum(array_slice($trueRanges, 0, $period)) / $period;

        for ($i = $period; $i < count($trueRanges); $i++) {
            $prev = end($atr);
            $atr[] = (($prev * ($period - 1)) + $trueRanges[$i]) / $period;
        }

        return $atr;
    }

    /**
     * Calculate Stochastic Oscillator (%K, %D).
     */
    public function calculateStochastic(array $klines, int $period = 14, int $smoothK = 3, int $smoothD = 3): array
    {
        $stoch = ['k' => [], 'd' => []];
        if (count($klines) < $period) return $stoch;

        $kValues = [];
        for ($i = $period - 1; $i < count($klines); $i++) {
            $slice = array_slice($klines, $i - $period + 1, $period);
            $highstHigh = max(array_column($slice, 'high'));
            $lowestLow = min(array_column($slice, 'low'));
            $close = $klines[$i]['close'];

            $k = ($highstHigh - $lowestLow) == 0 ? 0 : (($close - $lowestLow) / ($highstHigh - $lowestLow)) * 100;
            $kValues[] = $k;
        }

        $smoothedK = [];
        for ($i = $smoothK - 1; $i < count($kValues); $i++) {
            $smoothedK[] = array_sum(array_slice($kValues, $i - $smoothK + 1, $smoothK)) / $smoothK;
        }

        $smoothedD = [];
        for ($i = $smoothD - 1; $i < count($smoothedK); $i++) {
            $smoothedD[] = array_sum(array_slice($smoothedK, $i - $smoothD + 1, $smoothD)) / $smoothD;
        }

        return ['k' => $smoothedK, 'd' => $smoothedD];
    }

    /**
     * Calculate Average Directional Index (ADX) along with +DI and -DI.
     */
    public function calculateADX(array $klines, int $period = 14): array
    {
        if (count($klines) < $period * 2) return ['adx' => [], 'plus_di' => [], 'minus_di' => []];

        $tr = []; $plusDM = []; $minusDM = [];

        for ($i = 1; $i < count($klines); $i++) {
             $high = $klines[$i]['high']; $low = $klines[$i]['low'];
             $prevHigh = $klines[$i-1]['high']; $prevLow = $klines[$i-1]['low'];
             $prevClose = $klines[$i-1]['close'];

             $tr[] = max($high - $low, abs($high - $prevClose), abs($low - $prevClose));

             $upMove = $high - $prevHigh; $downMove = $prevLow - $low;
             $plusDM[] = ($upMove > $downMove && $upMove > 0) ? $upMove : 0;
             $minusDM[] = ($downMove > $upMove && $downMove > 0) ? $downMove : 0;
        }

        $smoothTR = [array_sum(array_slice($tr, 0, $period))];
        $smoothPlusDM = [array_sum(array_slice($plusDM, 0, $period))];
        $smoothMinusDM = [array_sum(array_slice($minusDM, 0, $period))];

        for ($i = $period; $i < count($tr); $i++) {
            $smoothTR[] = $smoothTR[count($smoothTR)-1] - ($smoothTR[count($smoothTR)-1] / $period) + $tr[$i];
            $smoothPlusDM[] = $smoothPlusDM[count($smoothPlusDM)-1] - ($smoothPlusDM[count($smoothPlusDM)-1] / $period) + $plusDM[$i];
            $smoothMinusDM[] = $smoothMinusDM[count($smoothMinusDM)-1] - ($smoothMinusDM[count($smoothMinusDM)-1] / $period) + $minusDM[$i];
        }

        $plusDI = []; $minusDI = []; $dx = [];
        for ($i = 0; $i < count($smoothTR); $i++) {
            $pdi = $smoothTR[$i] == 0 ? 0 : 100 * ($smoothPlusDM[$i] / $smoothTR[$i]);
            $mdi = $smoothTR[$i] == 0 ? 0 : 100 * ($smoothMinusDM[$i] / $smoothTR[$i]);
            $plusDI[] = $pdi; $minusDI[] = $mdi;
            $dx[] = ($pdi + $mdi) == 0 ? 0 : 100 * abs($pdi - $mdi) / ($pdi + $mdi);
        }

        $adx = [];
        if (count($dx) >= $period) {
            $adx[] = array_sum(array_slice($dx, 0, $period)) / $period;
            for ($i = $period; $i < count($dx); $i++) {
                $adx[] = ($adx[count($adx)-1] * ($period - 1) + $dx[$i]) / $period;
            }
        }

        return ['adx' => $adx, 'plus_di' => $plusDI, 'minus_di' => $minusDI];
    }

    /**
     * Calculate Commodity Channel Index (CCI).
     */
    public function calculateCCI(array $klines, int $period = 20): array
    {
        $cci = [];
        if (count($klines) < $period) return $cci;

        $typicalPrices = array_map(fn($k) => ($k['high'] + $k['low'] + $k['close']) / 3, $klines);

        for ($i = $period - 1; $i < count($typicalPrices); $i++) {
            $slice = array_slice($typicalPrices, $i - $period + 1, $period);
            $sma = array_sum($slice) / $period;
            
            $meanDeviation = array_sum(array_map(fn($price) => abs($price - $sma), $slice)) / $period;
            $cci[] = $meanDeviation == 0 ? 0 : ($typicalPrices[$i] - $sma) / (0.015 * $meanDeviation);
        }

        return $cci;
    }

    /**
     * Calculate Williams %R.
     */
    public function calculateWilliamsR(array $klines, int $period = 14): array
    {
        $wr = [];
        if (count($klines) < $period) return $wr;

        for ($i = $period - 1; $i < count($klines); $i++) {
            $slice = array_slice($klines, $i - $period + 1, $period);
            $highestHigh = max(array_column($slice, 'high'));
            $lowestLow = min(array_column($slice, 'low'));
            $close = $klines[$i]['close'];

            $wr[] = ($highestHigh - $lowestLow) == 0 ? 0 : (($highestHigh - $close) / ($highestHigh - $lowestLow)) * -100;
        }

        return $wr;
    }

    /**
     * Calculate On-Balance Volume (OBV).
     */
    public function calculateOBV(array $klines): array
    {
        $obv = [0];
        if (count($klines) < 2) return $obv;

        for ($i = 1; $i < count($klines); $i++) {
            $prevClose = $klines[$i-1]['close'];
            $close = $klines[$i]['close'];
            $volume = $klines[$i]['volume'];

            if ($close > $prevClose) {
                $obv[] = end($obv) + $volume;
            } elseif ($close < $prevClose) {
                $obv[] = end($obv) - $volume;
            } else {
                $obv[] = end($obv);
            }
        }

        return $obv;
    }

    /**
     * Calculate Volume Weighted Average Price (VWAP).
     */
    public function calculateVWAP(array $klines): array
    {
        $vwap = [];
        $cumulativeTPV = 0;
        $cumulativeVolume = 0;

        foreach ($klines as $k) {
            $typicalPrice = ($k['high'] + $k['low'] + $k['close']) / 3;
            $volume = $k['volume'];
            $cumulativeTPV += $typicalPrice * $volume;
            $cumulativeVolume += $volume;
            $vwap[] = $cumulativeVolume == 0 ? $typicalPrice : $cumulativeTPV / $cumulativeVolume;
        }

        return $vwap;
    }

    /**
     * Calculate Chaikin Money Flow (CMF).
     */
    public function calculateCMF(array $klines, int $period = 20): array
    {
        $cmf = [];
        if (count($klines) < $period) return $cmf;

        $adValues = [];
        foreach ($klines as $k) {
            $high = $k['high']; $low = $k['low']; $close = $k['close']; $volume = $k['volume'];
            $mfMultiplier = ($high == $low) ? 0 : (($close - $low) - ($high - $close)) / ($high - $low);
            $adValues[] = $mfMultiplier * $volume;
        }

        for ($i = $period - 1; $i < count($klines); $i++) {
            $sumAD = array_sum(array_slice($adValues, $i - $period + 1, $period));
            $sumVol = array_sum(array_column(array_slice($klines, $i - $period + 1, $period), 'volume'));
            $cmf[] = $sumVol == 0 ? 0 : $sumAD / $sumVol;
        }

        return $cmf;
    }

    /**
     * Calculate Ichimoku Cloud.
     */
    public function calculateIchimoku(array $klines, int $tenkanPeriod = 9, int $kijunPeriod = 26, int $senkouBPeriod = 52): array
    {
        $cloud = ['tenkan' => [], 'kijun' => [], 'senkou_a' => [], 'senkou_b' => [], 'chikou' => []];
        if (count($klines) < $senkouBPeriod) return $cloud;

        $getHighLowAvg = function(array $slice) {
            return (max(array_column($slice, 'high')) + min(array_column($slice, 'low'))) / 2;
        };

        for ($i = 0; $i < count($klines); $i++) {
            if ($i >= $tenkanPeriod - 1) $cloud['tenkan'][] = $getHighLowAvg(array_slice($klines, $i - $tenkanPeriod + 1, $tenkanPeriod));
            if ($i >= $kijunPeriod - 1) {
                $kijun = $getHighLowAvg(array_slice($klines, $i - $kijunPeriod + 1, $kijunPeriod));
                $cloud['kijun'][] = $kijun;
                if (!empty($cloud['tenkan'])) {
                    $tenkan = end($cloud['tenkan']);
                    $cloud['senkou_a'][] = ($tenkan + $kijun) / 2;
                }
            }
            if ($i >= $senkouBPeriod - 1) $cloud['senkou_b'][] = $getHighLowAvg(array_slice($klines, $i - $senkouBPeriod + 1, $senkouBPeriod));
            $cloud['chikou'][] = $klines[$i]['close'];
        }

        return $cloud;
    }

    /**
     * Calculate Parabolic SAR.
     */
    public function calculateParabolicSAR(array $klines, float $step = 0.02, float $max = 0.2): array
    {
        $sar = [];
        if (count($klines) < 2) return $sar;

        $isLong = true;
        $af = $step;
        $ep = $klines[0]['high'];
        $sarValue = $klines[0]['low'];

        $sar[] = $sarValue;

        for ($i = 1; $i < count($klines); $i++) {
            $high = $klines[$i]['high'];
            $low = $klines[$i]['low'];
            $sarValue = $sarValue + $af * ($ep - $sarValue);

            if ($isLong) {
                if ($i >= 2) $sarValue = min($sarValue, $klines[$i-1]['low'], $klines[$i-2]['low']);
                if ($low < $sarValue) {
                    $isLong = false; $sarValue = $ep; $ep = $low; $af = $step;
                } else if ($high > $ep) {
                    $ep = $high; $af = min($max, $af + $step);
                }
            } else {
                if ($i >= 2) $sarValue = max($sarValue, $klines[$i-1]['high'], $klines[$i-2]['high']);
                if ($high > $sarValue) {
                    $isLong = true; $sarValue = $ep; $ep = $high; $af = $step;
                } else if ($low < $ep) {
                    $ep = $low; $af = min($max, $af + $step);
                }
            }
            $sar[] = $sarValue;
        }

        return $sar;
    }

    /**
     * Calculate Classic Pivot Points.
     */
    public function calculatePivotPoints(array $klines): array
    {
        if (count($klines) < 2) return [];

        $historical = array_slice($klines, 0, -1);
        $high = max(array_column($historical, 'high'));
        $low = min(array_column($historical, 'low'));
        $close = end($historical)['close'];

        $pivot = ($high + $low + $close) / 3;
        return [
            'pivot' => $pivot,
            'r1' => (2 * $pivot) - $low,
            's1' => (2 * $pivot) - $high,
            'r2' => $pivot + ($high - $low),
            's2' => $pivot - ($high - $low),
            'r3' => $high + 2 * ($pivot - $low),
            's3' => $low - 2 * ($high - $pivot),
        ];
    }

    /**
     * Detect Volume Spike Anomaly.
     */
    public function detectVolumeSpike(array $klines, int $period = 20, float $multiplier = 2.5): string
    {
        if (count($klines) < $period + 1) return 'NEUTRAL';

        $recentKlines = array_slice($klines, -$period - 1);
        $currentKline = array_pop($recentKlines);
        $currentVolume = $currentKline['volume'];
        
        $volumes = array_column($recentKlines, 'volume');
        $avgVolume = array_sum($volumes) / count($volumes);

        if ($currentVolume > ($avgVolume * $multiplier)) {
            $isBullishCandle = $currentKline['close'] > $currentKline['open'];
            return $isBullishCandle ? 'BULLISH_SPIKE' : 'BEARISH_SPIKE';
        }

        return 'NEUTRAL';
    }

    /**
     * Detect Bullish/Bearish Divergence between Price and Oscillator.
     */
    public function detectDivergence(array $closes, array $oscillatorValues, int $lookback = 10): string
    {
        if (count($closes) < $lookback || count($oscillatorValues) < $lookback) return 'NEUTRAL';

        $recentCloses = array_slice($closes, -$lookback);
        $recentOsc = array_slice($oscillatorValues, -$lookback);

        $priceLowIdx = array_keys($recentCloses, min($recentCloses))[0];
        $priceHighIdx = array_keys($recentCloses, max($recentCloses))[0];

        $oscLowIdx = array_keys($recentOsc, min($recentOsc))[0];
        $oscHighIdx = array_keys($recentOsc, max($recentOsc))[0];

        // Bullish Divergence: Price Lower Low, Oscillator Higher Low
        if ($recentCloses[$lookback-1] < $recentCloses[0] && $recentOsc[$lookback-1] > $recentOsc[0]) {
            return 'BULLISH_DIVERGENCE';
        }

        // Bearish Divergence: Price Higher High, Oscillator Lower High
        if ($recentCloses[$lookback-1] > $recentCloses[0] && $recentOsc[$lookback-1] < $recentOsc[0]) {
            return 'BEARISH_DIVERGENCE';
        }

        return 'NEUTRAL';
    }

    /**
     * Detect Candlestick Patterns (Engulfing, Hammer, Shooting Star).
     */
    public function detectCandlestickPatterns(array $klines): array
    {
        $patterns = [];
        if (count($klines) < 2) return $patterns;

        $current = end($klines);
        $previous = $klines[count($klines) - 2];

        $currOpen = $current['open']; $currClose = $current['close']; $currHigh = $current['high']; $currLow = $current['low'];
        $prevOpen = $previous['open']; $prevClose = $previous['close'];

        // 1. Engulfing
        if ($currClose > $currOpen && $prevClose < $prevOpen && $currClose > $prevOpen && $currOpen < $prevClose) {
            $patterns[] = 'BULLISH_ENGULFING';
        } elseif ($currClose < $currOpen && $prevClose > $prevOpen && $currClose < $prevOpen && $currOpen > $prevClose) {
            $patterns[] = 'BEARISH_ENGULFING';
        }

        // 2. Hammer / Shooting Star
        $bodySize = abs($currClose - $currOpen);
        $candleHeight = $currHigh - $currLow;
        if ($candleHeight > 0) {
            $upperShadow = $currHigh - max($currOpen, $currClose);
            $lowerShadow = min($currOpen, $currClose) - $currLow;

            // Hammer: Small body, long lower shadow
            if ($lowerShadow > (2 * $bodySize) && $upperShadow < (0.2 * $candleHeight)) {
                $patterns[] = 'HAMMER';
            }
            // Shooting Star: Small body, long upper shadow
            if ($upperShadow > (2 * $bodySize) && $lowerShadow < (0.2 * $candleHeight)) {
                $patterns[] = 'SHOOTING_STAR';
            }
        }

        return $patterns;
    }

    /**
     * Calculate Fibonacci Pivot Points.
     */
    public function calculateFibonacciPivots(array $klines): array
    {
        if (count($klines) < 2) return [];

        $historical = array_slice($klines, 0, -1);
        $high = max(array_column($historical, 'high'));
        $low = min(array_column($historical, 'low'));
        $close = end($historical)['close'];

        $pivot = ($high + $low + $close) / 3;
        $range = $high - $low;

        return [
            'pivot' => $pivot,
            'r1' => $pivot + (0.382 * $range),
            's1' => $pivot - (0.382 * $range),
            'r2' => $pivot + (0.618 * $range),
            's2' => $pivot - (0.618 * $range),
            'r3' => $pivot + (1.000 * $range),
            's3' => $pivot - (1.000 * $range),
        ];
    }

    /**
     * Calculate Hurst Exponent to determine if series is trending, mean-reverting, or random walk.
     * H < 0.5: Mean-reverting (Anti-persistent)
     * H = 0.5: Random Walk (Brownian Motion)
     * H > 0.5: Trending (Persistent)
     */
    public function calculateHurstExponent(array $closes, int $minWindow = 8): float
    {
        $n = count($closes);
        if ($n < 32) return 0.5; // Not enough data for reliable Hurst

        $maxWindow = floor($n / 2);
        $rsValues = [];
        $windows = [];

        // Simple R/S analysis
        for ($w = $minWindow; $w <= $maxWindow; $w *= 2) {
            $numSubsets = floor($n / $w);
            $subsetRS = [];
            
            for ($s = 0; $s < $numSubsets; $s++) {
                $slice = array_slice($closes, $s * $w, $w);
                $mean = array_sum($slice) / $w;
                
                // Mean centered
                $y = array_map(fn($val) => $val - $mean, $slice);
                
                // Cumulative deviation
                $z = [0];
                foreach ($y as $val) $z[] = end($z) + $val;
                
                $range = max($z) - min($z);
                
                // Standard deviation
                $variance = array_sum(array_map(fn($val) => pow($val - $mean, 2), $slice)) / $w;
                $sd = sqrt($variance);
                
                if ($sd > 0) {
                    $subsetRS[] = $range / $sd;
                }
            }
            
            if (!empty($subsetRS)) {
                $rsValues[] = log(array_sum($subsetRS) / count($subsetRS));
                $windows[] = log($w);
            }
        }

        if (count($windows) < 2) return 0.5;

        // Linear regression on log(RS) vs log(Window)
        $nPoints = count($windows);
        $sumX = array_sum($windows);
        $sumY = array_sum($rsValues);
        $sumXY = 0;
        $sumXX = 0;
        for ($i = 0; $i < $nPoints; $i++) {
            $sumXY += $windows[$i] * $rsValues[$i];
            $sumXX += $windows[$i] * $windows[$i];
        }

        $slope = ($nPoints * $sumXY - $sumX * $sumY) / ($nPoints * $sumXX - $sumX * $sumX);
        return $slope;
    }

    /**
     * Calculate Fractal Dimension (Efficiency Index variant) to measure market complexity.
     */
    public function calculateFractalDimension(array $closes, int $period = 30): float
    {
        if (count($closes) < $period) return 1.5;

        $slice = array_slice($closes, -$period);
        $totalPath = 0;
        for ($i = 1; $i < count($slice); $i++) {
            $totalPath += abs($slice[$i] - $slice[$i - 1]);
        }

        $range = max($slice) - min($slice);
        
        if ($totalPath == 0) return 1.0;
        
        // Fractal dimension formula approximation
        $efficiencyIndex = $range / $totalPath;
        // Map EI to Dimension (Simplified)
        return 2.0 - $efficiencyIndex;
    }

    /**
     * Apply Z-Score normalization to a series.
     */
    public function applyZScore(array $values, int $period = 20): array
    {
        $zscores = [];
        if (count($values) < $period) return $zscores;

        for ($i = $period - 1; $i < count($values); $i++) {
            $slice = array_slice($values, $i - $period + 1, $period);
            $mean = array_sum($slice) / $period;
            $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $slice)) / $period;
            $sd = sqrt($variance);
            
            $zscores[] = ($sd == 0) ? 0 : ($values[$i] - $mean) / $sd;
        }

        return $zscores;
    }

    /**
     * Calculate Chandelier Exit for volatility-based trail.
     */
    public function calculateChandelierExit(array $klines, int $period = 22, float $multiplier = 3.0): array
    {
        $atrHist = $this->calculateATR($klines, $period);
        if (empty($atrHist)) return ['long' => [], 'short' => []];

        $exits = ['long' => [], 'short' => []];
        $offset = count($klines) - count($atrHist);

        for ($i = 0; $i < count($atrHist); $i++) {
            $currentKlines = array_slice($klines, $i + $offset - $period + 1, $period);
            if (empty($currentKlines)) continue;

            $highestHigh = max(array_column($currentKlines, 'high'));
            $lowestLow = min(array_column($currentKlines, 'low'));
            $currentAtr = $atrHist[$i];

            $exits['long'][] = $highestHigh - ($currentAtr * $multiplier);
            $exits['short'][] = $lowestLow + ($currentAtr * $multiplier);
        }

        return $exits;
    }

    /**
     * 1D Kalman Filter for price denoising.
     */
    public function calculateKalmanFilter(array $values): array
    {
        if (empty($values)) return [];

        $kalman = [];
        $q = 0.01; // Process noise
        $r = 0.1;  // Measurement noise
        $x = $values[0]; // Initial state
        $p = 1.0;  // Initial error covariance

        foreach ($values as $val) {
            // Prediction
            $p = $p + $q;
            
            // Measurement update (Kalman Gain)
            $k = $p / ($p + $r);
            $x = $x + $k * ($val - $x);
            $p = (1 - $k) * $p;
            
            $kalman[] = $x;
        }

        return $kalman;
    }

    /**
     * SuperTrend Indicator.
     */
    public function calculateSuperTrend(array $klines, int $period = 10, float $multiplier = 3.0): array
    {
        $atrHist = $this->calculateATR($klines, $period);
        if (count($klines) < $period + 1) return [];

        $results = [];
        $offset = count($klines) - count($atrHist);
        
        $upperBandArr = [];
        $lowerBandArr = [];
        $trend = []; // 1 for Long, -1 for Short

        for ($i = 0; $i < count($atrHist); $i++) {
            // Index in original klines
            $idx = $i + $offset;
            $currentAtr = $atrHist[$i];
            $hl2 = ($klines[$idx]['high'] + $klines[$idx]['low']) / 2;

            $upperBand = $hl2 + ($multiplier * $currentAtr);
            $lowerBand = $hl2 - ($multiplier * $currentAtr);

            // Final Bands logic
            if ($i > 0) {
                $prevUpper = $upperBandArr[$i-1];
                $prevLower = $lowerBandArr[$i-1];
                $prevClose = $klines[$idx-1]['close'];

                if ($upperBand < $prevUpper || $prevClose > $prevUpper) {
                    // Stay or lower the upper band
                } else {
                    $upperBand = $prevUpper;
                }

                if ($lowerBand > $prevLower || $prevClose < $prevLower) {
                    // Stay or raise the lower band
                } else {
                    $lowerBand = $prevLower;
                }
            }

            $upperBandArr[] = $upperBand;
            $lowerBandArr[] = $lowerBand;

            // Trend determination
            if ($i == 0) {
                $trend[] = 1;
            } else {
                $prevTrend = $trend[$i-1];
                if ($prevTrend == 1 && $klines[$idx]['close'] < $lowerBandArr[$i]) {
                    $trend[] = -1;
                } elseif ($prevTrend == -1 && $klines[$idx]['close'] > $upperBandArr[$i]) {
                    $trend[] = 1;
                } else {
                    $trend[] = $prevTrend;
                }
            }

            $currentTrend = end($trend);
            $results[] = [
                'time' => $klines[$idx]['time'],
                'value' => ($currentTrend == 1) ? $lowerBand : $upperBand,
                'trend' => $currentTrend
            ];
        }

        return $results;
    }

    /**
     * Keltner Channels (EMA-based bands).
     */
    public function calculateKeltnerChannels(array $klines, int $period = 20, float $multiplier = 1.5): array
    {
        $closes = array_column($klines, 'close');
        $ema = $this->calculateEMA($closes, $period);
        $atrHist = $this->calculateATR($klines, $period);

        if (empty($ema) || empty($atrHist)) return ['upper' => [], 'middle' => [], 'lower' => []];

        $results = ['upper' => [], 'middle' => [], 'lower' => []];
        $emaOffset = count($closes) - count($ema);
        $atrOffset = count($klines) - count($atrHist);

        for ($i = 0; $i < count($atrHist); $i++) {
            $eVal = $ema[$i + ($atrOffset - $emaOffset)];
            $aVal = $atrHist[$i];

            $results['upper'][]  = $eVal + ($multiplier * $aVal);
            $results['middle'][] = $eVal;
            $results['lower'][]  = $eVal - ($multiplier * $aVal);
        }

        return $results;
    }

    /**
     * Squeeze Momentum Indicator (TTM Squeeze style).
     * Returns an array of objects indicating if the market is in a squeeze.
     */
    public function calculateSqueezeMomentum(array $klines): array
    {
        $closes = array_column($klines, 'close');
        if (count($closes) < 20) return [];

        $bb = $this->calculateBollingerBands($closes, 20, 2.0);
        $kc = $this->calculateKeltnerChannels($klines, 20, 1.5);

        if (empty($bb['upper']) || empty($kc['upper'])) return [];

        $results = [];
        $bbOffset = count($closes) - count($bb['upper']);
        $kcOffset = count($klines) - count($kc['upper']);
        
        // Aligning results to the shortest series
        $len = min(count($bb['upper']), count($kc['upper']));
        
        for ($i = 0; $i < $len; $i++) {
            $bbUpper = $bb['upper'][$i + (count($bb['upper']) - $len)];
            $bbLower = $bb['lower'][$i + (count($bb['lower']) - $len)];
            $kcUpper = $kc['upper'][$i + (count($kc['upper']) - $len)];
            $kcLower = $kc['lower'][$i + (count($kc['lower']) - $len)];
            
            // Squeeze is ON when Bollinger Bands are inside Keltner Channels
            $isSqueeze = ($bbUpper < $kcUpper) && ($bbLower > $kcLower);
            
            // Momentum: Linear regression slope of (Price - HL2/EMA Pivot)
            // Simplified here: Distance from middle band
            $momentum = $closes[count($closes) - $len + $i] - $bb['middle'][$i + (count($bb['middle']) - $len)];
            
            $results[] = [
                'is_squeeze' => $isSqueeze,
                'momentum'   => $momentum,
                'direction'  => $momentum > 0 ? 1 : -1
            ];
        }

        return $results;
    }

    /**
     * Calculate ATR-based Trailing Stop.
     */
    public function calculateTrailingStopATR(array $klines, string $side = 'BUY', float $multiplier = 2.0): float
    {
        $atrArr = $this->calculateATR($klines, 14);
        if (empty($atrArr)) return 0;

        $atr = end($atrArr);
        $lastClose = end($klines)['close'];

        if (strtoupper($side) === 'BUY') {
            return $lastClose - ($atr * $multiplier);
        } else {
            return $lastClose + ($atr * $multiplier);
        }
    }

    /**
     * Calculate Dynamic High-Accuracy Take Profit and Stop Loss levels.
     * Uses ATR for volatility and Fibonacci Pivots for structural alignment.
     */
    public function calculateDynamicTPBL(array $klines, string $side = 'BUY'): array
    {
        $lastClose = end($klines)['close'];
        $atrHist = $this->calculateATR($klines, 14);
        $atr = !empty($atrHist) ? end($atrHist) : ($lastClose * 0.015);
        $pivots = $this->calculateFibonacciPivots($klines);

        if (empty($pivots)) {
            // Fallback to basic ATR multipliers if pivots fail
            $tp = $lastClose + ($side === 'BUY' ? 2 * $atr : -2 * $atr);
            $sl = $lastClose + ($side === 'BUY' ? -1.5 * $atr : 1.5 * $atr);
            return ['tp' => $tp, 'sl' => $sl, 'method' => 'ATR_ONLY'];
        }

        if (strtoupper($side) === 'BUY') {
            // SL: Ideally just below S1, or 1.5*ATR if price is far from S1
            $sl = min($lastClose - (1.2 * $atr), $pivots['s1'] * 0.998);
            // TP: Ideally at R1 or R2
            $tp = max($lastClose + (2.0 * $atr), $pivots['r1']);
            // If R1 is too close (RR < 1), target R2
            if (($tp - $lastClose) < ($lastClose - $sl)) {
                $tp = $pivots['r2'];
            }
        } else {
            // SL: Ideally just above R1
            $sl = max($lastClose + (1.2 * $atr), $pivots['r1'] * 1.002);
            // TP: Ideally at S1 or S2
            $tp = min($lastClose - (2.0 * $atr), $pivots['s1']);
            if (($lastClose - $tp) < ($sl - $lastClose)) {
                $tp = $pivots['s2'];
            }
        }

        return [
            'tp' => round($tp, 6),
            'sl' => round($sl, 6),
            'pivots' => $pivots,
            'atr' => round($atr, 6),
            'method' => 'DYNAMIC_STRUCTURAL'
        ];
    }
}
