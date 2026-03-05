<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/trading', [DashboardController::class, 'trading'])->name('trading');
Route::get('/scanner', [DashboardController::class, 'scanner'])->name('scanner');
Route::get('/analysis', [DashboardController::class, 'analysis'])->name('analysis');
Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');

// ─── Phase 4 New Market Pages ─────────────────────────────────────────────
Route::get('/asset', function (\Illuminate\Http\Request $request) {
    return redirect()->route('trading', $request->all());
});
Route::get('/equity', [DashboardController::class, 'equity'])->name('equity');
Route::get('/fx-rates', [DashboardController::class, 'fxRates'])->name('fx-rates');
Route::get('/derivatives', [DashboardController::class, 'derivatives'])->name('derivatives');
Route::get('/commodities', [DashboardController::class, 'commodityMarkets'])->name('commodities');
Route::get('/news', [DashboardController::class, 'news'])->name('news');
Route::get('/chart-builder', [DashboardController::class, 'chartBuilder'])->name('chart-builder');
