@extends('layouts.app')

@section('title', 'Trading — ' . ($type === 'stock' ? ($profile['name'] ?? $cleanSymbol) : ($symbol === 'PAXGUSDT' ? 'XAU/USD' : $symbol)))
@section('page-title', $type === 'stock' ? 'Asset Analysis: ' . ($profile['name'] ?? $cleanSymbol) : (($symbol === 'PAXGUSDT' ? 'XAU/USD' : str_replace('USDT', '/USDT', $symbol)) . ' Trading View'))

@section('content')
{{-- Symbol Header --}}
<div style="display:flex;align-items:center;gap:var(--space-4);margin-bottom:var(--space-2)">
    <span class="text-h2 text-mono" id="tv-price">
        @if($type === 'stock' || $type === 'forex')
            @if($quote && isset($quote['price']))
                ${{ number_format((float)$quote['price'], 2) }}
            @else
                <span class="text-muted">Loading...</span>
            @endif
        @elseif($ticker)
            ${{ number_format((float)$ticker['lastPrice'], 2) }}
        @else
            <span class="text-muted">Loading...</span>
        @endif
    </span>
    
    @if($type === 'stock' || $type === 'forex')
        @if($quote && isset($quote['change_pct']))
        <span class="kpi-delta {{ (float)$quote['change_pct'] >= 0 ? 'positive' : 'negative' }}">
            {{ (float)$quote['change_pct'] >= 0 ? '▲' : '▼' }}
            {{ number_format(abs((float)$quote['change_pct']), 2) }}%
        </span>
        @endif
        @if($type === 'stock' && $quote)
        <span class="text-caption">
            H: ${{ number_format((float)($quote['high'] ?? 0), 2) }}
            &nbsp;L: ${{ number_format((float)($quote['low'] ?? 0), 2) }}
            &nbsp;Vol: {{ number_format((float)($quote['volume'] ?? 0) / 1e6, 2) }}M
        </span>
        @endif
    @elseif($ticker)
        <span class="kpi-delta {{ (float)$ticker['priceChangePercent'] >= 0 ? 'positive' : 'negative' }}">
            {{ (float)$ticker['priceChangePercent'] >= 0 ? '▲' : '▼' }}
            {{ number_format(abs((float)$ticker['priceChangePercent']), 2) }}%
        </span>
        <span class="text-caption">
            H: ${{ number_format((float)$ticker['highPrice'], 2) }}
            &nbsp;L: ${{ number_format((float)$ticker['lowPrice'], 2) }}
            &nbsp;Vol: {{ number_format((float)$ticker['quoteVolume'] / 1e6, 1) }}M USDT
        </span>
    @endif
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
    {{-- Left: Immersive Chart --}}
    <div class="panel chart-dock acrylic" style="border-radius: var(--radius-lg)">
        <div class="chart-toolbar" style="padding:var(--space-3) var(--space-4); border-bottom: 1px solid var(--border); display:flex; align-items:center; background: rgba(0,0,0,0.2)">
            <div class="chart-timeframe" id="timeframe-selector" style="display:flex; gap:8px">
                <button class="btn btn-ghost btn-sweep btn-sm" data-tf="1m">1m</button>
                <button class="btn btn-ghost btn-sweep btn-sm" data-tf="5m">5m</button>
                <button class="btn btn-ghost btn-sweep btn-sm {{ $interval === '15m' ? 'btn-primary' : '' }}" data-tf="15m">15m</button>
                <button class="btn btn-ghost btn-sweep btn-sm {{ $interval === '1h'  ? 'btn-primary' : '' }}" data-tf="1h">1h</button>
                <button class="btn btn-ghost btn-sweep btn-sm {{ $interval === '4h'  ? 'btn-primary' : '' }}" data-tf="4h">4h</button>
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
            </div>
            
            <span class="text-small" style="margin-left:auto;text-shadow:0 0 10px rgba(255,255,255,0.2)">Edge-to-Edge Holographic Chart</span>
        </div>
        
        {{-- GP Indicators Modal/Dropdown --}}
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
        
        <div class="chart-container" id="chart-container" style="flex:1; width:100%;">
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted)">
                <span>Loading immersive chart...</span>
            </div>
        </div>
    </div>

    {{-- Right Sidebar: Floating Dock (HUD + Orderbook + Trades for Crypto, Stats for Stocks) --}}
    <div class="side-dock fade-in-up" style="--delay: 0.3s;">
        @if($type === 'crypto' || $type === 'commodity')
            {{-- Futuristic Prediction HUD --}}
            <div class="panel acrylic animated-border" style="flex-shrink: 0; padding: 1px;">
                <!-- ... existing prediction HUD ... -->
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
                        
                        {{-- Circular progress meter --}}
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
                                <div style="position:absolute; top:0; left:0; width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 0 14px; box-sizing:border-box;">
                                    <div class="signal-action" id="signal-action-text" style="font-size:1.1rem;font-weight:700;letter-spacing:0.5px;text-align:center;line-height:1.25;text-shadow: 0 0 15px rgba(255,255,255,0.2)">—</div>
                                    <div class="signal-confidence" id="signal-conf-text" style="font-size:0.7rem; color:var(--text-muted); font-family:var(--font-mono); margin-top:5px">CALC%</div>
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

                    <div class="text-caption" style="margin-bottom:var(--space-2)">Raw Indicators</div>
                    <div id="indicator-list" style="display:flex;flex-direction:column;"></div>
                </div>
            </div>
        @else
            {{-- Stock/Forex Key Stats --}}
            <div class="panel acrylic" style="flex-shrink: 0">
                <div class="panel-header">
                    <span class="panel-title">Key Statistics</span>
                </div>
                <div class="panel-body no-padding">
                    <div style="padding: var(--space-4); display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        @if($type === 'stock' && $profile && isset($profile['fundamentals']))
                            @php $f = $profile['fundamentals']; @endphp
                            <div class="stat-item">
                                <div class="stat-label">Market Cap</div>
                                <div class="stat-value">${{ number_format(($profile['market_cap'] ?? 0)/1e9, 2) }}B</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">P/E Ratio</div>
                                <div class="stat-value">{{ number_format((float)($f['pe_ratio'] ?? 0), 2) }}</div>
                            </div>
                            <!-- ... other stats ... -->
                        @else
                            <div class="stat-item">
                                <div class="stat-label">Type</div>
                                <div class="stat-value" style="text-transform: capitalize;">{{ $type }}</div>
                            </div>
                            @if(isset($quote['volume']))
                            <div class="stat-item">
                                <div class="stat-label">24h Volume</div>
                                <div class="stat-value">{{ number_format($quote['volume'], 2) }}</div>
                            </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            @if($type === 'stock')
                <div class="panel acrylic" style="flex-shrink: 0">
                    <div class="panel-header">
                        <span class="panel-title">Analyst Recommendations</span>
                    </div>
                    <div class="panel-body" id="analyst-rec-container">
                        <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 2rem;">Loading recommendations...</div>
                    </div>
                </div>
                
                <div class="panel acrylic" style="flex: 1;">
                    <div class="panel-header">
                        <span class="panel-title">🔗 Peer Comparison</span>
                    </div>
                    <div class="panel-body no-padding" style="overflow-y: auto;">
                        <table class="data-table" id="peer-table">
                            <thead>
                                <tr><th>Symbol</th><th>Price</th><th class="align-right">Mkt Cap</th></tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="3" style="text-align:center; padding: 2rem; color: var(--text-muted)">Loading peers...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif

        {{-- Live TV News Panel --}}
        <div class="panel acrylic fade-in-up" style="--delay: 0.4s; flex-shrink: 0; margin-top: auto;">
            <div class="panel-header" style="padding: 10px 14px; background: rgba(0,0,0,0.25); border-bottom: 1px solid rgba(255,255,255,0.05);">
                <span class="panel-title" style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" style="filter: drop-shadow(0 0 5x var(--danger))"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33 2.78 2.78 0 0 0 1.94 2c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.33 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/></svg>
                    Live Financial News
                </span>
                <div class="panel-actions">
                    <span class="live-indicator-small" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.65rem; color: var(--danger); font-weight: 700; text-transform: uppercase;"><div style="width: 4px; height: 4px; border-radius: 50%; background: var(--danger); animation: pulse-red 2s infinite;"></div> LIVE</span>
                </div>
            </div>
            <div class="panel-body no-padding">
                <div style="padding: 8px; background: rgba(0,0,0,0.15); border-bottom: 1px solid rgba(255,255,255,0.03);">
                    <select id="live-tv-channel-trading" class="form-control" style="width: 100%; border-radius: var(--radius-sm); font-family: var(--font-sans); font-size: 0.75rem; background-color: rgba(255,255,255,0.05); color: var(--text-primary); border: 1px solid rgba(255,255,255,0.1); padding: 4px 8px; cursor: pointer; appearance: auto;" onchange="changeLiveTvChannelTrading()">
                        <option value="iEpJwprxDdk">Bloomberg Television</option>
                        <option value="KQp-e_XQnDE">Yahoo Finance</option>
                        <option value="XWq5kBlakcQ">CNA</option>
                        <option value="LuKwFajn37U">DW News</option>
                    </select>
                </div>
                <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; background: #000;">
                    <iframe id="live-tv-iframe-trading" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" src="https://www.youtube.com/embed/iEpJwprxDdk?autoplay=1&mute=1" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function changeLiveTvChannelTrading() {
        var videoId = document.getElementById('live-tv-channel-trading').value;
        var iframe = document.getElementById('live-tv-iframe-trading');
        iframe.src = "https://www.youtube.com/embed/" + videoId + "?autoplay=1&mute=1";
    }
</script>

{{-- Bottom section: News (Unified) --}}
<div class="grid-1 fade-in-up" style="--delay: 0.4s; margin-top: var(--space-4)">
    <div class="panel acrylic">
        <div class="panel-header">
            <span class="panel-title">📰 Related News</span>
        </div>
        <div class="panel-body no-padding" style="max-height: 400px; overflow-y: auto;" id="news-container">
            <div style="padding: 2rem; color: var(--text-muted); text-align: center;">Loading news...</div>
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
@endsection

@section('scripts')
<script>
const SYMBOL = '{{ $symbol }}';
const ASSET_TYPE = '{{ $type }}';
const ASSET_SYMBOL = '{{ $cleanSymbol }}';
let currentInterval = '{{ $interval }}';
const initialKlines = @json($klines);

document.addEventListener('DOMContentLoaded', function() {
    // If server returned klines, render immediately. Otherwise load via API.
    if (initialKlines && initialKlines.length > 0) {
        initChart(initialKlines);
    } else {
        fetchAndRenderChart(SYMBOL, currentInterval);
    }
    
    // Conditional logic for Crypto/Commodity vs Stocks/Forex
    if (ASSET_TYPE === 'crypto' || ASSET_TYPE === 'commodity') {
        // initSSE(); // Deprecated
        initWebSockets();
        initBinanceWS();
    } else {
        // For Stocks/Forex, hide crypto-only elements if they exist
        const badge = document.getElementById('stream-badge');
        if (badge) badge.style.display = 'none';
        
        if (ASSET_TYPE === 'stock') {
            fetchAnalystRecs();
            fetchPeers();
        }
    }
    
    // News is global
    fetchNews();
    
    initTimeframeSelector();
    
    // Bind tool logic ONCE
    initChartTools();
});

// ─── CHART ───────────────────────────────────────────────────
let chart, candleSeries, barSeries, lineSeries, volumeSeries;
let currentBar = null;
let currentChartType = 'Candlestick'; // Candlestick, Bar, Line

// Store indicator series references to remove them
let indicatorSeries = {};

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
        rightPriceScale: { borderColor: '#1e293b', scaleMargins: { top: 0.1, bottom: 0.25 } },
        timeScale: { borderColor: '#1e293b', timeVisible: true, secondsVisible: false },
    });

    // Create all 3 basic types but only show Candle initially
    candleSeries = chart.addCandlestickSeries({ upColor: '#10b981', downColor: '#ef4444', borderUpColor: '#10b981', borderDownColor: '#ef4444', wickUpColor: '#10b98188', wickDownColor: '#ef444488' });
    barSeries = chart.addBarSeries({ upColor: '#10b981', downColor: '#ef4444', thinBars: false, visible: false });
    lineSeries = chart.addLineSeries({ color: '#3b82f6', lineWidth: 2, visible: false });

    volumeSeries = chart.addHistogramSeries({
        color: '#3b82f6', priceFormat: { type: 'volume' },
        priceScaleId: '',
        scaleMargins: { top: 0.85, bottom: 0 },
        // To prevent overlap, we add an overlay flag if the library version supports it, 
        // but the main fix is keeping priceScaleId: '' and ensuring the top margin is large enough.
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

async function fetchAndRenderChart(symbol, interval) {
    const container = document.getElementById('chart-container');
    if (container) container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted)">Loading chart from server...</div>';
    
    let loaded = false;
    let url = `{{ url('/api/market/klines') }}?symbol=${symbol}&interval=${interval}&limit=200`;

    if (ASSET_TYPE === 'stock') {
        const now = Math.floor(Date.now() / 1000);
        let from = now - (365 * 86400); 
        if (interval === 'W' || interval === '1w') from = now - (365 * 3 * 86400);
        if (interval === 'M' || interval === '1M') from = now - (365 * 5 * 86400);
        
        // Map common intervals to Finnhub style if needed, though our stock-candles usually takes D, W, M
        let res = interval;
        if (res === '1d') res = 'D';
        if (res === '1w') res = 'W';
        if (res === '1M') res = 'M';

        url = `/api/market/stock-candles?symbol=${ASSET_SYMBOL}&resolution=${res}&from=${from}&to=${now}`;
    }
    
    // 1. Always try local Laravel API FIRST (most reliable in this env)
    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            const r = await fetch(url, { signal: AbortSignal.timeout(15000) });
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
        container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted)"><div class="empty-state"><div class="empty-state-icon">📡</div><div class="empty-state-title">Could not load chart data</div><div class="empty-state-desc">Server cannot reach API. Check your network connection.</div></div></div>';
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
        const predSignal = document.getElementById('pred-signal');
        // Only update the label text, don't wipe the SVG ring
        const sigLabel = predSignal ? predSignal.querySelector('.signal-label') : null;
        if (sigLabel) sigLabel.textContent = 'Waiting for sufficient data...';
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
    const predTargets = document.getElementById('prediction-targets');
    if (predTargets) predTargets.style.display = 'block';
    
    // Choose TP/SL from high-accuracy levels
    const tp = data.tp || data.price_target_buy || data.price_target_sell;
    const sl = data.sl || data.stop_loss_buy || data.stop_loss_sell;
    
    const tpEl = document.getElementById('target-tp');
    const slEl = document.getElementById('target-sl');
    const rrEl = document.getElementById('target-rr');
    if (tpEl) tpEl.textContent = '$' + formatPrice(tp);
    if (slEl) slEl.textContent = '$' + formatPrice(sl);
    if (rrEl) rrEl.textContent = data.risk_reward;
    
    const tsEl = document.getElementById('trend-strength');
    if (tsEl) {
        tsEl.textContent = data.trend_strength;
        tsEl.className = data.trend_strength.includes('STRONG') ? 'text-success fw-bold' : 'text-muted';
    }
    
    const mrBadge = document.getElementById('market-regime-badge');
    if (mrBadge) {
        mrBadge.style.display = 'inline-block';
        mrBadge.textContent = data.market_regime;
        mrBadge.className = `badge text-small ${data.market_regime === 'TRENDING' ? 'badge-buy' : 'badge-neutral'}`;
    }

    // 3. Category Breakdown (New Multi-layer view)
    const catEl = document.getElementById('category-list');
    if (catEl) {
        let catHtml = '';
        for (const [catName, catData] of Object.entries(data.categories)) {
            const cSig = catData.signal.includes('BUY') ? 'buy' : (catData.signal.includes('SELL') ? 'sell' : 'neutral');
            
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
    }

    // 4. Raw Indicator List
    const listEl = document.getElementById('indicator-list');
    if (listEl) {
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
}

// ─── SERVER-SENT EVENTS (SSE) STREAMING ─────────
// Deprecated: Use initWebSockets instead
function initSSE() {
    console.warn('initSSE is deprecated. Use initWebSockets.');
}

function initWebSockets() {
    if (!window.Echo) {
        console.error('Laravel Echo not found.');
        return;
    }

    console.info(`[Trading] Subscribing to market.${SYMBOL} and market.all...`);

    // Listen to specific symbol channel
    window.Echo.channel(`market.${SYMBOL}`)
        .listen('.updated', (e) => {
            const data = e.data;
            handleBroadcastData(data);
        });

    // Also listen to 'all' for shared components like Ticker Bar
    window.Echo.channel('market.all')
        .listen('.updated', (e) => {
            const data = e.data;
            
            // If this 'all' broadcast is actually for our symbol, handle it
            // (The command broadcasts same payload to both channels)
            if (e.symbol === SYMBOL) {
                handleBroadcastData(data);
            } else {
                // Just update shared components
                if (data.watchlist) renderWatchlist(data.watchlist);
                if (data.top_pairs && typeof window.renderTickerBar === 'function') {
                    window.renderTickerBar(data.top_pairs);
                }
            }
        });
}

function handleBroadcastData(data) {
    // 1. Update Order Book
    if (data.trading && data.trading.depth) renderOrderBook(data.trading.depth);
    else if (data.depth) renderOrderBook(data.depth);
    
    // 2. Update Trades
    if (data.trading && data.trading.trades) renderTrades(data.trading.trades);
    else if (data.trades) renderTrades(data.trades);
    
    // 3. Update Ticker Header
    if (data.trading && data.trading.ticker) {
        const t = data.trading.ticker;
        const headerPrice = document.querySelector('.header-price');
        const headerChange = document.querySelector('.header-change');
        if (headerPrice) headerPrice.textContent = '$' + formatPrice(parseFloat(t.lastPrice));
        if (headerChange) {
            const c = parseFloat(t.priceChangePercent);
            headerChange.textContent = `${c >= 0 ? '+' : ''}${c.toFixed(2)}%`;
            headerChange.className = `header-change ${c >= 0 ? 'text-success' : 'text-danger'}`;
        }
    }
    
    // 4. Update Shared Components
    if (data.watchlist) renderWatchlist(data.watchlist);
    if (data.top_pairs && typeof window.renderTickerBar === 'function') {
        window.renderTickerBar(data.top_pairs);
    }
    
    // 5. Update Prediction
    if (data.trading && data.trading.prediction) {
        renderPrediction(data.trading.prediction);
    } else if (data.predictions) {
        let targetPrediction = data.predictions['15m'];
        if (currentInterval === '1h') targetPrediction = data.predictions['1h'];
        else if (currentInterval === '4h' || currentInterval === '1d') targetPrediction = data.predictions['4h'];
        
        if (targetPrediction) renderPrediction(targetPrediction);
    }
}

function setBadge(type, text) {
    const dot = document.getElementById('global-status-dot');
    if (!dot) return;
    if (type === 'live') {
        dot.style.background = 'var(--success)';
        dot.style.boxShadow = '0 0 6px var(--success)';
    } else {
        dot.style.background = 'var(--warning)';
        dot.style.boxShadow = '0 0 6px var(--warning)';
    }
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
            
            // Cleanup GP indicators on timeframe change
            indicatorSeries = {};
            document.querySelectorAll('.indicator-toggle').forEach(cb => cb.checked = false);
            
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
    if (price === undefined || price === null || isNaN(price)) return '--';
    if (typeof price === 'string') price = parseFloat(price);
    if (price >= 1000) return price.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (price >= 1) return price.toFixed(2);
    if (price >= 0.01) return price.toFixed(4);
    return price.toFixed(6);
}

// ─── DATA FETCHERS (STOCKS/NEWS) ──────────────────────────────
function fetchAnalystRecs() {
    fetch(`/api/market/analyst-estimates?symbol=${ASSET_SYMBOL}`)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('analyst-rec-container');
            if(!container) return;
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
            if(!tbody) return;
            if(!peers || !peers.length) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted)">No peer data found</td></tr>';
                return;
            }
            tbody.innerHTML = peers.map(p => {
                const mcap= p.market_cap ? (p.market_cap / 1e9).toFixed(2)+'B' : '--';
                return `<tr style="cursor:pointer;" onclick="window.location.href='/trading?symbol=stock:${p.symbol}'">
                    <td style="font-weight:bold; color: var(--accent)">${p.symbol}</td>
                    <td class="text-mono">$${(p.price||0).toFixed(2)}</td>
                    <td class="align-right text-mono">${mcap}</td>
                </tr>`;
            }).join('');
        });
}

function fetchNews() {
    let newsUrl = `/api/market/news?category=general`;
    if (ASSET_TYPE === 'stock') newsUrl = `/api/market/company-news?symbol=${ASSET_SYMBOL}`;
    else if (ASSET_TYPE === 'crypto' || ASSET_TYPE === 'commodity') newsUrl = `/api/market/news?category=crypto`;
    else if (ASSET_TYPE === 'forex') newsUrl = `/api/market/news?category=forex`;

    fetch(newsUrl)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('news-container');
            if(!container) return;
            const articles = Array.isArray(data) ? data : (data.articles || []);
            
            if (!articles.length) {
                container.innerHTML = '<div style="padding: 2rem; color: var(--text-muted); text-align: center;">No related news found.</div>';
                return;
            }
            
            let html = '';
            articles.slice(0, 6).forEach(n => {
                const img = n.image ? `<img src="${n.image}" alt="thumb" style="width:60px; height:60px; object-fit:cover; border-radius: var(--radius-sm); margin-right: var(--space-3);">` : '';
                const timeStr = n.datetime ? new Date(n.datetime * 1000).toLocaleString() : '';
                html += `
                <a href="${n.url}" target="_blank" style="display:flex; padding: var(--space-3); border-bottom: 1px solid rgba(255,255,255,0.05); text-decoration: none; color: inherit; transition: background 0.2s;">
                    ${img}
                    <div style="flex: 1;">
                        <h4 style="margin: 0 0 4px 0; font-size: 0.9rem; color: var(--text-primary);">${n.headline}</h4>
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
// ─── BINANCE WEBSOCKET FOR REALTIME CHART ─────────────────────
let binanceWS = null;
function initBinanceWS() {
    if (binanceWS) binanceWS.close();
    
    if (ASSET_TYPE !== 'crypto') return; // Only crypto has a public WS stream from Binance here
    let wsInterval = currentInterval.toLowerCase();
    binanceWS = new WebSocket(`wss://data-stream.binance.vision/ws/${SYMBOL.toLowerCase().replace('stock:','').replace('forex:','') }@kline_${wsInterval}`);
    
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
@endsection
