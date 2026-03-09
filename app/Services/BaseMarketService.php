<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class BaseMarketService
{
    protected string $finnhubBase      = 'https://finnhub.io/api/v1';
    protected string $exchangeRateBase  = 'https://open.er-api.com/v6';
    protected string $fredBase          = 'https://fred.stlouisfed.org/graph/fredgraph.csv';
    protected string $okxBase           = 'https://www.okx.com/api/v5';
    protected string $binanceBase       = 'https://data-api.binance.vision';

    public array $majorStocks = [
        'AAPL', 'MSFT', 'GOOGL', 'AMZN', 'NVDA',
        'META', 'TSLA', 'BRK.B', 'JPM', 'V',
        'TSM', 'ASML', 'SAP', 'BABA', 'NVO',
    ];

    public array $forexPairs = [
        'EUR' => 'EUR/USD',
        'GBP' => 'GBP/USD',
        'JPY' => 'USD/JPY',
        'AUD' => 'AUD/USD',
        'CAD' => 'USD/CAD',
        'CHF' => 'USD/CHF',
        'CNY' => 'USD/CNY',
        'HKD' => 'USD/HKD',
        'SGD' => 'USD/SGD',
        'IDR' => 'USD/IDR',
    ];
    /**
     * Centralized HTTP options for SSL resilience and consistent timeouts.
     */
    protected function getHttpOptions(int $timeout = 5): array
    {
        $verify = (bool) config('services.market.ca_cert', true);
        
        return [
            'verify' => $verify,
            'timeout' => $timeout,
            'connect_timeout' => 3,
            'curl' => [
                CURLOPT_SSL_VERIFYPEER => $verify ? 1 : 0,
                CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
                CURLOPT_FOLLOWLOCATION => true,
            ]
        ];
    }

    /** Standard browser headers to authenticate Yahoo Finance requests */
    protected function browserHeaders(): array
    {
        return [
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept'          => 'application/json',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Referer'         => 'https://finance.yahoo.com/',
        ];
    }

    protected function yahooGet(string $path, array $params = [])
    {
        return Http::withOptions($this->getHttpOptions(8))
            ->withHeaders($this->browserHeaders())
            ->retry(3, 100)
            ->get('https://query1.finance.yahoo.com' . $path, $params);
    }

    /**
     * Internal: Yahoo Finance request with crumb/cookie logic.
     * Overcomes 401/406 by simulating a real browser visit.
     */
    public function yahooGetWithCrumb(string $path, array $params = [])
    {
        $cacheKey = 'yahoo_crumb_cookie';
        $auth = Cache::remember($cacheKey, 3600, function () { // cache for 1 hour
            $verify = (bool) config('services.market.ca_cert', true);
            $caPath = config('services.market.ca_cert');

            try {
                // 1. Get Cookie
                $ch = curl_init('https://fc.yahoo.com');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HEADER, 1);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify ? 1 : 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
                if ($verify && $caPath) {
                    curl_setopt($ch, CURLOPT_CAINFO, $caPath);
                }
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                $res = curl_exec($ch);
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $header = substr($res, 0, $headerSize);
                curl_close($ch);

                preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
                $cookies = [];
                foreach($matches[1] as $item) {
                    parse_str($item, $cookie);
                    $cookies = array_merge($cookies, $cookie);
                }
                $cookieStr = '';
                foreach ($cookies as $k => $v) { $cookieStr .= "$k=$v; "; }
                
                // 2. Get Crumb
                $ch2 = curl_init('https://query1.finance.yahoo.com/v1/test/getcrumb');
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Cookie: $cookieStr"]);
                curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, $verify ? 1 : 0);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
                if ($verify && $caPath) {
                    curl_setopt($ch2, CURLOPT_CAINFO, $caPath);
                }
                curl_setopt($ch2, CURLOPT_TIMEOUT, 6);
                $crumb = curl_exec($ch2);
                $status = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                    
                if ($status == 200 && !empty($crumb)) {
                    return ['cookie' => $cookieStr, 'crumb' => rtrim($crumb)];
                }
            } catch (\Exception $e) {
                Log::warning('Yahoo Crumb fetch failed: ' . $e->getMessage());
            }
            return null;
        });

        if ($auth && !empty($auth['crumb'])) {
            $params['crumb'] = $auth['crumb'];
        }

        $headers = $this->browserHeaders();
        // Overwrite Accept to allow text/html, application/json, etc.
        $headers['Accept'] = '*/*';
        
        if ($auth && !empty($auth['cookie'])) {
            $headers['Cookie'] = $auth['cookie'];
        }

        $url = 'https://query1.finance.yahoo.com' . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch3 = curl_init($url);
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
        
        $headerArray = [];
        foreach ($headers as $k => $v) {
            $headerArray[] = "$k: $v";
        }
        
        $verify = (bool) config('services.market.ca_cert', true);
        curl_setopt($ch3, CURLOPT_HTTPHEADER, $headerArray);
        curl_setopt($ch3, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, $verify ? 1 : 0);
        curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
        if ($verify) {
            curl_setopt($ch3, CURLOPT_CAINFO, config('services.market.ca_cert'));
        }
        curl_setopt($ch3, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, 1);
        $data = curl_exec($ch3);
        $status = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
        curl_close($ch3);
        
        return new class($status, $data) {
            private $st;
            private $dt;
            public function __construct($s, $d) { $this->st = $s; $this->dt = $d; }
            public function successful() { return $this->st >= 200 && $this->st < 300; }
            public function json() { return json_decode($this->dt, true); }
            public function status() { return $this->st; }
            public function body() { return $this->dt; }
        };
    }

    protected function finnhubGet(string $path, array $params = [])
    {
        $key = config('services.finnhub.key', env('FINNHUB_API_KEY'));
        if (empty($key) || $key === 'your_finnhub_api_key_here') {
            return null;
        }
        $options = $this->getHttpOptions(15);
        // Finnhub sometimes has issues with CA bundles on local machines
        // If verify is false in general, it will be false here too.
        
        $response = Http::withOptions($options)
            ->withHeaders([
                'User-Agent'      => 'Mozilla/5.0 Chrome/120',
                'X-Finnhub-Token' => $key,
            ])
            ->retry(3, 200)
            ->get($this->finnhubBase . $path, $params);
            
        if ($response && $response->status() === 429) {
            abort(429, 'FINNHUB_RATE_LIMIT');
        }
        
        return $response;
    }

    protected function exchangeRateGet(string $path, array $params = [])
    {
        return Http::withOptions($this->getHttpOptions(8))
            ->withHeaders([
                'Accept'     => 'application/json',
                'User-Agent' => 'Mozilla/5.0 Chrome/120',
            ])
            ->retry(3, 100)
            ->get($this->exchangeRateBase . $path, $params);
    }

    protected function okxGet(string $path, array $params = [])
    {
        return Http::withOptions($this->getHttpOptions(8))
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])
            ->retry(3, 100)
            ->get($this->okxBase . $path, $params);
    }

    protected function binanceGet(string $path, array $params = [])
    {
        return Http::withOptions($this->getHttpOptions(8))
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])
            ->retry(3, 100)
            ->get($this->binanceBase . $path, $params);
    }
}
