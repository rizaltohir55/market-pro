<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class MachineLearningService
{
    /**
     * Compute a Real ML Forecast using XGBoost (Python).
     */
    public function predictXGBoost(array $klines, int $forecastSteps = 5): array
    {
        $modelDir = storage_path('app/ml_models');
        if (!file_exists($modelDir)) {
            mkdir($modelDir, 0755, true);
        }

        $inputData = [
            'klines' => $klines,
            'steps'  => $forecastSteps,
            'model_dir' => $modelDir
        ];
        
        // 1. Check for Microservice HTTP call
        $mlUrl = config('services.ml.url');
        if ($mlUrl) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-ML-Key' => config('services.ml.key'),
                ])->timeout(30)->post($mlUrl . '/predict', $inputData);
                
                if ($response->successful()) {
                    return $response->json();
                }
                \Illuminate\Support\Facades\Log::warning("ML Service HTTP failed: " . $response->status());
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("ML Service HTTP exception: " . $e->getMessage());
            }
        }

        $jsonInput = json_encode($inputData);
        
        // 2. Fallback to Local proc_open
        $scriptPath = base_path('app/ML/xgboost_engine.py');
        
        // Using Symfony Process for safer execution, timeouts, and reading stdout
        $python = $this->getPythonCommand();
        $process = new Process([$python, $scriptPath]);
        $process->setInput($jsonInput);
        $process->setTimeout(60);

        try {
            $process->mustRun();
            $output = $this->extractJson($process->getOutput());
            if (!$output) {
                return [
                    'error' => "Failed to parse JSON from Python output. Raw: " . substr($process->getOutput(), 0, 500),
                    'forecast' => [],
                    'r_squared' => 0
                ];
            }
            if (isset($output['error'])) {
                return ['error' => $output['error'], 'forecast' => [], 'r_squared' => 0];
            }
            return $output;
        } catch (ProcessFailedException $e) {
            $msg = app()->environment('production') ? 'Prediction engine failed' : $e->getMessage();
            return [
                'error' => 'Process failed or timed out: ' . $msg,
                'forecast' => [],
                'r_squared' => 0
            ];
        }
    }

    /**
     * Compute batch real ML Forecasts using XGBoost (Python).
     */
    public function predictBatchXGBoost(array $batchInput, int $forecastSteps = 5): array
    {
        $modelDir = storage_path('app/ml_models');
        if (!file_exists($modelDir)) {
            mkdir($modelDir, 0755, true);
        }

        $inputData = [
            'batch' => $batchInput,
            'steps' => $forecastSteps,
            'model_dir' => $modelDir
        ];

        // 1. Check for Microservice HTTP call
        $mlUrl = config('services.ml.url');
        if ($mlUrl) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-ML-Key' => config('services.ml.key'),
                ])->timeout(60)->post($mlUrl . '/predict/batch', $inputData);
                
                if ($response->successful()) {
                    return $response->json();
                }
                \Illuminate\Support\Facades\Log::warning("ML Service Batch HTTP failed: " . $response->status());
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("ML Service Batch HTTP exception: " . $e->getMessage());
            }
        }

        $jsonInput = json_encode($inputData);
        $scriptPath = base_path('app/ML/xgboost_engine.py');
        
        $python = $this->getPythonCommand();
        $process = new Process([$python, $scriptPath]);
        $process->setInput($jsonInput);
        $process->setTimeout(120); // Batch process might take longer

        try {
            $process->mustRun();
            return $this->extractJson($process->getOutput()) ?? ['error' => 'Invalid JSON output from Python. Raw: ' . substr($process->getOutput(), 0, 200)];
        } catch (ProcessFailedException $e) {
            $msg = app()->environment('production') ? 'Batch prediction engine failed' : $e->getMessage();
            return ['error' => 'Batch process failed or timed out: ' . $msg];
        }
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

    /**
     * Extracts the first valid JSON object or array from a string.
     * Prevents issues where Python warnings pollute stdout.
     */
    private function extractJson(string $input): ?array
    {
        $input = trim($input);
        
        // Try direct decode first
        $decoded = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Search for JSON structure if direct decode fails
        // Finds first { or [ and last } or ]
        $firstBrace = strpos($input, '{');
        $firstBracket = strpos($input, '[');
        
        $startPos = false;
        if ($firstBrace !== false && ($firstBracket === false || $firstBrace < $firstBracket)) {
            $startPos = $firstBrace;
            $endChar = '}';
        } elseif ($firstBracket !== false) {
            $startPos = $firstBracket;
            $endChar = ']';
        }

        if ($startPos !== false) {
            $lastPos = strrpos($input, $endChar);
            if ($lastPos !== false && $lastPos > $startPos) {
                $jsonString = substr($input, $startPos, $lastPos - $startPos + 1);
                $decoded = json_decode($jsonString, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        \Illuminate\Support\Facades\Log::error("ML Service: Robust JSON parsing failed. Original input length: " . strlen($input));
        return null;
    }

    /**
     * Detect the available Python command in the environment.
     */
    private function getPythonCommand(): string
    {
        static $cachedCommand = null;
        if ($cachedCommand !== null) return $cachedCommand;

        // Priority list: python3.13 (found in tasklist), python3, python
        $commands = ['python3.13', 'python3', 'python'];
        
        foreach ($commands as $cmd) {
            $check = PHP_OS_FAMILY === 'Windows' ? "where $cmd" : "which $cmd";
            $output = [];
            $returnVar = 0;
            exec($check . ' 2>&1', $output, $returnVar);
            
            if ($returnVar === 0 && !empty($output)) {
                // Windows 'where' can return multiple lines, take the first one.
                // We MUST return the absolute path for proc_open array syntax to work on Windows.
                $exactPath = trim($output[0]);
                $cachedCommand = $exactPath;
                return $exactPath;
            }
        }

        $cachedCommand = 'python'; // Default fallback
        return 'python';
    }
}
