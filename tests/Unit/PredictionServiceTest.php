<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PredictionService;
use App\Services\TechnicalAnalysisService;
use App\Services\EconomicCalendarService;
use App\Services\MachineLearningService;
use Mockery;

class PredictionServiceTest extends TestCase
{
    public function test_get_scalping_signal_runs_without_undefined_variable_error()
    {
        $ta = Mockery::mock(TechnicalAnalysisService::class);
        $calendar = Mockery::mock(EconomicCalendarService::class);
        $ml = Mockery::mock(MachineLearningService::class);

        $klines = [];
        for ($i = 0; $i < 300; $i++) {
            $klines[] = [
                'open' => 100, 'high' => 105, 'low' => 95, 'close' => 100, 'volume' => 1000, 'timestamp' => time()
            ];
        }

        // Mocking all the TA calls that calculateSignal makes
        $ta->shouldReceive('calculateKalmanFilter')->andReturn(array_column($klines, 'close'));
        $ta->shouldReceive('calculateSuperTrend')->andReturn([['trend' => 1, 'value' => 100]]);
        $ta->shouldReceive('calculateADX')->andReturn(['adx' => [35], 'plus_di' => [30], 'minus_di' => [20]]);
        $ta->shouldReceive('calculateEMA')->andReturn([100]);
        $ta->shouldReceive('calculateIchimoku')->andReturn(['senkou_a' => [100], 'senkou_b' => [90]]);
        $ta->shouldReceive('calculateParabolicSAR')->andReturn([95]);
        $ta->shouldReceive('calculateRSI')->andReturn([50]);
        $ta->shouldReceive('detectDivergence')->andReturn('NEUTRAL');
        $ta->shouldReceive('calculateStochastic')->andReturn(['k' => [50], 'd' => [45]]);
        $ta->shouldReceive('detectCandlestickPatterns')->andReturn([]);
        $ta->shouldReceive('calculateMACD')->andReturn(['macd' => [1], 'signal' => [0.5], 'histogram' => [0.5]]);
        $ta->shouldReceive('calculateOBV')->andReturn([1000, 1100]);
        $ta->shouldReceive('calculateVWAP')->andReturn([100]);
        $ta->shouldReceive('calculateCMF')->andReturn([0.15]);
        $ta->shouldReceive('detectVolumeSpike')->andReturn('NEUTRAL');
        $ta->shouldReceive('calculateBollingerBands')->andReturn(['upper' => [110], 'lower' => [90], 'middle' => [100]]);
        $ta->shouldReceive('calculateFibonacciPivots')->andReturn(['s1' => 95, 'r1' => 105, 'r2' => 110, 's2' => 90]);
        $ta->shouldReceive('calculateHurstExponent')->andReturn(0.6);
        $ta->shouldReceive('calculateFractalDimension')->andReturn(1.4);
        $ta->shouldReceive('calculateSqueezeMomentum')->andReturn([['is_squeeze' => false, 'momentum' => 0.1]]);
        
        // This is the one that was causing the error
        $ta->shouldReceive('calculateATR')->with($klines, 14)->andReturn([1.5, 1.6]);
        
        $ta->shouldReceive('calculateDynamicTPBL')->andReturn(['tp' => 110, 'sl' => 90]);
        $ta->shouldReceive('calculateTrailingStopATR')->andReturn(92.0);

        $calendar->shouldReceive('getEconomicCalendar')->andReturn([]);
        
        $ml->shouldReceive('predictXGBoost')->andReturn(['forecast' => [102], 'r_squared' => 0.8]);
        $ml->shouldReceive('predictLinearRegression')->andReturn(['slope' => 0.1, 'r_squared' => 0.8]);
        $ml->shouldReceive('runMonteCarlo')->andReturn(['median' => 103]);

        $service = new PredictionService($ta, $calendar, $ml);
        
        $result = $service->getScalpingSignal($klines, 'BTCUSDT', '15m');
        
        $this->assertArrayHasKey('signal', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
