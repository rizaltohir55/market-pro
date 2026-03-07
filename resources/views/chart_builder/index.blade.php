@extends('layouts.app')

@section('title', 'Chart Builder (G)')
@section('page-title', 'Multi-Asset Chart Builder (G)')

@section('content')
<div class="panel acrylic" style="height: calc(100vh - 120px); display: flex; flex-direction: column;">
    <div class="panel-header" style="justify-content: space-between; border-bottom: 1px solid var(--border);">
        <div style="display: flex; gap: var(--space-3); align-items: center;">
            <a href="javascript:history.back()" class="btn btn-sm btn-ghost" style="padding: 0 8px; display: flex; align-items: center; justify-content: center;" title="Go Back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </a>
            <div class="search-box" style="position: relative;">
                <input type="text" id="add-symbol-input" class="form-input" placeholder="Search symbol (e.g. BTC, AAPL)..." style="width: 280px; text-transform: uppercase;">
                <div id="symbol-search-results" class="search-results-dropdown" style="display: none; position: absolute; top: 100%; left: 0; width: 100%; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius-sm); margin-top: 4px; z-index: 100; max-height: 300px; overflow-y: auto; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5);">
                </div>
            </div>
            <button id="btn-add-symbol" class="btn btn-sm btn-primary">Add Overlay</button>
            <button id="btn-add-pane" class="btn btn-sm btn-ghost">Add New Pane</button>
            
            <div class="chart-timeframe" id="builder-timeframe" style="display:flex; gap:4px; margin-left: var(--space-4);">
                <button class="btn btn-ghost btn-sm" data-tf="15m">15m</button>
                <button class="btn btn-ghost btn-sm active btn-primary" data-tf="1h">1h</button>
                <button class="btn btn-ghost btn-sm" data-tf="4h">4h</button>
                <button class="btn btn-ghost btn-sm" data-tf="1d">1D</button>
            </div>
            
            <div class="chart-indicators" id="builder-indicators" style="display:flex; gap:4px; margin-left: var(--space-4); border-left: 1px solid rgba(255,255,255,0.1); padding-left: var(--space-4);">
                <button class="btn btn-ghost btn-sm" data-indicator="sma20">SMA 20</button>
                <button class="btn btn-ghost btn-sm" data-indicator="sma50">SMA 50</button>
                <button class="btn btn-ghost btn-sm" data-indicator="bb">Bollinger Bands</button>
                <button class="btn btn-ghost btn-sm" data-indicator="volume">Volume Profile</button>
            </div>
        </div>
        <div>
            <span class="text-caption">G — Multi-Asset Comparative Analysis</span>
        </div>
    </div>
    
    <div style="padding: var(--space-2); display: flex; gap: 8px; flex-wrap: wrap; border-bottom: 1px solid rgba(255,255,255,0.05);" id="active-symbols-list">
        <!-- Badges for active symbols go here -->
    </div>

    <!-- We will use a flex container for multiple panes if requested, otherwise single pane -->
    <div id="chart-builder-container" style="flex: 1; min-height: 0; display: flex; flex-direction: column; gap: 4px;">
        <!-- Initial Main Pane -->
        <div id="pane-0" class="chart-pane" style="flex: 1; width: 100%; position: relative;">
            <div class="loading-overlay" style="position:absolute; top:0; left:0; right:0; bottom:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.5); z-index:10; display:none;">
                <span>Loading Data...</span>
            </div>
            <div class="tv-container" style="width:100%; height:100%;"></div>
        </div>
    </div>
</div>

<style>
.symbol-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 8px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: var(--radius-sm);
    font-size: 0.75rem;
    font-family: var(--font-mono);
}
.symbol-badge .color-dot {
    width: 8px; height: 8px; border-radius: 50%;
}
.symbol-badge button {
    background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0; font-size: 0.8rem;
}
.symbol-badge button:hover { color: var(--danger); }

/* Search Dropdown Styles */
.form-input {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.1);
    color: var(--text-primary);
    padding: 6px 12px;
    border-radius: var(--radius-sm);
    outline: none;
    font-family: var(--font-mono);
}
.form-input:focus {
    border-color: var(--accent);
}
.search-result-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.search-result-item:hover {
    background: rgba(255,255,255,0.1);
}
.search-result-item:last-child {
    border-bottom: none;
}
.search-symbol {
    font-weight: bold;
    color: var(--accent);
    font-family: var(--font-mono);
}
.search-desc {
    font-size: 0.8rem;
    color: var(--text-muted);
}
.search-type {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(255,255,255,0.1);
    color: var(--text-secondary);
}
</style>
@endsection

@section('scripts')
<script>
const initialSymbols = @json($symbols);
let currentTf = '1h';

// Colors for overlapping lines
const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4'];
let colorIndex = 0;

let chartInstance = null;
let activeSeries = {}; // symbol -> { series, color, type, pane }

let baseSymbolData = null;
let activeIndicators = { sma20: false, sma50: false, bb: false, volume: false };
let indicatorSeries = {};

document.addEventListener('DOMContentLoaded', () => {
    initBuilderChart();
    
    // Load initial symbols
    initialSymbols.forEach(sym => {
        addSymbolToChart(sym.toUpperCase());
    });
    
    // UI Listeners
    const searchInput = document.getElementById('add-symbol-input');
    const searchResults = document.getElementById('symbol-search-results');
    let searchTimeout = null;

    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(async () => {
            try {
                // Call the unified search endpoint
                const res = await fetch(`/api/market/terminal?command=srch&query=${encodeURIComponent(query)}`);
                if (!res.ok) throw new Error('Search failed');
                const data = await res.json();
                
                if (data && data.data && data.data.length > 0) {
                    searchResults.innerHTML = data.data.map(item => `
                        <div class="search-result-item" data-symbol="${item.symbol}">
                            <div>
                                <div class="search-symbol">${item.symbol}</div>
                                <div class="search-desc">${item.description || item.name || ''}</div>
                            </div>
                            <div class="search-type">${item.type || 'Crypto/Stock'}</div>
                        </div>
                    `).join('');
                    searchResults.style.display = 'block';
                    
                    // Add click listeners to items
                    document.querySelectorAll('.search-result-item').forEach(item => {
                        item.addEventListener('click', () => {
                            const symbol = item.dataset.symbol;
                            searchInput.value = symbol;
                            searchResults.style.display = 'none';
                            addSymbolToChart(symbol);
                        });
                    });
                } else {
                    searchResults.innerHTML = '<div style="padding: 10px; color: var(--text-muted); text-align: center;">No results found</div>';
                    searchResults.style.display = 'block';
                }
            } catch (error) {
                console.error('Search error:', error);
            }
        }, 300); // 300ms debounce
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    document.getElementById('btn-add-symbol').addEventListener('click', () => {
        const val = searchInput.value.trim().toUpperCase();
        if (val) addSymbolToChart(val);
        searchInput.value = '';
        searchResults.style.display = 'none';
    });
    
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const val = e.target.value.trim().toUpperCase();
            if (val) addSymbolToChart(val);
            e.target.value = '';
            searchResults.style.display = 'none';
        }
    });
    
    document.querySelectorAll('#builder-timeframe button').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('#builder-timeframe button').forEach(b => {
                b.classList.remove('active', 'btn-primary');
            });
            e.target.classList.add('active', 'btn-primary');
            currentTf = e.target.dataset.tf;
            
            // Reload all active series with new timeframe
            const symbols = Object.keys(activeSeries);
            symbols.forEach(s => removeSymbol(s, false)); // keep badges
            symbols.forEach(s => addSymbolToChart(s, true)); // reload data
        });
    });

    // Indicator Toggle Listener
    document.querySelectorAll('#builder-indicators button').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const ind = e.target.dataset.indicator;
            activeIndicators[ind] = !activeIndicators[ind];
            
            if (activeIndicators[ind]) {
                e.target.classList.add('active', 'btn-primary');
            } else {
                e.target.classList.remove('active', 'btn-primary');
            }
            
            if (baseSymbolData) {
                renderIndicators();
            }
        });
    });
    
    // Handle Add Pane (Split View) - simplified version, just adds below
    document.getElementById('btn-add-pane').addEventListener('click', () => {
        alert('Multi-pane advanced charting is coming in the next update. Currently supporting Single-Pane Comparative Overlays.');
    });
});

function initBuilderChart() {
    const container = document.querySelector('#pane-0 .tv-container');
    chartInstance = LightweightCharts.createChart(container, {
        width: container.offsetWidth,
        height: container.offsetHeight,
        layout: { background: { color: '#0f172a' }, textColor: '#94a3b8', fontFamily: "'JetBrains Mono', monospace", fontSize: 11 },
        grid: { vertLines: { color: '#1e293b' }, horzLines: { color: '#1e293b' } },
        crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
        rightPriceScale: { borderColor: '#1e293b', scaleMargins: { top: 0.1, bottom: 0.1 } },
        timeScale: { borderColor: '#1e293b', timeVisible: true },
    });
    
    new ResizeObserver(entries => {
        if (entries[0].contentRect.width > 0 && entries[0].contentRect.height > 0) {
            chartInstance.applyOptions({ 
                width: entries[0].contentRect.width, 
                height: entries[0].contentRect.height 
            });
        }
    }).observe(container);
}

async function addSymbolToChart(symbol, skipBadge = false) {
    if (activeSeries[symbol]) return; // Already exists
    
    const isFirst = Object.keys(activeSeries).length === 0;
    const color = colors[colorIndex % colors.length];
    colorIndex++;
    
    // Show Loading
    document.querySelector('#pane-0 .loading-overlay').style.display = 'flex';
    
    try {
        const res = await fetch(`/api/market/klines?symbol=${symbol}&interval=${currentTf}&limit=500`);
        if (!res.ok) throw new Error('Failed to fetch data');
        const data = await res.json();
        
        if (!data || !data.length) throw new Error('No data');
        
        const sortedData = [...data].sort((a,b) => a.time - b.time);
        
        let series;
        if (isFirst) {
            // First symbol is Candles and acts as the base scale
            series = chartInstance.addCandlestickSeries({
                upColor: '#10b981', downColor: '#ef4444',
                borderUpColor: '#10b981', borderDownColor: '#ef4444',
                wickUpColor: '#10b98188', wickDownColor: '#ef444488',
            });
            const cData = sortedData.map(k => ({ time: k.time, open: k.open, high: k.high, low: k.low, close: k.close }));
            series.setData(cData);
            
            baseSymbolData = sortedData;
            renderIndicators();
        } else {
            // Subsequent symbols are Lines anchored to Percentage scale for direct comparative viewing!
            series = chartInstance.addLineSeries({
                color: color,
                lineWidth: 2,
                priceScaleId: 'left', // Attach to a different scale or set mode to Percentage
            });
            
            // Format to basic line
            const lData = sortedData.map(k => ({ time: k.time, value: k.close }));
            series.setData(lData);
            
            // If we are overlaying, make the right price scale Percentage-based
            chartInstance.rightPriceScale().applyOptions({
                mode: LightweightCharts.PriceScaleMode.Percentage,
            });
        }
        
        activeSeries[symbol] = { series, color, isFirst };
        
        if (!skipBadge) addBadge(symbol, isFirst ? '#10b981' : color);
        
        chartInstance.timeScale().fitContent();
        
    } catch (e) {
        console.error(e);
        alert(`Could not load data for ${symbol}. Check if the symbol exists and is supported.`);
    } finally {
        document.querySelector('#pane-0 .loading-overlay').style.display = 'none';
    }
}

function addBadge(symbol, color) {
    const cont = document.getElementById('active-symbols-list');
    const b = document.createElement('div');
    b.className = 'symbol-badge';
    b.id = `badge-${symbol}`;
    b.innerHTML = `
        <span class="color-dot" style="background:${color}"></span>
        <span>${symbol}</span>
        <button onclick="removeSymbol('${symbol}', true)">×</button>
    `;
    cont.appendChild(b);
}

window.removeSymbol = function(symbol, removeBadge = true) {
    if (activeSeries[symbol]) {
        chartInstance.removeSeries(activeSeries[symbol].series);
        delete activeSeries[symbol];
    }
    if (removeBadge) {
        const b = document.getElementById(`badge-${symbol}`);
        if (b) b.remove();
        
        // If we removed all, reset the scale if needed
        if (Object.keys(activeSeries).length <= 1) {
            chartInstance.rightPriceScale().applyOptions({
                mode: LightweightCharts.PriceScaleMode.Normal,
            });
        }
        
        // If the base symbol was removed
        if (!activeSeries[symbol] && Object.keys(activeSeries).length === 0) {
            baseSymbolData = null;
            // Clear indicators visually when base is removed
            for (let k in activeIndicators) activeIndicators[k] = false;
            document.querySelectorAll('#builder-indicators button').forEach(btn => btn.classList.remove('active', 'btn-primary'));
        }
    }
}

// --- Technical Indicators Logic ---

function calculateSMA(data, period) {
    const result = [];
    for (let i = 0; i < data.length; i++) {
        if (i < period - 1) continue;
        let sum = 0;
        for (let j = 0; j < period; j++) {
            sum += data[i - j].close;
        }
        result.push({ time: data[i].time, value: sum / period });
    }
    return result;
}

function calculateBollinger(data, period, stdDevMultiplier) {
    const basis = [];
    const upper = [];
    const lower = [];
    
    for (let i = 0; i < data.length; i++) {
        if (i < period - 1) continue;
        let sum = 0;
        for (let j = 0; j < period; j++) {
            sum += data[i - j].close;
        }
        const sma = sum / period;
        
        let varianceSum = 0;
        for (let j = 0; j < period; j++) {
            varianceSum += Math.pow(data[i - j].close - sma, 2);
        }
        const stdDev = Math.sqrt(varianceSum / period);
        
        basis.push({ time: data[i].time, value: sma });
        upper.push({ time: data[i].time, value: sma + stdDevMultiplier * stdDev });
        lower.push({ time: data[i].time, value: sma - stdDevMultiplier * stdDev });
    }
    return { basis, upper, lower };
}

function renderIndicators() {
    if (!baseSymbolData || !chartInstance) return;
    
    // Clean up existing
    if (indicatorSeries.sma20) { chartInstance.removeSeries(indicatorSeries.sma20); delete indicatorSeries.sma20; }
    if (indicatorSeries.sma50) { chartInstance.removeSeries(indicatorSeries.sma50); delete indicatorSeries.sma50; }
    if (indicatorSeries.bbUpper) { chartInstance.removeSeries(indicatorSeries.bbUpper); delete indicatorSeries.bbUpper; }
    if (indicatorSeries.bbLower) { chartInstance.removeSeries(indicatorSeries.bbLower); delete indicatorSeries.bbLower; }
    if (indicatorSeries.bbBasis) { chartInstance.removeSeries(indicatorSeries.bbBasis); delete indicatorSeries.bbBasis; }
    if (indicatorSeries.volume) { chartInstance.removeSeries(indicatorSeries.volume); delete indicatorSeries.volume; }
    
    if (activeIndicators.sma20) {
        indicatorSeries.sma20 = chartInstance.addLineSeries({ color: '#f59e0b', lineWidth: 1, title: 'SMA 20' });
        indicatorSeries.sma20.setData(calculateSMA(baseSymbolData, 20));
    }
    
    if (activeIndicators.sma50) {
        indicatorSeries.sma50 = chartInstance.addLineSeries({ color: '#3b82f6', lineWidth: 2, title: 'SMA 50' });
        indicatorSeries.sma50.setData(calculateSMA(baseSymbolData, 50));
    }
    
    if (activeIndicators.bb) {
        const bb = calculateBollinger(baseSymbolData, 20, 2);
        
        indicatorSeries.bbUpper = chartInstance.addLineSeries({ color: '#10b981', lineWidth: 1, lineStyle: LightweightCharts.LineStyle.Dashed, title: 'BB Upper' });
        indicatorSeries.bbUpper.setData(bb.upper);
        
        indicatorSeries.bbBasis = chartInstance.addLineSeries({ color: '#f59e0b', lineWidth: 1, title: 'BB Basis' });
        indicatorSeries.bbBasis.setData(bb.basis);
        
        indicatorSeries.bbLower = chartInstance.addLineSeries({ color: '#ef4444', lineWidth: 1, lineStyle: LightweightCharts.LineStyle.Dashed, title: 'BB Lower' });
        indicatorSeries.bbLower.setData(bb.lower);
    }
    
    if (activeIndicators.volume) {
        indicatorSeries.volume = chartInstance.addHistogramSeries({
            color: '#26a69a',
            priceFormat: { type: 'volume' },
            priceScaleId: '', // set as an overlay
            scaleMargins: {
                top: 0.85, 
                bottom: 0,
            },
        });
        
        const vData = baseSymbolData.map(k => {
            const vol = parseFloat(k.volume) || 0;
            const color = k.close >= k.open ? 'rgba(16, 185, 129, 0.4)' : 'rgba(239, 68, 68, 0.4)';
            return { time: k.time, value: vol, color: color };
        });
        indicatorSeries.volume.setData(vData);
    }
}
</script>
@endsection
