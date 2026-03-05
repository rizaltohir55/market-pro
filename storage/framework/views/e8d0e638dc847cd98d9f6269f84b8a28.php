

<?php $__env->startSection('title', 'Trading — ' . ($symbol === 'PAXGUSDT' ? 'XAU/USD' : $symbol)); ?>
<?php $__env->startSection('page-title', ($symbol === 'PAXGUSDT' ? 'XAU/USD' : str_replace('USDT', '/USDT', $symbol)) . ' Trading View'); ?>

<?php $__env->startSection('content'); ?>

<div style="display:flex;align-items:center;gap:var(--space-4);margin-bottom:var(--space-2)">
    <span class="text-h2 text-mono" id="tv-price">
        <?php if($ticker): ?>
            $<?php echo e(number_format((float)$ticker['lastPrice'], 2)); ?>

        <?php else: ?>
            <span class="text-muted">Loading...</span>
        <?php endif; ?>
    </span>
    <?php if($ticker): ?>
    <span class="kpi-delta <?php echo e((float)$ticker['priceChangePercent'] >= 0 ? 'positive' : 'negative'); ?>">
        <?php echo e((float)$ticker['priceChangePercent'] >= 0 ? '▲' : '▼'); ?>

        <?php echo e(number_format(abs((float)$ticker['priceChangePercent']), 2)); ?>%
    </span>
    <span class="text-caption">
        H: $<?php echo e(number_format((float)$ticker['highPrice'], 2)); ?>

        &nbsp;L: $<?php echo e(number_format((float)$ticker['lowPrice'], 2)); ?>

        &nbsp;Vol: <?php echo e(number_format((float)$ticker['quoteVolume'] / 1e6, 1)); ?>M USDT
    </span>
    <?php endif; ?>
    <span id="stream-badge" class="badge badge-neutral" style="margin-left:auto">Connecting...</span>
</div>

<style>
.grid-trading-immersive {
    display: flex;
    flex-direction: row;
    gap: var(--space-4);
    height: calc(100vh - 160px);
    width: 100%;
}
.chart-dock {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.side-dock {
    width: 320px;
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
    overflow-y: auto;
    padding-right: 4px;
}
.side-dock::-webkit-scrollbar { width: 4px; }
.side-dock .panel {
    background: rgba(15, 23, 42, 0.35); /* High transparency for doc */
    box-shadow: none;
}

@media (max-width: 1024px) {
    .grid-trading-immersive {
        flex-direction: column;
        height: auto;
    }
    .chart-dock {
        height: 60vh;
        min-height: 400px;
    }
    .side-dock {
        width: 100%;
        padding-right: 0;
        overflow: visible;
    }
}
</style>

<div class="grid-trading-immersive fade-in-up" style="--delay: 0.1s;">
    
    <div class="panel chart-dock acrylic" style="border-radius: var(--radius-lg)">
        <div class="chart-toolbar" style="padding:var(--space-3) var(--space-4); border-bottom: 1px solid var(--border); display:flex; align-items:center; background: rgba(0,0,0,0.2)">
            <div class="chart-timeframe" id="timeframe-selector" style="display:flex; gap:8px">
                <button class="btn btn-ghost btn-sweep btn-sm" data-tf="1m">1m</button>
                <button class="btn btn-ghost btn-sweep btn-sm" data-tf="5m">5m</button>
                <button class="btn btn-ghost btn-sweep btn-sm <?php echo e($interval === '15m' ? 'btn-primary' : ''); ?>" data-tf="15m">15m</button>
                <button class="btn btn-ghost btn-sweep btn-sm <?php echo e($interval === '1h'  ? 'btn-primary' : ''); ?>" data-tf="1h">1h</button>
                <button class="btn btn-ghost btn-sweep btn-sm <?php echo e($interval === '4h'  ? 'btn-primary' : ''); ?>" data-tf="4h">4h</button>
                <button class="btn btn-ghost btn-sweep btn-sm" data-tf="1d">1D</button>
            </div>
            
            <div style="width: 1px; height: 20px; background: rgba(255,255,255,0.1); margin: 0 var(--space-3);"></div>
            
            <div class="chart-tools" style="display:flex; gap: 8px;">
                <select id="chart-type-selector" class="btn btn-sm btn-ghost" style="appearance: none; background: transparent; padding-right: 20px;">
                    <option value="Candlestick">Candlestick</option>
                    <option value="Bar">OHLC / Bar</option>
                    <option value="Line">Line</option>
                </select>
                <button class="btn btn-ghost btn-sm" id="btn-gp-indicators">GP ▾</button>
                <button class="btn btn-ghost btn-sm" id="btn-compare-rp">Compare (RP)</button>
                <button class="btn btn-ghost btn-sm" id="btn-builder-g" onclick="window.location.href='/chart-builder?symbols=<?php echo e($symbol); ?>'">Builder (G)</button>
            </div>
            
            <span class="text-small" style="margin-left:auto;text-shadow:0 0 10px rgba(255,255,255,0.2)">Edge-to-Edge Holographic Chart</span>
        </div>
        
        
        <div id="gp-indicators-panel" class="panel acrylic" style="display:none; position: absolute; z-index: 50; margin-top: 50px; margin-left: var(--space-4); padding: var(--space-3); border: 1px solid var(--border);">
            <div style="font-size: 0.8rem; font-weight: bold; margin-bottom: 8px;">Technical Indicators (GP)</div>
            <div style="display:flex; flex-direction:column; gap:4px; font-size: 0.75rem;">
                <label><input type="checkbox" class="indicator-toggle" value="sma20"> SMA 20</label>
                <label><input type="checkbox" class="indicator-toggle" value="sma50"> SMA 50</label>
                <label><input type="checkbox" class="indicator-toggle" value="ema20"> EMA 20</label>
                <label><input type="checkbox" class="indicator-toggle" value="bb"> Bollinger Bands</label>
                <label><input type="checkbox" class="indicator-toggle" value="rsi"> RSI (14)</label>
                <label><input type="checkbox" class="indicator-toggle" value="macd"> MACD (12,26,9)</label>
            </div>
        </div>
        
        
        <div id="compare-rp-panel" class="panel acrylic" style="display:none; position: absolute; z-index: 50; margin-top: 50px; margin-left: 200px; padding: var(--space-3); border: 1px solid var(--border);">
            <div style="font-size: 0.8rem; font-weight: bold; margin-bottom: 8px;">Relative Performance (RP)</div>
            <input type="text" id="compare-symbol-input" placeholder="Symbol (e.g. ETHUSDT)" class="form-control" style="font-size: 0.75rem; padding: 4px; width: 150px;">
            <button id="btn-add-compare" class="btn btn-sm btn-primary" style="margin-top: 8px; width: 100%;">Add Overlay</button>
            <div id="compare-active-list" style="margin-top: 8px; font-size: 0.7rem; color: var(--text-muted);"></div>
        </div>
        <div class="chart-container" id="chart-container" style="flex:1; width:100%;">
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted)">
                <span>Loading immersive chart...</span>
            </div>
        </div>
    </div>

    
    <div class="side-dock fade-in-up" style="--delay: 0.3s;">
        
        <div class="panel acrylic animated-border" style="flex-shrink: 0; padding: 1px;">
            <div style="background: var(--bg-surface); backdrop-filter: blur(20px); border-radius: calc(var(--radius-xl) - 1px); padding: var(--space-4); height: 100%;">
                <div class="panel-header" style="justify-content:space-between; border-bottom:none; padding-bottom:0; padding-left:0; padding-right:0">
                    <span class="panel-title" style="text-shadow: 0 0 10px rgba(59, 130, 246, 0.4)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        AI Signal HUD
                    </span>
                    <span id="market-regime-badge" class="badge badge-neutral text-small" style="display:none; transform: translateZ(10px)">RANGING</span>
                </div>
                <div class="panel-body" style="padding: var(--space-4) 0 0 0;">
                    <div class="prediction-panel" id="prediction-panel">
                        
                        
                        <div class="prediction-signal animate-breathe" id="pred-signal" style="display:flex; flex-direction:column; align-items:center; position:relative; margin-bottom: var(--space-4); transition:all var(--transition-base)">
                            <div style="position:relative; width: 140px; height: 140px; margin-bottom: 12px; filter: drop-shadow(0 0 15px rgba(0,0,0,0.5))">
                                <svg viewBox="0 0 100 100" style="position:absolute; top:0; left:0; width:100%; height:100%; transform: rotate(-90deg);">
                                    <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="6" />
                                    <circle id="hud-ring" cx="50" cy="50" r="42" fill="none" stroke="var(--accent)" stroke-width="6" stroke-linecap="round" stroke-dasharray="264" stroke-dashoffset="264" style="transition: stroke-dashoffset 1s cubic-bezier(0.4, 0, 0.2, 1), stroke 0.5s;" />
                                    <!-- Inner spinning target ring -->
                                    <circle cx="50" cy="50" r="34" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1" stroke-dasharray="4 4">
                                        <animateTransform attributeName="transform" type="rotate" from="0 50 50" to="360 50 50" dur="10s" repeatCount="indefinite" />
                                    </circle>
                                </svg>
                                <div style="position:absolute; top:0; left:0; width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                    <div class="signal-action" id="signal-action-text" style="font-size:1.5rem;font-weight:700;letter-spacing:1px;text-shadow: 0 0 15px rgba(255,255,255,0.2)">—</div>
                                    <div class="signal-confidence" id="signal-conf-text" style="font-size:0.75rem; color:var(--text-muted); font-family:var(--font-mono); margin-top:4px">CALC%</div>
                                </div>
                            </div>
                            <div class="signal-label" style="text-transform:uppercase;color:var(--text-secondary);font-size:0.7rem;letter-spacing:2px;text-align:center;">Composite Analysis</div>
                        </div>
                    
                    <div id="prediction-targets" style="display:none;background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.05);border-radius:var(--radius-md);padding:var(--space-3);margin-bottom:var(--space-4);">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-2);text-align:center;font-size:0.8125rem">
                            <div><span class="text-muted" style="font-size:0.6875rem;text-transform:uppercase">Target (TP)</span><br><span id="target-tp" class="text-success text-mono fw-bold" style="text-shadow:0 0 8px rgba(52,211,153,0.4)">—</span></div>
                            <div><span class="text-muted" style="font-size:0.6875rem;text-transform:uppercase">Stop Loss (SL)</span><br><span id="target-sl" class="text-danger text-mono fw-bold" style="text-shadow:0 0 8px rgba(248,113,113,0.4)">—</span></div>
                        </div>
                        <div style="margin-top:var(--space-3);text-align:center;font-size:0.75rem;color:var(--text-muted);border-top:1px solid rgba(255,255,255,0.05);padding-top:8px">
                            Risk/Reward: <span id="target-rr" class="text-mono" style="color:var(--text-primary)">1.5</span>x | Trend ADX: <span id="trend-strength">WEAK</span>
                        </div>
                    </div>

                    <div class="text-caption" style="margin-bottom:var(--space-2)">Category Breakdown</div>
                    <div class="category-list" id="category-list" style="display:flex;flex-direction:column;gap:var(--space-2);margin-bottom:var(--space-2)">
                        <div class="skeleton skeleton-text" style="width:100%"></div>
                        <div class="skeleton skeleton-text" style="width:80%"></div>
                        <div class="skeleton skeleton-text" style="width:90%"></div>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="panel acrylic" style="flex-shrink: 0">
            <div class="panel-header" style="padding-bottom:var(--space-2); border-bottom: none">
                <span class="panel-title">Order Book</span>
                <span class="text-small">Depth: 15</span>
            </div>
            <div class="panel-body no-padding">
                <div class="orderbook" id="orderbook">
                    <div style="padding:2px var(--space-4);display:grid;grid-template-columns:1fr 1fr 1fr;font-size:0.6875rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.04em;font-family:var(--font-mono)">
                        <span>Price</span><span class="align-right">Amount</span><span class="align-right">Total</span>
                    </div>

                    
                    <div id="ob-asks" style="max-height:180px;overflow:hidden;display:flex;flex-direction:column-reverse;padding:0 var(--space-2)">
                        <div style="padding:var(--space-3);color:var(--text-muted);text-align:center;font-size:0.75rem">Loading orderbook...</div>
                    </div>

                    
                    <div class="orderbook-spread" style="margin: 4px 0; background: rgba(0,0,0,0.2); padding: 4px; text-align: center; border-radius: 4px">
                        — Spread unavailable —
                    </div>

                    
                    <div id="ob-bids" style="max-height:180px;overflow:hidden;padding:0 var(--space-2)">
                        <div style="padding:var(--space-3);color:var(--text-muted);text-align:center;font-size:0.75rem">Loading orderbook...</div>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="panel acrylic" style="flex-shrink: 0; margin-bottom: var(--space-4)">
            <div class="panel-header" style="border-bottom:none">
                <span class="panel-title">Market Trades</span>
            </div>
            <div class="panel-body no-padding" style="max-height:200px;overflow-y:auto">
                <table class="data-table" id="trades-table" style="font-size:0.75rem">
                    <thead style="display:none">
                        <tr>
                            <th>Price</th><th class="align-right">Amount</th><th class="align-right">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="3" class="text-center" style="padding:var(--space-4);color:var(--text-muted)">Loading trades...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<style>
.orderbook-row {
    transition: background 0.2s ease, transform 0.2s ease;
    cursor: pointer;
    border-radius: 4px;
}
.orderbook-row:hover {
    background: rgba(255,255,255,0.05);
    transform: translateX(2px);
}
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
const SYMBOL = '<?php echo e($symbol); ?>';
let currentInterval = '<?php echo e($interval); ?>';
const initialKlines = <?php echo json_encode($klines, 15, 512) ?>;

document.addEventListener('DOMContentLoaded', function() {
    // If server returned klines, render immediately. Otherwise load via API.
    if (initialKlines && initialKlines.length > 0) {
        initChart(initialKlines);
    } else {
        fetchAndRenderChart(SYMBOL, currentInterval);
    }
    
    // Replace manual polling with Server-Sent Events
    initSSE();
    initBinanceWS();
    
    initTimeframeSelector();
});

// ─── CHART ───────────────────────────────────────────────────
let chart, candleSeries, barSeries, lineSeries, volumeSeries;
let currentBar = null;
let currentChartType = 'Candlestick'; // Candlestick, Bar, Line

// Store indicator series references to remove them
let indicatorSeries = {};
let compareSeries = {};

// We need base data available globally for indicators
let baseData = []; 

function initChart(klines) {
    const container = document.getElementById('chart-container');
    container.innerHTML = '';
    chart = LightweightCharts.createChart(container, {
        width: container.offsetWidth,
        height: container.offsetHeight,
        layout: { background: { color: '#111827' }, textColor: '#94a3b8', fontFamily: "'JetBrains Mono', monospace", fontSize: 11 },
        grid: { vertLines: { color: '#1e293b' }, horzLines: { color: '#1e293b' } },
        crosshair: {
            mode: LightweightCharts.CrosshairMode.Normal,
            vertLine: { color: '#f59e0b44', width: 1, style: 2 },
            horzLine: { color: '#f59e0b44', width: 1, style: 2 },
        },
        rightPriceScale: { borderColor: '#1e293b', scaleMargins: { top: 0.1, bottom: 0.2 } },
        timeScale: { borderColor: '#1e293b', timeVisible: true, secondsVisible: false },
    });

    // Create all 3 basic types but only show Candle initially
    candleSeries = chart.addCandlestickSeries({ upColor: '#10b981', downColor: '#ef4444', borderUpColor: '#10b981', borderDownColor: '#ef4444', wickUpColor: '#10b98188', wickDownColor: '#ef444488' });
    barSeries = chart.addBarSeries({ upColor: '#10b981', downColor: '#ef4444', thinBars: false, visible: false });
    lineSeries = chart.addLineSeries({ color: '#3b82f6', lineWidth: 2, visible: false });

    volumeSeries = chart.addHistogramSeries({
        color: '#3b82f6', priceFormat: { type: 'volume' },
        priceScaleId: '', scaleMargins: { top: 0.85, bottom: 0 },
    });

    if (klines && klines.length) {
        const sorted = [...klines].sort((a,b) => a.time - b.time);
        baseData = sorted;
        
        const cData = sorted.map(k => ({ time: k.time, open: k.open, high: k.high, low: k.low, close: k.close }));
        const lData = sorted.map(k => ({ time: k.time, value: k.close }));
        
        candleSeries.setData(cData);
        barSeries.setData(cData);
        lineSeries.setData(lData);
        
        volumeSeries.setData(sorted.map(k => ({ time: k.time, value: k.volume || 0, color: k.close >= k.open ? '#10b98133' : '#ef444433' })));
        
        chart.timeScale().fitContent();
        const last = sorted[sorted.length - 1];
        if (last) {
            updatePriceDisplay(last.close);
            currentBar = { ...last };
        }
    }
    new ResizeObserver(entries => {
        const { width, height } = entries[0].contentRect;
        if (width > 0 && height > 0) chart.applyOptions({ width, height });
    }).observe(container);

    initChartTools();
}

function updateSeriesCurrentBar(tickBar) {
    if (candleSeries) candleSeries.update(tickBar);
    if (barSeries) barSeries.update(tickBar);
    if (lineSeries) lineSeries.update({ time: tickBar.time, value: tickBar.close });
    if (volumeSeries) {
        volumeSeries.update({ 
            time: tickBar.time, 
            value: tickBar.volume || 0, 
            color: tickBar.close >= tickBar.open ? '#10b98133' : '#ef444433' 
        });
    }

    // A real app would also dynamically append to baseData and recalculate indicators if needed
    // For this integration, static indicators on historical data is fine.
}

function initChartTools() {
    // 1. Chart Type
    document.getElementById('chart-type-selector').addEventListener('change', (e) => {
        const type = e.target.value;
        currentChartType = type;
        candleSeries.applyOptions({ visible: type === 'Candlestick' });
        barSeries.applyOptions({ visible: type === 'Bar' });
        lineSeries.applyOptions({ visible: type === 'Line' });
    });

    // 2. GP Indicators Toggle
    const gpBtn = document.getElementById('btn-gp-indicators');
    const gpPanel = document.getElementById('gp-indicators-panel');
    gpBtn.addEventListener('click', () => {
        gpPanel.style.display = gpPanel.style.display === 'none' ? 'block' : 'none';
        comparePanel.style.display = 'none';
    });
    
    // Auto-open if query param mode=gp
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('mode') === 'gp') {
        gpPanel.style.display = 'block';
    }

    document.querySelectorAll('.indicator-toggle').forEach(cb => {
        cb.addEventListener('change', (e) => {
            toggleIndicator(e.target.value, e.target.checked);
        });
    });

    // 3. Compare (RP)
    const compareBtn = document.getElementById('btn-compare-rp');
    const comparePanel = document.getElementById('compare-rp-panel');
    compareBtn.addEventListener('click', () => {
        comparePanel.style.display = comparePanel.style.display === 'none' ? 'block' : 'none';
        gpPanel.style.display = 'none';
    });

    document.getElementById('btn-add-compare').addEventListener('click', () => {
        const sym = document.getElementById('compare-symbol-input').value.trim().toUpperCase();
        if (sym && !compareSeries[sym]) {
            addCompareSeries(sym);
            document.getElementById('compare-symbol-input').value = '';
        }
    });
}

function toggleIndicator(id, active) {
    if (!active && indicatorSeries[id]) {
        // Remove
        if (Array.isArray(indicatorSeries[id])) {
            indicatorSeries[id].forEach(s => chart.removeSeries(s));
        } else {
            chart.removeSeries(indicatorSeries[id]);
        }
        delete indicatorSeries[id];
        return;
    }

    if (active && baseData.length && window.TAMath) {
        const closes = baseData.map(d => d.close);
        const times = baseData.map(d => d.time);
        
        let seriesData = [];
        let createdSeries;

        if (id === 'sma20' || id === 'sma50') {
            const period = id === 'sma20' ? 20 : 50;
            const sma = window.TAMath.sma(closes, period);
            seriesData = times.map((t, i) => ({ time: t, value: sma[i] })).filter(d => d.value !== null);
            createdSeries = chart.addLineSeries({ color: id === 'sma20' ? '#f59e0b' : '#ec4899', lineWidth: 2, crosshairMarkerVisible: false });
            createdSeries.setData(seriesData);
            indicatorSeries[id] = createdSeries;
        } 
        else if (id === 'ema20') {
            const ema = window.TAMath.ema(closes, 20);
            seriesData = times.map((t, i) => ({ time: t, value: ema[i] })).filter(d => d.value !== null);
            createdSeries = chart.addLineSeries({ color: '#8b5cf6', lineWidth: 2, crosshairMarkerVisible: false });
            createdSeries.setData(seriesData);
            indicatorSeries[id] = createdSeries;
        }
        else if (id === 'bb') {
            const bb = window.TAMath.bollingerBands(closes, 20, 2);
            const upperData = times.map((t, i) => ({ time: t, value: bb.upper[i] })).filter(d => d.value !== null);
            const lowerData = times.map((t, i) => ({ time: t, value: bb.lower[i] })).filter(d => d.value !== null);
            
            const upperSeries = chart.addLineSeries({ color: '#10b981', lineWidth: 1, lineStyle: 2, crosshairMarkerVisible: false });
            const lowerSeries = chart.addLineSeries({ color: '#10b981', lineWidth: 1, lineStyle: 2, crosshairMarkerVisible: false });
            
            upperSeries.setData(upperData);
            lowerSeries.setData(lowerData);
            indicatorSeries[id] = [upperSeries, lowerSeries];
        }
        else if (id === 'rsi') {
            const rsi = window.TAMath.rsi(closes, 14);
            seriesData = times.map((t, i) => ({ time: t, value: rsi[i] })).filter(d => d.value !== null);
            createdSeries = chart.addLineSeries({ 
                color: '#06b6d4', lineWidth: 2, priceScaleId: 'rsiScale', 
                scaleMargins: { top: 0.8, bottom: 0 }
            });
            createdSeries.setData(seriesData);
            indicatorSeries[id] = createdSeries;
        }
        else if (id === 'macd') {
            const macd = window.TAMath.macd(closes, 12, 26, 9);
            const macdData = times.map((t, i) => ({ time: t, value: macd.macdLine[i] })).filter(d => d.value !== null);
            const sigData = times.map((t, i) => ({ time: t, value: macd.signalLine[i] })).filter(d => d.value !== null);
            const histData = times.map((t, i) => ({ time: t, value: macd.histogram[i], color: macd.histogram[i] >= 0 ? '#10b98188' : '#ef444488' })).filter(d => d.value !== null);
            
            const machSeries = chart.addHistogramSeries({ priceScaleId: 'macdScale', scaleMargins: { top: 0.8, bottom: 0 } });
            const maclSeries = chart.addLineSeries({ color: '#3b82f6', lineWidth: 2, crosshairMarkerVisible: false, priceScaleId: 'macdScale' });
            const sigSeries = chart.addLineSeries({ color: '#f59e0b', lineWidth: 2, crosshairMarkerVisible: false, priceScaleId: 'macdScale' });
            
            machSeries.setData(histData);
            maclSeries.setData(macdData);
            sigSeries.setData(sigData);
            
            indicatorSeries[id] = [machSeries, maclSeries, sigSeries];
        }
    }
}

async function addCompareSeries(symbol) {
    try {
        const btn = document.getElementById('btn-add-compare');
        btn.textContent = 'Loading...';
        btn.disabled = true;

        const res = await fetch(`/api/market/klines?symbol=${symbol}&interval=${currentInterval}&limit=200`);
        if (!res.ok) throw new Error('Failed');
        const data = await res.json();
        
        if (data && data.length) {
            // Switch current price scale to percentage so overlay makes sense
            chart.rightPriceScale().applyOptions({ mode: LightweightCharts.PriceScaleMode.Percentage });
            
            const sorted = [...data].sort((a,b) => a.time - b.time);
            const lineData = sorted.map(k => ({ time: k.time, value: k.close }));
            
            const color = '#' + Math.floor(Math.random()*16777215).toString(16).padStart(6, '0');
            const series = chart.addLineSeries({ color: color, lineWidth: 2, priceScaleId: 'right' });
            series.setData(lineData);
            
            compareSeries[symbol] = series;
            
            // Add UI chip
            const list = document.getElementById('compare-active-list');
            const chip = document.createElement('div');
            chip.style.display = 'inline-flex';
            chip.style.alignItems = 'center';
            chip.style.gap = '4px';
            chip.style.background = 'rgba(255,255,255,0.1)';
            chip.style.padding = '2px 6px';
            chip.style.borderRadius = '4px';
            chip.style.margin = '2px';
            chip.innerHTML = `<span style="color:${color};font-weight:bold">${symbol}</span> <span style="cursor:pointer;color:var(--danger)" onclick="window.removeCompare('${symbol}', this)">×</span>`;
            list.appendChild(chip);
        } else {
            alert('No data for symbol ' + symbol);
        }
    } catch (e) {
        alert('Could not overlay ' + symbol);
    } finally {
        const btn = document.getElementById('btn-add-compare');
        btn.textContent = 'Add Overlay';
        btn.disabled = false;
    }
}

window.removeCompare = function(symbol, el) {
    if (compareSeries[symbol]) {
        chart.removeSeries(compareSeries[symbol]);
        delete compareSeries[symbol];
    }
    el.parentNode.remove();
    
    // Switch back to normal mode if no compares left
    if (Object.keys(compareSeries).length === 0) {
        chart.rightPriceScale().applyOptions({ mode: LightweightCharts.PriceScaleMode.Normal });
    }
}

async function fetchAndRenderChart(symbol, interval) {
    const container = document.getElementById('chart-container');
    if (container) container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted)">Loading chart from server...</div>';
    
    let loaded = false;
    
    // 1. Always try local Laravel API FIRST (most reliable in this env)
    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            const r = await fetch(`<?php echo e(url('/api/market/klines')); ?>?symbol=${symbol}&interval=${interval}&limit=200`, { signal: AbortSignal.timeout(15000) });
            if (r.ok) {
                const data = await r.json();
                if (data && data.length > 0) {
                    initChart(data);
                    loaded = true;
                    break;
                }
            }
        } catch(e) { console.warn(`[Chart] Local API attempt ${attempt+1} failed:`, e.message); }
        if (attempt < 2) await new Promise(r => setTimeout(r, 2000)); // Wait 2s before retry
    }
    
    if (!loaded && container) {
        container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted)"><div class="empty-state"><div class="empty-state-icon">📡</div><div class="empty-state-title">Could not load chart data</div><div class="empty-state-desc">Server cannot reach Binance API. Check your network connection.</div></div></div>';
    }
}

function updatePriceDisplay(price) {
    const el = document.getElementById('tv-price');
    if (el) el.textContent = '$' + price.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    const upd = document.getElementById('status-last-update');
    if (upd) upd.textContent = `Last update: ${new Date().toLocaleTimeString('en-US',{hour12:false})}`;
}

// ─── PREDICTION ──────────────────────────────────────────────
function renderPrediction(data) {
    if (!data || !data.categories) {
        document.getElementById('pred-signal').innerHTML = '<div style="color:var(--text-muted);padding:var(--space-4);text-align:center">Waiting for sufficient data (min 200 candles)...</div>';
        return;
    }

    // 1. HUD Signal Update
    const sigClass = data.signal.includes('BUY') ? 'buy' : (data.signal.includes('SELL') ? 'sell' : 'neutral');
    
    // Update SVG Ring
    const hudRing = document.getElementById('hud-ring');
    const hudAction = document.getElementById('signal-action-text');
    const hudConf = document.getElementById('signal-conf-text');
    
    // Calculate arc length (max 264)
    const conf = parseFloat(data.confidence) || 0;
    const offset = 264 - (conf / 100) * 264;
    
    if (hudRing) {
        hudRing.style.strokeDashoffset = offset;
        hudRing.style.stroke = sigClass === 'buy' ? 'var(--success)' : (sigClass === 'sell' ? 'var(--danger)' : 'var(--text-muted)');
        hudRing.style.filter = `drop-shadow(0 0 10px ${sigClass === 'buy' ? 'rgba(16,185,129,0.8)' : (sigClass === 'sell' ? 'rgba(239,68,68,0.8)' : 'rgba(255,255,255,0.2)')})`;
    }
    
    if (hudAction) {
        hudAction.textContent = data.signal;
        hudAction.className = `signal-action ${sigClass === 'buy' ? 'text-success' : (sigClass === 'sell' ? 'text-danger' : 'text-primary')}`;
        hudAction.style.textShadow = `0 0 15px ${sigClass === 'buy' ? 'rgba(16,185,129,0.6)' : (sigClass === 'sell' ? 'rgba(239,68,68,0.6)' : 'rgba(255,255,255,0.2)')}`;
    }
    
    if (hudConf) {
        hudConf.textContent = `${conf}% CONF`;
    }
        
    // 2. Targets & Regime
    document.getElementById('prediction-targets').style.display = 'block';
    
    // Choose TP/SL based on direction
    const tp = data.signal.includes('BUY') ? data.price_target_buy : data.price_target_sell;
    const sl = data.signal.includes('BUY') ? data.stop_loss_buy : data.stop_loss_sell;
    
    document.getElementById('target-tp').textContent = '$' + formatPrice(tp);
    document.getElementById('target-sl').textContent = '$' + formatPrice(sl);
    document.getElementById('target-rr').textContent = data.risk_reward;
    
    const tsEl = document.getElementById('trend-strength');
    tsEl.textContent = data.trend_strength;
    tsEl.className = data.trend_strength.includes('STRONG') ? 'text-success fw-bold' : 'text-muted';
    
    const mrBadge = document.getElementById('market-regime-badge');
    mrBadge.style.display = 'inline-block';
    mrBadge.textContent = data.market_regime;
    mrBadge.className = `badge text-small ${data.market_regime === 'TRENDING' ? 'badge-buy' : 'badge-neutral'}`;

    // 3. Category Breakdown (New Multi-layer view)
    const catEl = document.getElementById('category-list');
    let catHtml = '';
    for (const [catName, catData] of Object.entries(data.categories)) {
        const cSig = catData.signal.includes('BUY') ? 'buy' : (catData.signal.includes('SELL') ? 'sell' : 'neutral');
        const cColor = catData.signal.includes('BUY') ? 'var(--success)' : (catData.signal.includes('SELL') ? 'var(--danger)' : 'var(--text-muted)');
        
        // Progress bar logic
        const buyw = catData.buy;
        const sellw = catData.sell;
        const neutw = 100 - buyw - sellw;
        
        catHtml += `
        <div style="font-size:0.75rem;display:flex;flex-direction:column;gap:4px">
            <div style="display:flex;justify-content:space-between">
                <span style="font-weight:600;color:var(--text-primary)">${catName}</span>
                <span class="badge badge-${cSig}" style="font-size:0.65rem;padding:0px 4px">${catData.signal}</span>
            </div>
            <div style="display:flex;height:6px;width:100%;border-radius:3px;overflow:hidden;background:var(--bg-base);box-shadow:inset 0 1px 3px rgba(0,0,0,0.2)">
                <div style="width:${buyw}%;background:linear-gradient(90deg, #059669, #34d399);transition:width 0.5s ease"></div>
                <div style="width:${neutw}%;background:var(--text-muted);opacity:0.2;transition:width 0.5s ease"></div>
                <div style="width:${sellw}%;background:linear-gradient(90deg, #ef4444, #f87171);transition:width 0.5s ease"></div>
            </div>
        </div>`;
    }
    catEl.innerHTML = catHtml;

    // 4. Raw Indicator List
    const listEl = document.getElementById('indicator-list');
    if (data.indicators && data.indicators.length) {
        listEl.innerHTML = data.indicators.map(ind => {
            const sc = ['BUY','BULLISH'].includes(ind.signal)?'text-success':['SELL','BEARISH'].includes(ind.signal)?'text-danger':'text-muted';
            const bc = ['BUY','BULLISH'].includes(ind.signal)?'buy':['SELL','BEARISH'].includes(ind.signal)?'sell':'neutral';
            return `
            <div class="indicator-row" style="padding:var(--space-2) 0;border-bottom:1px solid var(--border-color)">
                <span class="indicator-name" style="flex:1">${ind.name}</span>
                <span class="indicator-value ${sc}" style="font-size:0.75rem;margin-right:var(--space-2)">${ind.value}</span>
                <span class="badge badge-${bc}" style="font-size:0.65rem">${ind.signal}</span>
            </div>`;
        }).join('');
    } else {
        listEl.innerHTML = '<div style="color:var(--text-muted);font-size:0.8rem">No raw indicators parsed.</div>';
    }
}

// ─── SERVER-SENT EVENTS (SSE) STREAMING ─────────
let sseSource = null;

function initSSE() {
    if (sseSource) sseSource.close();
    
    // Connect to our new backend stream route, passing the selected interval
    sseSource = new EventSource(`<?php echo e(url('/api/market/stream')); ?>?page=trading&symbol=${SYMBOL}&interval=${currentInterval}`);
    
    sseSource.onopen = () => {
        setBadge('live', '🟢 SSE Connected');
        document.getElementById('conn-dot')?.classList.remove('disconnected');
        document.getElementById('conn-text') && (document.getElementById('conn-text').textContent = 'Connected');
        document.getElementById('ws-status-text') && (document.getElementById('ws-status-text').textContent = 'Real-time Server Stream Active');
    };
    
    sseSource.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            
            // Real-time ticking is now handled separately by Binance WebSocket (initBinanceWS)
            // so we don't process data.ticker here for chart ticking anymore.
            
            // 2. Update Order Book
            if (data.depth) renderOrderBook(data.depth);
            
            // 3. Update Trades
            if (data.trades) renderTrades(data.trades);
            
            // 4. Update Ticker Header
            if (data.ticker) {
                const headerPrice = document.querySelector('.header-price');
                const headerChange = document.querySelector('.header-change');
                if (headerPrice) headerPrice.textContent = '$' + formatPrice(parseFloat(data.ticker.lastPrice));
                if (headerChange) {
                    const c = parseFloat(data.ticker.priceChangePercent);
                    headerChange.textContent = `${c >= 0 ? '+' : ''}${c.toFixed(2)}%`;
                    headerChange.className = `header-change ${c >= 0 ? 'text-success' : 'text-danger'}`;
                }
            }
            
            // 5. Update Watchlist and Ticker Bar (shared payload)
            if (data.watchlist) renderWatchlist(data.watchlist);
            if (data.top_pairs) {
                if (typeof window.renderTickerBar === 'function') {
                    window.renderTickerBar(data.top_pairs);
                }
            }
            
            // 6. Update Prediction (Now uses MTF predictions map)
            if (data.predictions) {
                // Determine the correct prediction timeframe based on current chart interval
                // By default, Trading page focuses on the short term, so we map '1m', '5m', '15m' to '15m' prediction
                // '1h' to '1h' prediction, etc. 
                let targetPrediction = data.predictions['15m'];
                if (currentInterval === '1h') targetPrediction = data.predictions['1h'];
                else if (currentInterval === '4h' || currentInterval === '1d') targetPrediction = data.predictions['4h'];
                
                if (targetPrediction) renderPrediction(targetPrediction);
            }
            
        } catch (e) {
            console.error('SSE JSON parsing error or Render Error', e, e.stack);
            
            // To visibly debug if it's failing inside renderPrediction:
            const listEl = document.getElementById('indicator-list');
            if(listEl) {
                listEl.innerHTML = `<div class="text-danger" style="padding:10px">JS Render Error: ${e.message}</div>`;
            }
        }
    };
    
    let reconnectTimeout = null;
    sseSource.onerror = () => {
        // SSE drops every 3s by design on our backend.
        // Only show offline if it hasn't reconnected after 5 seconds.
        clearTimeout(reconnectTimeout);
        reconnectTimeout = setTimeout(() => {
            if (sseSource.readyState !== EventSource.OPEN) {
                setBadge('poll', '🟡 Reconnecting');
                document.getElementById('conn-dot')?.classList.add('disconnected');
                document.getElementById('conn-text') && (document.getElementById('conn-text').textContent = 'Offline');
                document.getElementById('ws-status-text') && (document.getElementById('ws-status-text').textContent = 'Connection lost. Retrying...');
            }
        }, 5000);
    };
}

function setBadge(type, text) {
    const b = document.getElementById('stream-badge');
    if (!b) return;
    b.textContent = text;
    b.className = `badge ${type === 'live' ? 'badge-buy' : 'badge-warning'}`;
}

// SSE dynamically replaces the need for rest polling

// ─── TIMEFRAME SELECTOR ───────────────────────────────────────
function initTimeframeSelector() {
    document.querySelectorAll('#timeframe-selector button').forEach(btn => {
        btn.addEventListener('click', function() {
            const tf = this.dataset.tf;
            document.querySelectorAll('#timeframe-selector button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentInterval = tf;
            if (sseSource) { try { sseSource.close(); } catch(e){} }
            if (typeof binanceWS !== 'undefined' && binanceWS) { try { binanceWS.close(); } catch(e){} }
            fetchAndRenderChart(SYMBOL, tf).then(() => { initSSE(); initBinanceWS(); });
        });
    });
}



// ─── WATCHLIST ────────────────────────────────────────────────
// Now handled by the global pair-tab system (renderWatchlistFromSSE).
// SSE data is piped via renderWatchlistFromSSE() in app.blade.php.

function renderWatchlist(tickers) {
    // Delegate to global tab system if available
    if (typeof window.renderWatchlistFromSSE === 'function') {
        window.renderWatchlistFromSSE(tickers);
    }
}

// ─── ORDER BOOK RENDERER ──────────────────────────────────────
function renderOrderBook(depth) {
    const asks = (depth.asks || []).slice(0, 15);
    const bids = (depth.bids || []).slice(0, 15);
    const maxA = asks.reduce((m, r) => Math.max(m, parseFloat(r[1])), 0.0001);
    const maxB = bids.reduce((m, r) => Math.max(m, parseFloat(r[1])), 0.0001);

    const obAsks = document.getElementById('ob-asks');
    const obBids = document.getElementById('ob-bids');
    const spread = document.querySelector('.orderbook-spread');

    if (obAsks) {
        obAsks.innerHTML = asks.map(a => {
            const pct = (parseFloat(a[1]) / maxA * 100).toFixed(1);
            const total = (parseFloat(a[0]) * parseFloat(a[1])).toLocaleString('en-US', {maximumFractionDigits:0});
            return `<div class="orderbook-row ask"><span class="ob-bg" style="width:${pct}%;background:linear-gradient(90deg, transparent, rgba(239,68,68,0.15))"></span><span class="price">$${formatPrice(parseFloat(a[0]))}</span><span class="amount">${parseFloat(a[1]).toFixed(5)}</span><span class="total">${total}</span></div>`;
        }).join('');
    }
    if (obBids) {
        obBids.innerHTML = bids.map(b => {
            const pct = (parseFloat(b[1]) / maxB * 100).toFixed(1);
            const total = (parseFloat(b[0]) * parseFloat(b[1])).toLocaleString('en-US', {maximumFractionDigits:0});
            return `<div class="orderbook-row bid"><span class="ob-bg" style="width:${pct}%;background:linear-gradient(270deg, transparent, rgba(16,185,129,0.15))"></span><span class="price">$${formatPrice(parseFloat(b[0]))}</span><span class="amount">${parseFloat(b[1]).toFixed(5)}</span><span class="total">${total}</span></div>`;
        }).join('');
    }
    if (spread && asks.length && bids.length) {
        const bestAsk = parseFloat(asks[0][0]), bestBid = parseFloat(bids[0][0]);
        const spr = bestAsk - bestBid, sprPct = ((spr / bestAsk) * 100).toFixed(4);
        spread.innerHTML = `$${formatPrice((bestBid + spr/2))} <span style="font-size:0.6875rem;color:var(--text-muted);margin-left:4px">Spread: ${sprPct}%</span>`;
    }
}

// ─── RECENT TRADES RENDERER ───────────────────────────────────
function renderTrades(trades) {
    const tbody = document.querySelector('#trades-table tbody');
    if (!tbody || !trades.length) return;
    const sorted = [...trades].reverse();
    tbody.innerHTML = sorted.map(t => {
        const cls = t.isBuyerMaker ? 'negative' : 'positive';
        const ts = new Date(t.time).toLocaleTimeString('en-US', {hour12:false, hour:'2-digit', minute:'2-digit', second:'2-digit'});
        return `<tr><td class="${cls}">$${formatPrice(parseFloat(t.price))}</td><td class="align-right">${parseFloat(t.qty).toFixed(5)}</td><td class="align-right" style="color:var(--text-muted)">${ts}</td></tr>`;
    }).join('');
}

function formatPrice(price) {
    if (price >= 1000) return price.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (price >= 1) return price.toFixed(2);
    if (price >= 0.01) return price.toFixed(4);
    return price.toFixed(6);
}
// ─── BINANCE WEBSOCKET FOR REALTIME CHART ─────────────────────
let binanceWS = null;
function initBinanceWS() {
    if (binanceWS) binanceWS.close();
    
    let wsInterval = currentInterval.toLowerCase();
    binanceWS = new WebSocket(`wss://data-stream.binance.vision/ws/${SYMBOL.toLowerCase()}@kline_${wsInterval}`);
    
    binanceWS.onmessage = (event) => {
        try {
            const message = JSON.parse(event.data);
            const kline = message.k;
            if (!kline) return;
            
            const tickBar = {
                time: kline.t / 1000,
                open: parseFloat(kline.o),
                high: parseFloat(kline.h),
                low: parseFloat(kline.l),
                close: parseFloat(kline.c),
                volume: parseFloat(kline.v)
            };
            
            currentBar = tickBar;
            
            updateSeriesCurrentBar(tickBar);
            
            updatePriceDisplay(parseFloat(kline.c));
        } catch(e) {}
    };
    
    binanceWS.onerror = () => console.warn('Binance WS error');
    binanceWS.onclose = () => setTimeout(initBinanceWS, 5000);
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Project\market_pro\resources\views/trading/index.blade.php ENDPATH**/ ?>