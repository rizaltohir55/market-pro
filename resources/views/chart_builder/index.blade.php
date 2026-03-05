@extends('layouts.app')

@section('title', 'Chart Builder (G)')
@section('page-title', 'Multi-Asset Chart Builder (G)')

@section('content')
<div class="panel acrylic" style="height: calc(100vh - 120px); display: flex; flex-direction: column;">
    <div class="panel-header" style="justify-content: space-between; border-bottom: 1px solid var(--border);">
        <div style="display: flex; gap: var(--space-3); align-items: center;">
            <div class="search-box">
                <input type="text" id="add-symbol-input" placeholder="Add symbol (e.g. BTCUSDT, ETHUSDT)..." style="width: 250px;">
            </div>
            <button id="btn-add-symbol" class="btn btn-sm btn-primary">Add Overlay</button>
            <button id="btn-add-pane" class="btn btn-sm btn-ghost">Add New Pane</button>
            
            <div class="chart-timeframe" id="builder-timeframe" style="display:flex; gap:4px; margin-left: var(--space-4);">
                <button class="btn btn-ghost btn-sm" data-tf="15m">15m</button>
                <button class="btn btn-ghost btn-sm active btn-primary" data-tf="1h">1h</button>
                <button class="btn btn-ghost btn-sm" data-tf="4h">4h</button>
                <button class="btn btn-ghost btn-sm" data-tf="1d">1D</button>
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

document.addEventListener('DOMContentLoaded', () => {
    initBuilderChart();
    
    // Load initial symbols
    initialSymbols.forEach(sym => {
        addSymbolToChart(sym.toUpperCase());
    });
    
    // UI Listeners
    document.getElementById('btn-add-symbol').addEventListener('click', () => {
        const val = document.getElementById('add-symbol-input').value.trim().toUpperCase();
        if (val) addSymbolToChart(val);
        document.getElementById('add-symbol-input').value = '';
    });
    
    document.getElementById('add-symbol-input').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const val = e.target.value.trim().toUpperCase();
            if (val) addSymbolToChart(val);
            e.target.value = '';
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
    }
}
</script>
@endsection
