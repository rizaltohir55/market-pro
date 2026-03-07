<?php

namespace Tests\Unit;

use App\Services\TechnicalAnalysisService;
use PHPUnit\Framework\TestCase;

class TechnicalAnalysisTest extends TestCase
{
    private TechnicalAnalysisService $ta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ta = new TechnicalAnalysisService();
    }

    public function test_calculate_ema()
    {
        $closes = [10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $period = 5;
        $result = $this->ta->calculateEMA($closes, $period);

        // First value should be SMA: (10+11+12+13+14)/5 = 12
        $this->assertCount(count($closes) - $period + 1, $result);
        $this->assertEquals(12.0, $result[0]);
        $this->assertGreaterThan(12.0, end($result));
    }

    public function test_calculate_rsi()
    {
        $closes = [44.34, 44.09, 44.15, 43.61, 44.33, 44.83, 45.10, 45.42, 45.84, 46.08, 45.89, 46.03, 45.61, 46.28, 46.28, 46.00];
        $result = $this->ta->calculateRSI($closes, 14);

        $this->assertNotEmpty($result);
        $this->assertGreaterThan(0, end($result));
        $this->assertLessThan(100, end($result));
    }

    public function test_calculate_macd()
    {
        $closes = array_fill(0, 50, 100);
        foreach ($closes as $i => &$v) $v += $i; // Upward trend

        $result = $this->ta->calculateMACD($closes);
        
        $this->assertArrayHasKey('macd', $result);
        $this->assertArrayHasKey('signal', $result);
        $this->assertArrayHasKey('histogram', $result);
        $this->assertNotEmpty($result['macd']);
    }

    public function test_calculate_bollinger_bands()
    {
        $closes = array_fill(0, 30, 100);
        $result = $this->ta->calculateBollingerBands($closes, 20);

        $this->assertNotEmpty($result['upper']);
        $this->assertEquals($result['middle'][0], $result['upper'][0]); // No variance = same
    }

    public function test_find_support_resistance()
    {
        $klines = [];
        for ($i = 0; $i < 100; $i++) {
            $klines[] = [
                'high' => 100 + ($i % 10 == 0 ? 10 : 0),
                'low' => 90 - ($i % 5 == 0 ? 10 : 0),
                'close' => 95
            ];
        }

        $result = $this->ta->findSupportResistance($klines, 5);
        $this->assertArrayHasKey('support', $result);
        $this->assertArrayHasKey('resistance', $result);
        $this->assertNotEmpty($result['resistance']);
        $this->assertNotEmpty($result['support']);
    }

    public function test_calculate_atr()
    {
        $klines = array_fill(0, 20, [
            'high' => 110,
            'low' => 100,
            'close' => 105
        ]);
        $result = $this->ta->calculateATR($klines, 14);
        $this->assertNotEmpty($result);
        $this->assertEquals(10.0, $result[0]);
    }

    public function test_calculate_stochastic()
    {
        $klines = array_fill(0, 30, [
            'high' => 110,
            'low' => 100,
            'close' => 105
        ]);
        $result = $this->ta->calculateStochastic($klines);
        $this->assertArrayHasKey('k', $result);
        $this->assertArrayHasKey('d', $result);
    }

    public function test_calculate_adx()
    {
        $klines = array_fill(0, 50, [
            'high' => 110,
            'low' => 100,
            'close' => 105
        ]);
        $result = $this->ta->calculateADX($klines);
        $this->assertArrayHasKey('adx', $result);
    }

    public function test_calculate_pivot_points()
    {
        $klines = [
            ['high' => 110, 'low' => 90, 'close' => 100],
            ['high' => 120, 'low' => 110, 'close' => 115], // Current candle (ignored for calc)
        ];
        $result = $this->ta->calculatePivotPoints($klines);
        
        // Pivot = (110 + 90 + 100) / 3 = 100
        $this->assertEquals(100, $result['pivot']);
        $this->assertEquals(110, $result['r1']);
        $this->assertEquals(90, $result['s1']);
    }

    public function test_detect_volume_spike()
    {
        $klines = array_fill(0, 21, [
            'open' => 100,
            'close' => 105,
            'volume' => 1000,
            'high' => 106,
            'low' => 99
        ]);
        
        // Normal state
        $this->assertEquals('NEUTRAL', $this->ta->detectVolumeSpike($klines));

        // Spike state
        $klines[20]['volume'] = 5000;
        $this->assertEquals('BULLISH_SPIKE', $this->ta->detectVolumeSpike($klines));
    }

    public function test_detect_candlestick_patterns()
    {
        // Bullish Engulfing
        $klines = [
            ['open' => 100, 'close' => 90, 'high' => 101, 'low' => 89], // Bearish
            ['open' => 85, 'close' => 105, 'high' => 106, 'low' => 84], // Bullish Engulfing
        ];
        $patterns = $this->ta->detectCandlestickPatterns($klines);
        $this->assertContains('BULLISH_ENGULFING', $patterns);
    }

    public function test_calculate_hurst_exponent()
    {
        // Brownian motion / Random walk should be ~0.5
        $values = array_map(fn() => mt_rand(900, 1100) / 10, range(0, 100));
        $hurst = $this->ta->calculateHurstExponent($values);
        $this->assertGreaterThan(0.2, $hurst);
        $this->assertLessThan(0.8, $hurst);

        // Trending should be > 0.5
        $trending = range(1, 100);
        $hurstTrend = $this->ta->calculateHurstExponent($trending);
        $this->assertGreaterThan(0.5, $hurstTrend);
    }

    public function test_calculate_fractal_dimension()
    {
        $closes = array_fill(0, 30, 100);
        foreach ($closes as $i => &$v) $v += (mt_rand(-5, 5) / 10);
        
        $fractal = $this->ta->calculateFractalDimension($closes);
        $this->assertGreaterThan(1.0, $fractal);
        $this->assertLessThan(2.0, $fractal);
    }

    public function test_apply_zscore()
    {
        $values = array_fill(0, 50, 100);
        $values[49] = 110; // Spike
        
        $zscores = $this->ta->applyZScore($values, 20);
        $this->assertNotEmpty($zscores);
        $this->assertGreaterThan(2.0, end($zscores)); // Significant spike should have high Z-Score
    }

    public function test_calculate_chandelier_exit()
    {
        $klines = array_fill(0, 50, ['high' => 110, 'low' => 90, 'close' => 100]);
        $result = $this->ta->calculateChandelierExit($klines);
        
        $this->assertArrayHasKey('long', $result);
        $this->assertArrayHasKey('short', $result);
        $this->assertNotEmpty($result['long']);
    }
}
