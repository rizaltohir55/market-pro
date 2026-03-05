<?php

namespace App\Http\Controllers;

use App\Services\StockMarketService;
use App\Services\ForexMarketService;
use App\Services\MultiSourceMarketService;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function show(Request $request, StockMarketService $stockMarket, ForexMarketService $forexMarket, MultiSourceMarketService $cryptoMarket)
    {
        $symbol = strtoupper($request->get('symbol', 'AAPL'));
        
        // Determine Asset Type
        // If symbol has no prefix, try to guess or assume it's a crypto if it ends in USDT, or Stock otherwise.
        $type = 'stock';
        $cleanSymbol = $symbol;
        
        if (str_starts_with($symbol, 'stock:')) {
            $type = 'stock';
            $cleanSymbol = str_replace('stock:', '', $symbol);
        } elseif (str_starts_with($symbol, 'forex:')) {
            $type = 'forex';
            $cleanSymbol = str_replace('forex:', '', $symbol);
        } elseif (str_ends_with($symbol, 'USDT') || str_ends_with($symbol, 'USD')) {
            // Treat as crypto if it ends in USDT/USD
            $type = 'crypto';
        }
        
        // For commodities like XAU
        if ($cleanSymbol === 'XAU' || $cleanSymbol === 'PAXGUSDT' || $cleanSymbol === 'XAG') {
            $type = 'commodity';
        }

        $quote = null;
        $profile = null;
        
        if ($type === 'stock') {
            $quote = $stockMarket->getStockQuote($cleanSymbol);
            $profile = $stockMarket->getStockProfile($cleanSymbol);
        } elseif ($type === 'crypto' || $type === 'commodity') {
            $ticker = $cryptoMarket->getTicker24hr($cleanSymbol);
            if (!empty($ticker)) {
                $quote = [
                    'symbol' => $ticker['symbol'] ?? $cleanSymbol,
                    'price' => (float)($ticker['lastPrice'] ?? 0),
                    'change_pct' => (float)($ticker['priceChangePercent'] ?? 0),
                    'high' => (float)($ticker['highPrice'] ?? 0),
                    'low' => (float)($ticker['lowPrice'] ?? 0),
                    'volume' => (float)($ticker['volume'] ?? 0),
                ];
            }
        } elseif ($type === 'forex') {
            // Simplified for forex
            $rates = $forexMarket->getForexRates();
            $cur = str_replace(['/', 'USD'], '', reset((explode(':', $symbol)))); // Try to get base
            if (empty($cur)) $cur = $cleanSymbol;
            
            if (isset($rates['rates'][$cur])) {
                $r = $rates['rates'][$cur];
                $quote = [
                    'symbol' => $r['display'],
                    'price' => $r['rate'],
                    'change_pct' => 0, // Not available easily
                ];
            }
        }

        return view('asset.show', compact('symbol', 'cleanSymbol', 'type', 'quote', 'profile'));
    }
}
