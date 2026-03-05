

<?php $__env->startSection('title', 'Asset Detail — ' . $symbol); ?>
<?php $__env->startSection('page-title', 'Asset Analysis: ' . ($profile['name'] ?? $symbol)); ?>

<?php $__env->startSection('content'); ?>

<div style="display:flex;align-items:center;gap:var(--space-4);margin-bottom:var(--space-4)">
    <span class="text-h2 text-mono" id="asset-price">
        <?php if($quote && isset($quote['price'])): ?>
            $<?php echo e(number_format((float)$quote['price'], 2)); ?>

        <?php else: ?>
            <span class="text-muted">Loading...</span>
        <?php endif; ?>
    </span>
    <?php if($quote && isset($quote['change_pct'])): ?>
    <span class="kpi-delta <?php echo e((float)$quote['change_pct'] >= 0 ? 'positive' : 'negative'); ?>" id="asset-change">
        <?php echo e((float)$quote['change_pct'] >= 0 ? '▲' : '▼'); ?>

        <?php echo e(number_format(abs((float)$quote['change_pct']), 2)); ?>%
    </span>
    <?php endif; ?>
    
    <?php if($type === 'stock' || $type === 'crypto' || $type === 'commodity'): ?>
    <span class="text-caption" id="asset-day-range">
        H: $<?php echo e(number_format((float)($quote['high'] ?? 0), 2)); ?>

        &nbsp;L: $<?php echo e(number_format((float)($quote['low'] ?? 0), 2)); ?>

        &nbsp;Vol: <?php echo e(number_format((float)($quote['volume'] ?? 0) / 1e6, 2)); ?>M
    </span>
    <?php endif; ?>
</div>


<div class="grid-trading-immersive fade-in-up" style="--delay: 0.1s; display: flex; flex-direction: row; gap: var(--space-4); height: 60vh; min-height: 500px; margin-bottom: var(--space-4);">
    
    <div class="panel chart-dock acrylic" style="flex: 2.5; display: flex; flex-direction: column; border-radius: var(--radius-lg)">
        <div class="chart-toolbar" style="padding:var(--space-3) var(--space-4); border-bottom: 1px solid var(--border); display:flex; align-items:center; background: rgba(0,0,0,0.2)">
            <div class="chart-timeframe" id="timeframe-selector" style="display:flex; gap:8px">
                <button class="btn btn-ghost btn-sweep btn-sm" data-tf="D">1D</button>
                <button class="btn btn-ghost btn-sweep btn-sm" data-tf="W">1W</button>
                <button class="btn btn-ghost btn-sweep btn-sm" data-tf="M">1M</button>
            </div>
            
            <div style="width: 1px; height: 20px; background: rgba(255,255,255,0.1); margin: 0 var(--space-3);"></div>
            
            <div class="chart-tools" style="display:flex; gap: 8px;">
                <select id="chart-type-selector" class="btn btn-sm btn-ghost" style="appearance: none; background: transparent; padding-right: 20px;">
                    <option value="Candlestick">Candlestick</option>
                    <option value="Bar">Bar Chart</option>
                    <option value="Line">Line Chart</option>
                </select>
                <button class="btn btn-ghost btn-sm" id="btn-gp-indicators">Indicators ▾</button>
                <button class="btn btn-ghost btn-sm" id="btn-compare-rp">Compare</button>
            </div>
        </div>
        
        
        <div id="gp-indicators-panel" class="panel acrylic" style="display:none; position: absolute; z-index: 50; margin-top: 50px; margin-left: var(--space-4); padding: var(--space-3); border: 1px solid var(--border);">
            <div style="font-size: 0.8rem; font-weight: bold; margin-bottom: 8px;">Technical Indicators</div>
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
            <div style="font-size: 0.8rem; font-weight: bold; margin-bottom: 8px;">Compare Asset</div>
            <input type="text" id="compare-symbol-input" placeholder="Symbol (e.g. MSFT)" class="form-control" style="font-size: 0.75rem; padding: 4px; width: 150px;">
            <button id="btn-add-compare" class="btn btn-sm btn-primary" style="margin-top: 8px; width: 100%;">Add Overlay</button>
            <div id="compare-active-list" style="margin-top: 8px; font-size: 0.7rem; color: var(--text-muted);"></div>
        </div>

        <div class="chart-container" id="chart-container" style="flex:1; width:100%; position: relative;">
            <div id="chart-loader" style="position: absolute; inset: 0; display:flex; align-items:center; justify-content:center; color:var(--text-muted); background: rgba(0,0,0,0.5); z-index: 10;">
                <span>Loading Chart Data...</span>
            </div>
        </div>
    </div>

    
    <div class="side-dock fade-in-up" style="flex: 1; display: flex; flex-direction: column; gap: var(--space-4);">
        <div class="panel acrylic" style="flex: 1; overflow-y: auto;">
            <div class="panel-header">
                <span class="panel-title">Key Statistics</span>
            </div>
            <div class="panel-body no-padding">
                <div style="padding: var(--space-4); display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <?php if($profile && isset($profile['fundamentals'])): ?>
                        <?php $f = $profile['fundamentals']; ?>
                        <div class="stat-item">
                            <div class="stat-label">Market Cap</div>
                            <div class="stat-value">$<?php echo e(number_format(($profile['market_cap'] ?? 0)/1e9, 2)); ?>B</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">P/E Ratio</div>
                            <div class="stat-value"><?php echo e(number_format((float)($f['pe_ratio'] ?? 0), 2)); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">EPS (TTM)</div>
                            <div class="stat-value">$<?php echo e(number_format((float)($f['eps_ttm'] ?? 0), 2)); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Div Yield</div>
                            <div class="stat-value"><?php echo e(number_format(((float)($f['dividend_yield']??0))*100, 2)); ?>%</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Price to Book</div>
                            <div class="stat-value"><?php echo e(number_format((float)($f['price_to_book']??0), 2)); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">ROE</div>
                            <div class="stat-value"><?php echo e(number_format(((float)($f['roe']??0))*100, 2)); ?>%</div>
                        </div>
                    <?php else: ?>
                        
                        <div class="stat-item">
                            <div class="stat-label">Type</div>
                            <div class="stat-value" style="text-transform: capitalize;"><?php echo e($type); ?></div>
                        </div>
                        <?php if(isset($quote['volume'])): ?>
                        <div class="stat-item">
                            <div class="stat-label">24h Volume</div>
                            <div class="stat-value"><?php echo e(number_format($quote['volume'], 2)); ?></div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if($type === 'stock'): ?>
        
        <div class="panel acrylic" style="flex: 1;">
            <div class="panel-header">
                <span class="panel-title">Analyst Recommendations</span>
            </div>
            <div class="panel-body" id="analyst-rec-container">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 2rem;">Loading recommendations...</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>


<div class="grid-2 fade-in-up" style="--delay: 0.2s; align-items: stretch; margin-top: var(--space-4)">
    
    <div class="panel acrylic">
        <div class="panel-header">
            <span class="panel-title">📰 Related News</span>
        </div>
        <div class="panel-body no-padding" style="max-height: 400px; overflow-y: auto;" id="news-container">
            <div style="padding: 2rem; color: var(--text-muted); text-align: center;">Loading news...</div>
        </div>
    </div>
    
    <?php if($type === 'stock'): ?>
    
    <div class="panel acrylic">
        <div class="panel-header">
            <span class="panel-title">🔗 Peer Comparison</span>
        </div>
        <div class="panel-body no-padding" style="max-height: 400px; overflow-y: auto;">
            <table class="data-table" id="peer-table">
                <thead>
                    <tr>
                        <th>Symbol</th><th>Price</th><th class="align-right">Mkt Cap</th><th class="align-right">P/E</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="4" style="text-align:center; padding: 2rem; color: var(--text-muted)">Loading peers...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.stat-item { margin-bottom: 4px; }
.stat-label { font-size: 0.75rem; color: var(--text-muted); margin-bottom: 2px; }
.stat-value { font-size: 1.1rem; font-weight: 700; font-family: var(--font-mono); }
@media (max-width: 1024px) {
    .grid-trading-immersive {
        flex-direction: column !important;
        height: auto !important;
    }
    .chart-dock { min-height: 400px; }
}
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
const ASSET_SYMBOL = '<?php echo e($cleanSymbol); ?>';
const ASSET_TYPE = '<?php echo e($type); ?>';
let currentInterval = 'D';

let chart, candleSeries, barSeries, lineSeries, volumeSeries;
let baseData = [];
let indicatorSeries = {};
let compareSeries = {};

document.addEventListener('DOMContentLoaded', () => {
    initChartArea();
    fetchAndRenderChart(ASSET_SYMBOL, currentInterval);
    
    if (ASSET_TYPE === 'stock') {
        fetchAnalystRecs();
        fetchPeers();
        fetchNews(`/api/market/company-news?symbol=${ASSET_SYMBOL}`);
    } else if (ASSET_TYPE === 'crypto' || ASSET_TYPE === 'commodity') {
        fetchNews(`/api/market/news?category=crypto`);
    } else {
        fetchNews(`/api/market/news?category=forex`);
    }

    initChartTools();
});

function initChartArea() {
    const container = document.getElementById('chart-container');
    chart = LightweightCharts.createChart(container, {
        width: container.clientWidth,
        height: container.clientHeight,
        layout: { background: { color: 'transparent' }, textColor: '#94a3b8', fontFamily: "'JetBrains Mono', monospace", fontSize: 11 },
        grid: { vertLines: { color: 'rgba(255,255,255,0.05)' }, horzLines: { color: 'rgba(255,255,255,0.05)' } },
        crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
        rightPriceScale: { borderColor: 'rgba(255,255,255,0.1)', scaleMargins: { top: 0.1, bottom: 0.2 } },
        timeScale: { borderColor: 'rgba(255,255,255,0.1)' },
    });

    candleSeries = chart.addCandlestickSeries({ upColor: '#10b981', downColor: '#ef4444', wickUpColor: '#10b981', wickDownColor: '#ef4444' });
    barSeries = chart.addBarSeries({ upColor: '#10b981', downColor: '#ef4444', visible: false });
    lineSeries = chart.addLineSeries({ color: '#3b82f6', lineWidth: 2, visible: false });
    volumeSeries = chart.addHistogramSeries({ priceFormat: { type: 'volume' }, priceScaleId: '', scaleMargins: { top: 0.85, bottom: 0 } });

    new ResizeObserver(entries => {
        if(entries[0] && entries[0].contentRect) {
            chart.applyOptions({ width: entries[0].contentRect.width, height: entries[0].contentRect.height });
        }
    }).observe(container);
}

function initChartTools() {
    // Chart Type
    document.getElementById('chart-type-selector').addEventListener('change', (e) => {
        const type = e.target.value;
        candleSeries.applyOptions({ visible: type === 'Candlestick' });
        barSeries.applyOptions({ visible: type === 'Bar' });
        lineSeries.applyOptions({ visible: type === 'Line' });
    });

    // Timeframe
    document.querySelectorAll('#timeframe-selector button').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#timeframe-selector button').forEach(b => b.classList.remove('active', 'btn-primary'));
            this.classList.add('active', 'btn-primary');
            currentInterval = this.dataset.tf;
            fetchAndRenderChart(ASSET_SYMBOL, currentInterval);
        });
    });

    // Indicators Dropdown
    const gpBtn = document.getElementById('btn-gp-indicators');
    const gpPanel = document.getElementById('gp-indicators-panel');
    gpBtn.addEventListener('click', () => {
        gpPanel.style.display = gpPanel.style.display === 'none' ? 'block' : 'none';
        document.getElementById('compare-rp-panel').style.display = 'none';
    });

    document.querySelectorAll('.indicator-toggle').forEach(cb => {
        cb.addEventListener('change', (e) => toggleIndicator(e.target.value, e.target.checked));
    });

    // Compare Dropdown
    const compBtn = document.getElementById('btn-compare-rp');
    const compPanel = document.getElementById('compare-rp-panel');
    compBtn.addEventListener('click', () => {
        compPanel.style.display = compPanel.style.display === 'none' ? 'block' : 'none';
        document.getElementById('gp-indicators-panel').style.display = 'none';
    });
    
    document.getElementById('btn-add-compare').addEventListener('click', () => {
        const sym = document.getElementById('compare-symbol-input').value.trim().toUpperCase();
        if (sym) {
            addCompareSeries(sym, ASSET_TYPE);
            document.getElementById('compare-symbol-input').value = '';
        }
    });
}

function fetchAndRenderChart(symbol, interval) {
    document.getElementById('chart-loader').style.display = 'flex';
    
    // Determine API based on type
    let url = '';
    if (ASSET_TYPE === 'stock') {
        // Map D/W/M to Finnhub resolutions
        const now = Math.floor(Date.now() / 1000);
        let from = now - (365 * 86400); // 1 year default
        if (interval === 'W') from = now - (365 * 3 * 86400); // 3 years
        if (interval === 'M') from = now - (365 * 5 * 86400); // 5 years
        
        url = `/api/market/stock-candles?symbol=${symbol}&resolution=${interval}&from=${from}&to=${now}`;
    } else {
        // Crypto / Forex via klines
        let tf = interval === 'D' ? '1d' : (interval === 'W' ? '1w' : '1M');
        url = `/api/market/klines?symbol=${symbol}&interval=${tf}&limit=300`;
    }

    fetch(url)
        .then(r => r.json())
        .then(data => {
            document.getElementById('chart-loader').style.display = 'none';
            if (data && data.length) {
                
                let formattedData = [];
                if (ASSET_TYPE === 'stock') {
                    // Expect array of [time, open, high, low, close, volume] or object array? Our stock-candles returns array of objects via getStockCandles!
                    formattedData = data;
                } else {
                    formattedData = data.map(d => ({
                        time: d.time || d[0]/1000,
                        open: d.open || d[1],
                        high: d.high || d[2],
                        low: d.low || d[3],
                        close: d.close || d[4],
                        volume: d.volume || d[5] || 0
                    }));
                }
                
                // Sort ascending
                formattedData.sort((a,b) => a.time - b.time);
                baseData = formattedData;
                
                candleSeries.setData(formattedData);
                barSeries.setData(formattedData);
                lineSeries.setData(formattedData.map(d => ({time: d.time, value: d.close})));
                volumeSeries.setData(formattedData.map(d => ({
                    time: d.time, 
                    value: d.volume, 
                    color: d.close >= d.open ? 'rgba(16, 185, 129, 0.5)' : 'rgba(239, 68, 68, 0.5)'
                })));
                
                chart.timeScale().fitContent();
                
                // Refresh indicators if checked
                document.querySelectorAll('.indicator-toggle:checked').forEach(cb => {
                    toggleIndicator(cb.value, false);
                    toggleIndicator(cb.value, true);
                });
            }
        }).catch(err => {
            document.getElementById('chart-loader').innerHTML = '<span>Failed to load chart</span>';
        });
}

function toggleIndicator(id, active) {
    if (!active && indicatorSeries[id]) {
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
            const machSeries = chart.addHistogramSeries({ priceScaleId: 'macdScale', scaleMargins: { top: 0.8, bottom: 0 } });
            const maclSeries = chart.addLineSeries({ color: '#3b82f6', lineWidth: 2, crosshairMarkerVisible: false, priceScaleId: 'macdScale' });
            const sigSeries = chart.addLineSeries({ color: '#f59e0b', lineWidth: 2, crosshairMarkerVisible: false, priceScaleId: 'macdScale' });
            
            machSeries.setData(times.map((t, i) => ({ time: t, value: macd.histogram[i], color: macd.histogram[i] >= 0 ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' })).filter(d => d.value !== null));
            maclSeries.setData(times.map((t, i) => ({ time: t, value: macd.macdLine[i] })).filter(d => d.value !== null));
            sigSeries.setData(times.map((t, i) => ({ time: t, value: macd.signalLine[i] })).filter(d => d.value !== null));
            
            indicatorSeries[id] = [machSeries, maclSeries, sigSeries];
        }
    }
}

async function addCompareSeries(symbol, type) {
    try {
        const btn = document.getElementById('btn-add-compare');
        btn.textContent = 'Loading...';
        btn.disabled = true;

        let url = `/api/market/stock-candles?symbol=${symbol}&resolution=${currentInterval}`;
        if (type !== 'stock') {
            url = `/api/market/klines?symbol=${symbol}&interval=1d&limit=300`;
        }

        const res = await fetch(url);
        const data = await res.json();
        
        if (data && data.length) {
            chart.rightPriceScale().applyOptions({ mode: LightweightCharts.PriceScaleMode.Percentage });
            
            const lineData = data.map(k => ({ 
                time: k.time || k[0]/1000, 
                value: k.close || k[4] 
            })).sort((a,b) => a.time - b.time);
            
            const color = '#' + Math.floor(Math.random()*16777215).toString(16).padStart(6, '0');
            const series = chart.addLineSeries({ color: color, lineWidth: 2, priceScaleId: 'right' });
            series.setData(lineData);
            
            compareSeries[symbol] = series;
            
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
        }
    } catch (e) {
        console.error(e);
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
    if (Object.keys(compareSeries).length === 0) {
        chart.rightPriceScale().applyOptions({ mode: LightweightCharts.PriceScaleMode.Normal });
    }
}

// Data Fetchers
function fetchAnalystRecs() {
    fetch(`/api/market/analyst-estimates?symbol=${ASSET_SYMBOL}`)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('analyst-rec-container');
            if(!data || !data.recommendation) {
                container.innerHTML = `<div style="padding:1rem;color:var(--text-muted);text-align:center">No data available</div>`;
                return;
            }
            const rec = data.recommendation;
            let txt = 'HOLD';
            if (rec.mean < 2) txt = 'STRONG BUY';
            else if (rec.mean < 3) txt = 'BUY';
            else if (rec.mean > 4) txt = 'STRONG SELL';
            else if (rec.mean > 3) txt = 'SELL';
            
            const color = rec.mean < 3 ? 'var(--success)' : (rec.mean > 3 ? 'var(--danger)' : 'var(--text-primary)');
            
            const total = rec.strongBuy + rec.buy + rec.hold + rec.sell + rec.strongSell;
            
            container.innerHTML = `
                <div style="display:flex; justify-content: space-between; margin-bottom: var(--space-3)">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Target Mean</div>
                        <div class="text-mono" style="font-size: 1.5rem; font-weight: 700; color: var(--accent)">${rec.targetMean ? '$'+rec.targetMean.toFixed(2) : '--'}</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Consensus</div>
                        <div style="font-size: 1.2rem; font-weight: 700; color: ${color};">${txt}</div>
                    </div>
                </div>
                ${total > 0 ? `
                <div style="margin-top: 1rem;">
                    <div style="display:flex; height: 12px; border-radius: 6px; overflow:hidden;">
                        <div style="width:${(rec.strongBuy/total)*100}%; background:#22c55e" title="Strong Buy: ${rec.strongBuy}"></div>
                        <div style="width:${(rec.buy/total)*100}%; background:#86efac" title="Buy: ${rec.buy}"></div>
                        <div style="width:${(rec.hold/total)*100}%; background:#94a3b8" title="Hold: ${rec.hold}"></div>
                        <div style="width:${(rec.sell/total)*100}%; background:#fca5a5" title="Sell: ${rec.sell}"></div>
                        <div style="width:${(rec.strongSell/total)*100}%; background:#ef4444" title="Strong Sell: ${rec.strongSell}"></div>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.65rem; color:var(--text-muted); margin-top:4px">
                        <span>Buy (${rec.strongBuy+rec.buy})</span>
                        <span>Hold (${rec.hold})</span>
                        <span>Sell (${rec.sell+rec.strongSell})</span>
                    </div>
                </div>
                ` : ''}
            `;
        });
}

function fetchPeers() {
    fetch(`/api/market/peer-comparison?symbol=${ASSET_SYMBOL}`)
        .then(r => r.json())
        .then(peers => {
            const tbody = document.querySelector('#peer-table tbody');
            if(!peers || !peers.length) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">No peer data found</td></tr>';
                return;
            }
            tbody.innerHTML = peers.map(p => {
                const mcap= p.market_cap ? (p.market_cap / 1e9).toFixed(2)+'B' : '--';
                return `<tr style="cursor:pointer;" onclick="window.location.href='/asset?symbol=stock:${p.symbol}'">
                    <td style="font-weight:bold; color: var(--accent)">${p.symbol}</td>
                    <td class="text-mono">$${(p.price||0).toFixed(2)}</td>
                    <td class="align-right text-mono">${mcap}</td>
                    <td class="align-right text-mono">${p.pe_ratio ? p.pe_ratio.toFixed(2) : '--'}</td>
                </tr>`;
            }).join('');
        });
}

function fetchNews(url) {
    fetch(url)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('news-container');
            const articles = Array.isArray(data) ? data : (data.articles || []);
            
            if (!articles.length) {
                container.innerHTML = '<div style="padding: 2rem; color: var(--text-muted); text-align: center;">No related news found.</div>';
                return;
            }
            
            let html = '';
            articles.slice(0, 5).forEach(n => {
                const img = n.image ? `<img src="${n.image}" alt="thumb" style="width:60px; height:60px; object-fit:cover; border-radius: var(--radius-sm); margin-right: var(--space-3);">` : '';
                const timeStr = n.datetime ? new Date(n.datetime * 1000).toLocaleString() : '';
                html += `
                <a href="${n.url}" target="_blank" style="display:flex; padding: var(--space-3); border-bottom: 1px solid rgba(255,255,255,0.05); text-decoration: none; color: inherit; transition: background 0.2s;">
                    ${img}
                    <div style="flex: 1;">
                        <h4 style="margin: 0 0 4px 0; font-size: 0.9rem; color: var(--text-primary); leading-trim: both; text-edge: cap;">${n.headline}</h4>
                        <div style="font-size: 0.7rem; color: var(--text-muted); display: flex; justify-content: space-between;">
                            <span>${n.source}</span>
                            <span>${timeStr}</span>
                        </div>
                    </div>
                </a>`;
            });
            container.innerHTML = html;
        });
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Project\market_pro\resources\views/asset/show.blade.php ENDPATH**/ ?>