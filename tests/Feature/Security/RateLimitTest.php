<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitTest extends TestCase
{
    /**
     * Test that the 'market' rate limiter works.
     */
    public function test_api_rate_limiting_is_active()
    {
        $this->mock(\App\Services\MultiSourceMarketService::class, function ($mock) {
            $mock->shouldReceive('getTicker24hr')->andReturn(['price' => 100]);
        });

        // We expect 60 requests per minute
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/api/market/ticker?symbol=BTCUSDT');
            
            if ($i === 60) {
                // The 61st request should be throttled
                $response->assertStatus(429);
            } else {
                // Normal requests should not be 429
                $this->assertNotEquals(429, $response->getStatusCode());
            }
        }
    }
}
