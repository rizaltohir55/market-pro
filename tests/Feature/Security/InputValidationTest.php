<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InputValidationTest extends TestCase
{
    /**
     * Test that passing an array to a string 'symbol' parameter is handled gracefully.
     */
    public function test_symbol_array_input_returns_422()
    {
        $response = $this->getJson('/api/market/klines?symbol[]=BTC');

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['symbol']);
    }

    /**
     * Test that valid symbol strings still work.
     */
    public function test_valid_symbol_input_works()
    {
        $response = $this->getJson('/api/market/klines?symbol=BTCUSDT');

        // It should not be a 422 or 500
        $this->assertNotEquals(422, $response->getStatusCode());
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    /**
     * Test interval validation.
     */
    public function test_invalid_interval_input_returns_422()
    {
        $response = $this->getJson('/api/market/klines?symbol=BTCUSDT&interval[]=1h');

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['interval']);
    }

    /**
     * Test limit validation.
     */
    public function test_invalid_limit_input_returns_422()
    {
        $response = $this->getJson('/api/market/klines?symbol=BTCUSDT&limit=invalid');

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['limit']);
    }

    /**
     * Test batch predictions with malformed symbols.
     */
    public function test_batch_predictions_malformed_symbols()
    {
        // Passing an object/associative array instead of indexed array
        $response = $this->getJson('/api/market/batch-predictions?symbols[key]=val');
        
        $response->assertStatus(200); // Should handle gracefully
    }
}
