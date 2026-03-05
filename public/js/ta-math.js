/**
 * Simple Technical Analysis Math Library
 * Calculates SMA, EMA, RSI, MACD, and Bollinger Bands
 * Input is usually an array of numbers (close prices).
 */

class TAMath {

    // Simple Moving Average
    static sma(data, period) {
        if (data.length < period) return [];
        let result = new Array(data.length).fill(null);
        let sum = 0;
        for (let i = 0; i < data.length; i++) {
            sum += data[i];
            if (i >= period) sum -= data[i - period];
            if (i >= period - 1) result[i] = sum / period;
        }
        return result;
    }

    // Exponential Moving Average
    static ema(data, period) {
        if (data.length < period || period <= 0) return [];
        let result = new Array(data.length).fill(null);
        let multiplier = 2 / (period + 1);

        // Use SMA for first valid point
        let sum = 0;
        for (let i = 0; i < period; i++) sum += data[i];
        result[period - 1] = sum / period;

        for (let i = period; i < data.length; i++) {
            result[i] = (data[i] - result[i - 1]) * multiplier + result[i - 1];
        }
        return result;
    }

    // Relative Strength Index
    static rsi(data, period = 14) {
        if (data.length < period + 1) return [];
        let result = new Array(data.length).fill(null);

        let gains = 0;
        let losses = 0;

        for (let i = 1; i <= period; i++) {
            let diff = data[i] - data[i - 1];
            if (diff >= 0) gains += diff;
            else losses -= diff;
        }

        let avgGain = gains / period;
        let avgLoss = losses / period;

        if (avgLoss === 0) {
            result[period] = 100;
        } else {
            let rs = avgGain / avgLoss;
            result[period] = 100 - (100 / (1 + rs));
        }

        for (let i = period + 1; i < data.length; i++) {
            let diff = data[i] - data[i - 1];
            let gain = diff >= 0 ? diff : 0;
            let loss = diff < 0 ? -diff : 0;

            avgGain = (avgGain * (period - 1) + gain) / period;
            avgLoss = (avgLoss * (period - 1) + loss) / period;

            if (avgLoss === 0) {
                result[i] = 100;
            } else {
                let rs = avgGain / avgLoss;
                result[i] = 100 - (100 / (1 + rs));
            }
        }
        return result;
    }

    // MACD
    static macd(data, fastPeriod = 12, slowPeriod = 26, signalPeriod = 9) {
        let emaFast = this.ema(data, fastPeriod);
        let emaSlow = this.ema(data, slowPeriod);

        let macdLine = new Array(data.length).fill(null);
        let macdLineValid = [];
        let macdLineValidIndices = [];

        for (let i = 0; i < data.length; i++) {
            if (emaFast[i] !== null && emaSlow[i] !== null) {
                macdLine[i] = emaFast[i] - emaSlow[i];
                macdLineValid.push(macdLine[i]);
                macdLineValidIndices.push(i);
            }
        }

        let signalLineValid = this.ema(macdLineValid, signalPeriod);
        let signalLine = new Array(data.length).fill(null);
        let histogram = new Array(data.length).fill(null);

        for (let i = 0; i < signalLineValid.length; i++) {
            let originalIndex = macdLineValidIndices[i];
            signalLine[originalIndex] = signalLineValid[i];

            if (signalLine[originalIndex] !== null && macdLine[originalIndex] !== null) {
                histogram[originalIndex] = macdLine[originalIndex] - signalLine[originalIndex];
            }
        }

        return { macdLine, signalLine, histogram };
    }

    // Bollinger Bands
    static bollingerBands(data, period = 20, stdDevMult = 2) {
        let sma = this.sma(data, period);
        let upper = new Array(data.length).fill(null);
        let lower = new Array(data.length).fill(null);

        for (let i = period - 1; i < data.length; i++) {
            let sumSq = 0;
            for (let j = 0; j < period; j++) {
                let diff = data[i - j] - sma[i];
                sumSq += diff * diff;
            }
            let variance = sumSq / period;
            let stdDev = Math.sqrt(variance);

            upper[i] = sma[i] + (stdDevMult * stdDev);
            lower[i] = sma[i] - (stdDevMult * stdDev);
        }

        return { upper, middle: sma, lower };
    }
}
window.TAMath = TAMath;
