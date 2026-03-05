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

        // Keep only the most recent and relevant levels
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

        // First ATR is simple average
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
}
