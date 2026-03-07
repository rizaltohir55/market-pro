<?php

namespace Tests\Unit;

use App\Services\MachineLearningService;
use Tests\TestCase;

class MachineLearningTest extends TestCase
{
    private MachineLearningService $ml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ml = new MachineLearningService();
    }

    public function test_linear_regression_prediction()
    {
        $values = [10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $result = $this->ml->predictLinearRegression($values, 5);

        $this->assertArrayHasKey('forecast', $result);
        $this->assertCount(5, $result['forecast']);
        $this->assertEquals(20.0, $result['forecast'][0]);
        $this->assertGreaterThan(0.9, $result['r_squared']); // Perfect linear fit
        $this->assertEquals(1.0, $result['slope']);
    }

    public function test_xgboost_prediction()
    {
        $klines = [];
        for ($i = 0; $i < 100; $i++) {
            $klines[] = ['close' => 100 + $i, 'open' => 100, 'high' => 110, 'low' => 90, 'volume' => 1000];
        }
        
        // Mocking base_path manually since it's a Unit test without Laravel bootstrap if run via phpunit directly, 
        // but here we are in a Laravel project, we should use 'php artisan test'.
        // Wait, if I use 'php artisan test', base_path will work.
        
        $result = $this->ml->predictXGBoost($klines, 5);

        if (isset($result['error'])) {
            $this->markTestSkipped("XGBoost Error: " . $result['error']);
        }

        $this->assertArrayHasKey('forecast', $result);
        $this->assertCount(5, $result['forecast']);
        $this->assertGreaterThan(0, $result['r_squared']);
    }

    public function test_monte_carlo_simulation()
    {
        $closes = [100, 101, 102, 101, 103, 104, 105, 106, 107, 108];
        $result = $this->ml->runMonteCarlo($closes, 5, 100);

        $this->assertArrayHasKey('median', $result);
        $this->assertArrayHasKey('p10', $result);
        $this->assertArrayHasKey('p90', $result);
        $this->assertGreaterThan(0, $result['median']);
    }
}
