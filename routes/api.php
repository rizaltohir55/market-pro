<?php

use App\Http\Controllers\Api\MarketController;
use Illuminate\Support\Facades\Route;

Route::prefix('market')->middleware('throttle:market')->group(function () {
    // ─── Crypto ──────────────────────────────────────────────────────────────
    Route::get('/ticker',         [MarketController::class, 'ticker']);
    Route::get('/klines',         [MarketController::class, 'klines']);
    Route::get('/klines-history', [MarketController::class, 'klinesHistory']);
    Route::get('/depth',          [MarketController::class, 'depth']);
    Route::get('/trades',         [MarketController::class, 'trades']);
    Route::get('/top-pairs',      [MarketController::class, 'topPairs']);
    Route::get('/prediction',     [MarketController::class, 'prediction']);
    Route::get('/batch-predictions', [MarketController::class, 'batchPredictions']);
    Route::get('/stream',         [\App\Http\Controllers\Api\StreamController::class, 'stream']);

    // ─── Stocks / Global Markets ─────────────────────────────────────────────
    Route::get('/stocks',         [MarketController::class, 'stocks']);
    Route::get('/stock-quote',    [MarketController::class, 'stockQuote']);
    Route::get('/stock-profile',  [MarketController::class, 'stockProfile']);
    Route::get('/stock-candles',  [MarketController::class, 'stockCandles']);
    Route::get('/indices',        [MarketController::class, 'indices']);

    // ─── Forex ────────────────────────────────────────────────────────────────
    Route::get('/forex',          [MarketController::class, 'forex']);
    Route::get('/forex-history',  [MarketController::class, 'forexHistory']);

    // ─── Bonds ────────────────────────────────────────────────────────────────
    Route::get('/bonds',          [MarketController::class, 'bonds']);

    // ─── Commodities ─────────────────────────────────────────────────────────
    Route::get('/commodities',    [MarketController::class, 'commodities']);
    
    // ─── Phase 4 New Features ────────────────────────────────────────────────
    Route::get('/equity-valuation',     [MarketController::class, 'equityValuation']);
    Route::get('/analyst-estimates',    [MarketController::class, 'analystEstimates']);
    Route::get('/peer-comparison',      [MarketController::class, 'peerComparison']);
    Route::get('/forex-full',           [MarketController::class, 'forexFull']);
    Route::get('/options-chain',        [MarketController::class, 'optionsChain']);
    Route::get('/crypto-futures',       [MarketController::class, 'cryptoFutures']);
    Route::get('/commodities-extended', [MarketController::class, 'commoditiesExtended']);

    // ─── News & Media ────────────────────────────────────────────────────────
    Route::get('/news',          [MarketController::class, 'news']);
    Route::get('/company-news',  [MarketController::class, 'companyNews']);
    Route::get('/news/bookmarks', [MarketController::class, 'getBookmarks']);
    Route::post('/news/bookmarks', [MarketController::class, 'toggleBookmark']);
    Route::get('/economic-calendar', [MarketController::class, 'economicCalendar']);
    
    // ─── Dashboard AJAX Data ──────────────────────────────────────────────
    Route::get('/dashboard/market-summary', [\App\Http\Controllers\DashboardController::class, 'getMarketSummary']);
    Route::get('/dashboard/stock-summary',  [\App\Http\Controllers\DashboardController::class, 'getStockSummary']);
    Route::get('/dashboard/global-rates',   [\App\Http\Controllers\DashboardController::class, 'getGlobalRates']);
    Route::get('/dashboard/global-overview', [\App\Http\Controllers\DashboardController::class, 'getGlobalOverview']);

    // ─── Terminal ────────────────────────────────────────────────────────────
    Route::get('/terminal',      [MarketController::class, 'terminal']);
});