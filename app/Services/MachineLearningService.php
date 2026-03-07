<?php

namespace App\Services;

class MachineLearningService
{
    /**
     * Compute a Real ML Forecast using XGBoost (Python).
     */
    public function predictXGBoost(array $klines, int $forecastSteps = 5): array
    {
        $input = [
            'klines' => $klines,
            'steps'  => $forecastSteps
        ];

        $jsonInput = json_encode($input);
        
        // Define path to python script
        $scriptPath = base_path('app/ML/xgboost_engine.py');
        
        // Using proc_open for safer execution and reading stdout/stderr
        $descriptorspec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];

        // Ensure we use the correct python command (python or python3)
        $process = proc_open("python \"{$scriptPath}\"", $descriptorspec, $pipes);

        if (is_resource($process)) {
            fwrite($pipes[0], $jsonInput);
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $returnValue = proc_close($process);

            if ($returnValue === 0) {
                $output = json_decode($stdout, true);
                if (isset($output['error'])) {
                    return ['error' => $output['error'], 'forecast' => [], 'r_squared' => 0];
                }
                return $output;
            } else {
                return [
                    'error' => "Process exited with code {$returnValue}. Stderr: {$stderr}",
                    'forecast' => [],
                    'r_squared' => 0
                ];
            }
        }

        return ['error' => 'Failed to open process', 'forecast' => [], 'r_squared' => 0];
    }

    /**
     * Compute a Linear Regression Forecast for a series (Legacy/Fallback).
     */
    public function predictLinearRegression(array $values, int $forecastPeriod = 5): array
    {
        $n = count($values);
        if ($n < 10) return ['forecast' => [], 'slope' => 0, 'r_squared' => 0];

        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0; $sumYY = 0;
        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $values[$i];
            $sumXY += $i * $values[$i];
            $sumXX += $i * $i;
            $sumYY += $values[$i] * $values[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // R-Squared (Confidence of fit)
        $rNumerator = ($n * $sumXY - $sumX * $sumY);
        $rDenominator = sqrt(($n * $sumXX - $sumX * $sumX) * ($n * $sumYY - $sumY * $sumY));
        $rSquared = ($rDenominator == 0) ? 0 : pow($rNumerator / $rDenominator, 2);

        $forecast = [];
        for ($j = 1; $j <= $forecastPeriod; $j++) {
            $forecast[] = $intercept + $slope * ($n + $j - 1);
        }

        return [
            'forecast' => $forecast,
            'slope' => $slope,
            'r_squared' => $rSquared,
            'last_fitted' => $intercept + $slope * ($n - 1)
        ];
    }

    /**
     * Run Monte Carlo Simulation to project future price paths.
     * Uses Brownian Motion (Geometric) or Log-Normal approach.
     */
    public function runMonteCarlo(array $closes, int $steps = 20, int $simulations = 1000): array
    {
        $n = count($closes);
        if ($n < 2) return [];

        $returns = [];
        for ($i = 1; $i < $n; $i++) {
            $returns[] = log($closes[$i] / $closes[$i - 1]);
        }

        $mean = array_sum($returns) / count($returns);
        $variance = array_sum(array_map(fn($r) => pow($r - $mean, 2), $returns)) / count($returns);
        $stdDev = sqrt($variance);

        $currentPrice = end($closes);
        $allPaths = [];

        for ($s = 0; $s < $simulations; $s++) {
            $path = [$currentPrice];
            $tempPrice = $currentPrice;
            
            for ($t = 0; $t < $steps; $t++) {
                // drift = (mean - 0.5 * sigma^2)
                $drift = $mean - (0.5 * $variance);
                // shock = sigma * Z
                $shock = $stdDev * $this->generateGaussianNoise();
                
                $tempPrice = $tempPrice * exp($drift + $shock);
                $path[] = $tempPrice;
            }
            $allPaths[] = $path;
        }

        // Aggregate results (Percentiles)
        $finalPrices = array_map(fn($p) => end($p), $allPaths);
        sort($finalPrices);

        return [
            'median' => $finalPrices[floor($simulations * 0.5)],
            'p10' => $finalPrices[floor($simulations * 0.1)],
            'p90' => $finalPrices[floor($simulations * 0.9)],
            'p25' => $finalPrices[floor($simulations * 0.25)],
            'p75' => $finalPrices[floor($simulations * 0.75)],
            'standard_deviation' => $stdDev,
            'expected_return' => $mean
        ];
    }

    /**
     * Generate Gaussian (Normal) random noise using Box-Muller transform.
     */
    private function generateGaussianNoise(): float
    {
        $u1 = (float)mt_rand() / (float)mt_getrandmax();
        $u2 = (float)mt_rand() / (float)mt_getrandmax();
        
        return sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    }
}
