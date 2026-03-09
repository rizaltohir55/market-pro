@extends('layouts.app')

@section('title', 'Analysis — ' . $symbol)
@section('page-title')
    <div style="display:flex;align-items:center;gap:var(--space-3)">
        {{ str_replace('USDT', '/USDT', $symbol) }} Technical Analysis
        <div class="header-price-badge">
            <span class="text-caption" style="font-size:0.6rem;opacity:0.6">LIVE</span>
            <span id="header-price-val" class="text-mono">${{ number_format($signal15m['price'] ?? $ticker['lastPrice'] ?? 0, 2) }}</span>
        </div>
    </div>
@endsection

@section('content')
{{-- Multi-Timeframe Signals --}}
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr)">
    @foreach([
        ['label' => '15 Min Signal', 'data' => $signal15m, 'tf' => '15m'],
        ['label' => '1 Hour Signal', 'data' => $signal1h, 'tf' => '1h'],
        ['label' => '4 Hour Signal', 'data' => $signal4h, 'tf' => '4h'],
    ] as $sig)
    @php
        $sigVal = $sig['data']['signal'] ?? 'NEUTRAL';
        $sigClass = str_contains($sigVal, 'BUY') ? 'buy' : (str_contains($sigVal, 'SELL') ? 'sell' : 'neutral');
        $tp = str_contains($sigVal, 'BUY') ? ($sig['data']['price_target_buy'] ?? 0) : ($sig['data']['price_target_sell'] ?? 0);
        $sl = str_contains($sigVal, 'BUY') ? ($sig['data']['stop_loss_buy'] ?? 0) : ($sig['data']['stop_loss_sell'] ?? 0);
    @endphp
    <div class="prediction-signal {{ $sigClass }}" style="border-radius:var(--radius-md)" data-tf="{{$sig['tf']}}" id="kpi-{{$sig['tf']}}">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <div class="signal-label" style="font-weight:bold">{{ $sig['label'] }}</div>
            <span class="badge badge-neutral text-small regime-badge">{{ $sig['data']['market_regime'] ?? 'UNKNOWN' }}</span>
        </div>
        <div class="signal-action" style="font-size:1.5rem">{{ $sigVal }}</div>
        <div class="signal-confidence" style="margin-top:4px">
            Conf: {{ $sig['data']['confidence'] ?? 0 }}% 
            <span style="opacity:0.7">| B: {{ $sig['data']['buy_score'] ?? 0 }} S: {{ $sig['data']['sell_score'] ?? 0 }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.1);font-size:0.75rem">
            <div><span style="opacity:0.7">Target</span><br><strong class="tp-val text-success">${{ number_format($tp, 2) }}</strong></div>
            <div style="text-align:right"><span style="opacity:0.7">Stop</span><br><strong class="sl-val text-danger">${{ number_format($sl, 2) }}</strong></div>
        </div>
    </div>
    @endforeach
</div>

<div class="analysis-layout-grid" style="display: grid; grid-template-columns: 1fr 340px; gap: var(--space-4); margin-top: var(--space-4); align-items: start;">
    {{-- Main Analysis Column --}}
    <div class="analysis-main-col" style="display: flex; flex-direction: column; gap: var(--space-4);">
        <div class="grid-2" style="align-items:start">
            {{-- Indicator Breakdown —  15m --}}
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Indicator Breakdown (15m)
                    </span>
                </div>
                <div class="panel-body no-padding">
                    <div class="indicator-list" id="indicator-list-15m">
                        @foreach($signal15m['indicators'] as $ind)
                        <div class="indicator-row">
                            <span class="indicator-name">{{ $ind['name'] }}</span>
                            @php
                                $sc = in_array($ind['signal'], ['BUY','BULLISH']) ? 'text-success' :
                                       (in_array($ind['signal'], ['SELL','BEARISH']) ? 'text-danger' : 'text-muted');
                                $bc = in_array($ind['signal'], ['BUY','BULLISH']) ? 'buy' :
                                       (in_array($ind['signal'], ['SELL','BEARISH']) ? 'sell' : 'neutral');
                            @endphp
                            <span class="indicator-value {{$sc}}">{{ $ind['value'] }}</span>
                            <span class="badge badge-{{$bc}}">{{ $ind['signal'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Support & Resistance --}}
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="2"><path d="M21 3L3 21"/><path d="M21 21L3 3"/></svg>
                        Support & Resistance levels
                    </span>
                </div>
                <div class="panel-body">
                    <div style="display:flex;flex-direction:column;gap:var(--space-3)">
                        @if(!empty($sr['resistance']))
                        <div>
                            <div class="text-caption" style="margin-bottom:var(--space-2);text-transform:uppercase;letter-spacing:0.06em;font-weight:600;color:var(--danger)">Resistance</div>
                            @foreach($sr['resistance'] as $level)
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-1) var(--space-3);font-family:var(--font-mono);font-size:0.875rem">
                                <span class="text-danger">${{ number_format($level, 2) }}</span>
                                @php $dist = $signal15m['price'] ?? 0 ? (($level - ($signal15m['price'] ?? 0)) / ($signal15m['price'] ?? 1)) * 100 : 0; @endphp
                                <span class="text-muted">{{ $dist >= 0 ? '+' : '' }}{{ number_format($dist, 2) }}%</span>
                            </div>
                            @endforeach
                        </div>
                        @endif


                        @if(!empty($sr['support']))
                        <div>
                            <div class="text-caption" style="margin-bottom:var(--space-2);text-transform:uppercase;letter-spacing:0.06em;font-weight:600;color:var(--success)">Support</div>
                            @foreach($sr['support'] as $level)
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-1) var(--space-3);font-family:var(--font-mono);font-size:0.875rem">
                                <span class="text-success">${{ number_format($level, 2) }}</span>
                                @php $dist = $signal15m['price'] ?? 0 ? (($level - ($signal15m['price'] ?? 0)) / ($signal15m['price'] ?? 1)) * 100 : 0; @endphp
                                <span class="text-muted">{{ number_format($dist, 2) }}%</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Analysis Summary --}}
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Analysis Summary
                </span>
            </div>
            <div class="panel-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--space-4)">
                    <div>
                        <div class="text-caption" style="margin-bottom:var(--space-2)">15 Minute</div>
                        <p id="summary-15m" style="font-size:0.875rem;color:var(--text-secondary)">{{ $signal15m['summary'] }}</p>
                    </div>
                    <div>
                        <div class="text-caption" style="margin-bottom:var(--space-2)">1 Hour</div>
                        <p id="summary-1h" style="font-size:0.875rem;color:var(--text-secondary)">{{ $signal1h['summary'] }}</p>
                    </div>
                    <div>
                        <div class="text-caption" style="margin-bottom:var(--space-2)">4 Hour</div>
                        <p id="summary-4h" style="font-size:0.875rem;color:var(--text-secondary)">{{ $signal4h['summary'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Chart for this symbol --}}
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Price Chart (15m)</span>
            </div>
            <div class="chart-container" id="analysis-chart" style="height:350px"></div>
        </div>
    </div>

    {{-- Live TV Sidebar Module --}}
    <div class="analysis-sidebar-col" style="display: flex; flex-direction: column; gap: var(--space-4);">
        <div class="panel acrylic fade-in-up" style="--delay: 0.2s; position: sticky; top: var(--space-4);">
            <div class="panel-header">
                <span class="panel-title" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" style="filter: drop-shadow(0 0 5px var(--danger))"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33 2.78 2.78 0 0 0 1.94 2c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.33 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/></svg>
                    Live Financial News
                </span>
                <div class="panel-actions">
                    <span class="live-indicator" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.70rem; color: var(--danger); font-weight: 700; text-transform: uppercase; animation: pulse 2s infinite;"><div style="width: 6px; height: 6px; border-radius: 50%; background: var(--danger);"></div> LIVE</span>
                </div>
            </div>
            <div class="panel-body no-padding" style="display: flex; flex-direction: column;">
                <div style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.2);">
                    <select id="live-tv-channel-analysis" class="form-control" style="width: 100%; border-radius: var(--radius-md); font-family: var(--font-sans); font-size: 0.85rem; background-color: rgba(255,255,255,0.05); color: var(--text-main); border: 1px solid rgba(255,255,255,0.1); padding: 8px 12px; cursor: pointer; appearance: auto;" onchange="changeLiveTvChannelAnalysis()">
                        <option value="iEpJwprxDdk">Bloomberg Television</option>
                        <option value="KQp-e_XQnDE">Yahoo Finance</option>
                        <option value="XWq5kBlakcQ">CNA</option>
                        <option value="LuKwFajn37U">DW News</option>
                    </select>
                </div>
                <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; background: #0b0b0f; border-bottom-left-radius: var(--radius-lg); border-bottom-right-radius: var(--radius-lg);">
                    <iframe id="live-tv-iframe-analysis" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" src="https://www.youtube.com/embed/iEpJwprxDdk?autoplay=1&mute=1" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function changeLiveTvChannelAnalysis() {
        var videoId = document.getElementById('live-tv-channel-analysis').value;
        var iframe = document.getElementById('live-tv-iframe-analysis');
        iframe.src = "https://www.youtube.com/embed/" + videoId + "?autoplay=1&mute=1";
    }
</script>

<style>
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.4; }
        100% { opacity: 1; }
    }
    
    @media (max-width: 1100px) {
        .analysis-layout-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endsection

@section('scripts')
<script>
const SYMBOL = '{{ $symbol }}';
const initialKlines = @json($klines15m);
let chart, candleSeries;

document.addEventListener('DOMContentLoaded', function() {
    initAnalysisChart();
    initWatchlist();

    // Start persistent Real-Time Updates
    // initSSE(); // Deprecated
    initWebSockets();
    initBinanceWS();
});

// Deprecated: Use initWebSockets instead
function initSSE() {
    console.warn('initSSE is deprecated. Use initWebSockets.');
}

function initWebSockets() {
    if (!window.Echo) {
        console.error('Laravel Echo not found.');
        return;
    }

    console.info(`[Analysis] Subscribing to market.${SYMBOL} and market.all...`);

    // Listen to specific symbol channel (contains predictions for this symbol)
    window.Echo.channel(`market.${SYMBOL}`)
        .listen('.updated', (e) => {
            const data = e.data;
            handleBroadcastData(data);
        });

    // Listen to 'all' for shared components
    window.Echo.channel('market.all')
        .listen('.updated', (e) => {
            const data = e.data;
            
            if (e.symbol === SYMBOL) {
                handleBroadcastData(data);
            } else {
                if (data.watchlist) renderWatchlist(data.watchlist);
                if (data.top_pairs && typeof window.renderTickerBar === 'function') {
                    window.renderTickerBar(data.top_pairs);
                }
            }
        });
}

function handleBroadcastData(data) {
    // 1. Update Predictions
    // The payload might have predictions at the root or under trading
    const predictions = data.predictions || (data.trading ? data.trading.prediction : null);
    
    if (predictions) {
        // If it's the specific single prediction from 'trading' payload
        if (predictions.signal && !predictions['15m']) {
             updatePredictionDOM('15m', predictions); // Fallback to 15m slot
        } else {
            if (predictions['15m']) updatePredictionDOM('15m', predictions['15m']);
            if (predictions['1h'])  updatePredictionDOM('1h',  predictions['1h']);
            if (predictions['4h'])  updatePredictionDOM('4h',  predictions['4h']);
        }
    }
    
    // 2. Shared components
    if (data.top_pairs && typeof window.renderTickerBar === 'function') {
        window.renderTickerBar(data.top_pairs);
    }
    if (data.watchlist) renderWatchlist(data.watchlist);
    
    if (window.updateLastUpdate) window.updateLastUpdate();
}

let currentBar = null;

function initAnalysisChart() {
    const container = document.getElementById('analysis-chart');
    if (!container) return;

    chart = LightweightCharts.createChart(container, {
        width: container.offsetWidth,
        height: container.offsetHeight,
        layout: { background: { color: '#111827' }, textColor: '#94a3b8', fontFamily: "'JetBrains Mono', monospace", fontSize: 11 },
        grid: { vertLines: { color: '#1e293b' }, horzLines: { color: '#1e293b' } },
        rightPriceScale: { borderColor: '#1e293b' },
        timeScale: { borderColor: '#1e293b', timeVisible: true },
    });

    candleSeries = chart.addCandlestickSeries({
        upColor: '#10b981', downColor: '#ef4444',
        borderUpColor: '#10b981', borderDownColor: '#ef4444',
        wickUpColor: '#10b98188', wickDownColor: '#ef444488',
    });

    if (initialKlines && initialKlines.length) {
        const sorted = [...initialKlines].sort((a,b) => a.time - b.time);
        candleSeries.setData(sorted.map(k => ({ time: k.time, open: k.open, high: k.high, low: k.low, close: k.close })));
        chart.timeScale().fitContent();
        const last = sorted[sorted.length - 1];
        if (last) currentBar = { ...last };
    }

    new ResizeObserver(entries => {
        const { width, height } = entries[0].contentRect;
        if (width > 0 && height > 0) chart.applyOptions({ width, height });
    }).observe(container);
}

// SSE handles real-time data push internally

function updatePredictionDOM(tf, data) {
    if (!data) return;
    
    // Update KPI Card
    const card = document.getElementById('kpi-' + tf);
    if (card) {
        let sigClass = 'neutral';
        if (data.signal.includes('BUY')) sigClass = 'buy';
        if (data.signal.includes('SELL')) sigClass = 'sell';
        
        card.className = `prediction-signal ${sigClass}`;
        
        const regimeEl = card.querySelector('.regime-badge');
        if (regimeEl) regimeEl.textContent = data.market_regime;
        
        const actionEl = card.querySelector('.signal-action');
        if (actionEl) actionEl.textContent = data.signal;
        
        const confEl = card.querySelector('.signal-confidence');
        if (confEl) confEl.innerHTML = `Conf: ${data.confidence}% <span style="opacity:0.7">| B: ${data.buy_score} S: ${data.sell_score}</span>`;
        
        const tp = data.signal.includes('BUY') ? (data.price_target_buy || 0) : (data.price_target_sell || 0);
        const sl = data.signal.includes('BUY') ? (data.stop_loss_buy || 0) : (data.stop_loss_sell || 0);
        
        const tpEl = card.querySelector('.tp-val');
        if (tpEl) tpEl.textContent = '$' + formatPrice(tp);
        
        const slEl = card.querySelector('.sl-val');
        if (slEl) slEl.textContent = '$' + formatPrice(sl);

        // Update Header Price if available in prediction payload
        const headerPrice = document.getElementById('header-price-val');
        if (headerPrice && data.price) headerPrice.textContent = '$' + formatPrice(data.price);
    }
    
    // Update Summary
    const summary = document.getElementById('summary-' + tf);
    if (summary) summary.textContent = data.summary;

    // Update Indicator List
    if (tf === '15m') {
        const listEl = document.getElementById('indicator-list-15m');
        if (listEl && data.indicators) {
            listEl.innerHTML = data.indicators.map(ind => {
                const sc = ['BUY','BULLISH'].includes(ind.signal)?'text-success':['SELL','BEARISH'].includes(ind.signal)?'text-danger':'text-muted';
                const bc = ['BUY','BULLISH'].includes(ind.signal)?'buy':['SELL','BEARISH'].includes(ind.signal)?'sell':'neutral';
                return `<div class="indicator-row">
                            <span class="indicator-name">${ind.name}</span>
                            <span class="indicator-value ${sc}">${ind.value}</span>
                            <span class="badge badge-${bc}">${ind.signal}</span>
                        </div>`;
            }).join('');
        }
    }
}



function renderWatchlist(tickers) {
    const map = {}; tickers.forEach(t => map[t.symbol] = t);
    const symbols = ['BTCUSDT','ETHUSDT','SOLUSDT','BNBUSDT','XRPUSDT','DOGEUSDT'];
    let html = '';
    symbols.forEach(s => {
        const t = map[s]; if (!t) return;
        const change = parseFloat(t.priceChangePercent);
        const cls = change >= 0 ? 'positive' : 'negative';
        html += `<div class="watchlist-item" onclick="window.location='/trading?symbol=${s}'"><span class="wl-symbol">${s.replace('USDT','/USDT')}</span><span class="wl-price">$${formatPrice(parseFloat(t.lastPrice))}</span><span class="wl-change ${cls}">${change>=0?'+':''}${change.toFixed(2)}%</span></div>`;
    });
    document.getElementById('watchlist-items').innerHTML = html;
    if (window.updateLastUpdate) window.updateLastUpdate();
}

function initWatchlist() {
    fetch('{{ url('/api/market/ticker') }}')
        .then(r => r.json())
        .then(renderWatchlist)
        .catch(() => {});
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
    
    // For analysis page, the user looks at the 15m chart.
    binanceWS = new WebSocket(`wss://data-stream.binance.vision/ws/${SYMBOL.toLowerCase()}@kline_15m`);
    
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
                close: parseFloat(kline.c)
            };
            
            currentBar = tickBar;
            
            if (candleSeries) {
                try {
                    candleSeries.update(tickBar);
                } catch(e) {}
            }

            // Update Header Price from WS Tick
            const headerPrice = document.getElementById('header-price-val');
            if (headerPrice) {
                headerPrice.textContent = '$' + formatPrice(tickBar.close);
            }
        } catch(e) {}
    };
    
    binanceWS.onerror = () => console.warn('Binance WS error');
    binanceWS.onclose = () => setTimeout(initBinanceWS, 5000);
}
</script>
@endsection
