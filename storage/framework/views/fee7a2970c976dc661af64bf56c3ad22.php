

<?php $__env->startSection('title', 'Analysis — ' . $symbol); ?>
<?php $__env->startSection('page-title', str_replace('USDT', '/USDT', $symbol) . ' Technical Analysis'); ?>

<?php $__env->startSection('content'); ?>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr)">
    <?php $__currentLoopData = [
        ['label' => '15 Min Signal', 'data' => $signal15m, 'tf' => '15m'],
        ['label' => '1 Hour Signal', 'data' => $signal1h, 'tf' => '1h'],
        ['label' => '4 Hour Signal', 'data' => $signal4h, 'tf' => '4h'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sig): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
        $sigVal = $sig['data']['signal'] ?? 'NEUTRAL';
        $sigClass = str_contains($sigVal, 'BUY') ? 'buy' : (str_contains($sigVal, 'SELL') ? 'sell' : 'neutral');
        $tp = str_contains($sigVal, 'BUY') ? ($sig['data']['price_target_buy'] ?? 0) : ($sig['data']['price_target_sell'] ?? 0);
        $sl = str_contains($sigVal, 'BUY') ? ($sig['data']['stop_loss_buy'] ?? 0) : ($sig['data']['stop_loss_sell'] ?? 0);
    ?>
    <div class="prediction-signal <?php echo e($sigClass); ?>" style="border-radius:var(--radius-md)" data-tf="<?php echo e($sig['tf']); ?>" id="kpi-<?php echo e($sig['tf']); ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <div class="signal-label" style="font-weight:bold"><?php echo e($sig['label']); ?></div>
            <span class="badge badge-neutral text-small regime-badge"><?php echo e($sig['data']['market_regime'] ?? 'UNKNOWN'); ?></span>
        </div>
        <div class="signal-action" style="font-size:1.5rem"><?php echo e($sigVal); ?></div>
        <div class="signal-confidence" style="margin-top:4px">
            Conf: <?php echo e($sig['data']['confidence'] ?? 0); ?>% 
            <span style="opacity:0.7">| B: <?php echo e($sig['data']['buy_score'] ?? 0); ?> S: <?php echo e($sig['data']['sell_score'] ?? 0); ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.1);font-size:0.75rem">
            <div><span style="opacity:0.7">Target</span><br><strong class="tp-val text-success">$<?php echo e(number_format($tp, 2)); ?></strong></div>
            <div style="text-align:right"><span style="opacity:0.7">Stop</span><br><strong class="sl-val text-danger">$<?php echo e(number_format($sl, 2)); ?></strong></div>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<div class="grid-2" style="align-items:start">
    
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Indicator Breakdown (15m)
            </span>
        </div>
        <div class="panel-body no-padding">
            <div class="indicator-list" id="indicator-list-15m">
                <?php $__currentLoopData = $signal15m['indicators']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ind): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="indicator-row" style="padding:var(--space-2) 0;border-bottom:1px solid var(--border-color)">
                    <span class="indicator-name" style="flex:1"><?php echo e($ind['name']); ?></span>
                    <?php
                        $sc = in_array($ind['signal'], ['BUY','BULLISH']) ? 'text-success' :
                               (in_array($ind['signal'], ['SELL','BEARISH']) ? 'text-danger' : 'text-muted');
                        $bc = in_array($ind['signal'], ['BUY','BULLISH']) ? 'buy' :
                               (in_array($ind['signal'], ['SELL','BEARISH']) ? 'sell' : 'neutral');
                    ?>
                    <span class="indicator-value <?php echo e($sc); ?>" style="font-size:0.75rem;margin-right:var(--space-2)"><?php echo e($ind['value']); ?></span>
                    <span class="badge badge-<?php echo e($bc); ?>" style="font-size:0.65rem"><?php echo e($ind['signal']); ?></span>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    </div>

    
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="2"><path d="M21 3L3 21"/><path d="M21 21L3 3"/></svg>
                Support & Resistance Levels
            </span>
        </div>
        <div class="panel-body">
            <div style="display:flex;flex-direction:column;gap:var(--space-3)">
                <?php if(!empty($sr['resistance'])): ?>
                <div>
                    <div class="text-caption" style="margin-bottom:var(--space-2);text-transform:uppercase;letter-spacing:0.06em;font-weight:600;color:var(--danger)">Resistance</div>
                    <?php $__currentLoopData = $sr['resistance']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $level): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-1) var(--space-3);font-family:var(--font-mono);font-size:0.875rem">
                        <span class="text-danger">$<?php echo e(number_format($level, 2)); ?></span>
                        <?php $dist = $signal15m['price'] ?? 0 ? (($level - ($signal15m['price'] ?? 0)) / ($signal15m['price'] ?? 1)) * 100 : 0; ?>
                        <span class="text-muted"><?php echo e($dist >= 0 ? '+' : ''); ?><?php echo e(number_format($dist, 2)); ?>%</span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php endif; ?>

                <div style="padding:var(--space-2) var(--space-3);background:var(--bg-elevated);border-radius:var(--radius-sm);text-align:center">
                    <span class="text-caption">Current Price</span>
                    <div class="text-mono" style="font-size:1.25rem;font-weight:700">$<?php echo e(number_format($signal15m['price'] ?? $ticker['lastPrice'] ?? 0, 2)); ?></div>
                </div>

                <?php if(!empty($sr['support'])): ?>
                <div>
                    <div class="text-caption" style="margin-bottom:var(--space-2);text-transform:uppercase;letter-spacing:0.06em;font-weight:600;color:var(--success)">Support</div>
                    <?php $__currentLoopData = $sr['support']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $level): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-1) var(--space-3);font-family:var(--font-mono);font-size:0.875rem">
                        <span class="text-success">$<?php echo e(number_format($level, 2)); ?></span>
                        <?php $dist = $signal15m['price'] ?? 0 ? (($level - ($signal15m['price'] ?? 0)) / ($signal15m['price'] ?? 1)) * 100 : 0; ?>
                        <span class="text-muted"><?php echo e(number_format($dist, 2)); ?>%</span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php endif; ?>

                <?php if(empty($sr['support']) && empty($sr['resistance'])): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📊</div>
                    <div class="empty-state-title">No clear S/R levels found</div>
                    <div class="empty-state-desc">Try a longer timeframe for more data points</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


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
                <p id="summary-15m" style="font-size:0.875rem;color:var(--text-secondary)"><?php echo e($signal15m['summary']); ?></p>
            </div>
            <div>
                <div class="text-caption" style="margin-bottom:var(--space-2)">1 Hour</div>
                <p id="summary-1h" style="font-size:0.875rem;color:var(--text-secondary)"><?php echo e($signal1h['summary']); ?></p>
            </div>
            <div>
                <div class="text-caption" style="margin-bottom:var(--space-2)">4 Hour</div>
                <p id="summary-4h" style="font-size:0.875rem;color:var(--text-secondary)"><?php echo e($signal4h['summary']); ?></p>
            </div>
        </div>
    </div>
</div>


<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Price Chart (15m)</span>
    </div>
    <div class="chart-container" id="analysis-chart" style="height:350px"></div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
const SYMBOL = '<?php echo e($symbol); ?>';
const initialKlines = <?php echo json_encode($klines15m, 15, 512) ?>;
let chart, candleSeries;

document.addEventListener('DOMContentLoaded', function() {
    initAnalysisChart();
    initWatchlist();

    // Start Real-Time Server-Sent Events Stream
    initSSE();
    initBinanceWS();
});

let sseSource = null;
function initSSE() {
    sseSource = new EventSource('<?php echo e(url('/api/market/stream')); ?>?page=analysis&symbol=' + SYMBOL);
    
    sseSource.onopen = () => {
        document.getElementById('conn-dot')?.classList.remove('disconnected');
        if (document.getElementById('conn-text')) document.getElementById('conn-text').textContent = 'Connected (SSE)';
        if (document.getElementById('ws-status-text')) document.getElementById('ws-status-text').textContent = 'Real-time Server Stream Active';
    };
    
    sseSource.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            
            // 1. Update Chart (klines returned are 1h by default from the stream for prediction/analysis basis)
            // But the chart on this page is labeled 15m. So we should rely on the 15m klines from the root logic.
            // Actually, the API stream pushes 'klines' (1h) and 'predictions'. We should sync the chart to it or just rely on predictions.
            // Real-time ticking is now handled separately by Binance WebSocket (initBinanceWS)
            // so we don't process data.ticker here for chart ticking anymore.
            
            // 2. Update Predictions
            if (data.predictions) {
                if (data.predictions['15m']) updatePredictionDOM('15m', data.predictions['15m']);
                if (data.predictions['1h'])  updatePredictionDOM('1h',  data.predictions['1h']);
                if (data.predictions['4h'])  updatePredictionDOM('4h',  data.predictions['4h']);
            }
            
            // 3. Shared components
            if (data.top_pairs) {
                if (typeof window.renderTickerBar === 'function') {
                    window.renderTickerBar(data.top_pairs);
                }
            }
            if (data.watchlist) renderWatchlist(data.watchlist);
            
            if (window.updateLastUpdate) window.updateLastUpdate();
        } catch(e) { console.error('SSE Error:', e); }
    };
    
    sseSource.onerror = () => {
        console.warn('SSE connection lost, reconnecting...');
    };
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
                return `<div class="indicator-row" style="padding:var(--space-2) 0;border-bottom:1px solid var(--border-color)">
                            <span class="indicator-name" style="flex:1">${ind.name}</span>
                            <span class="indicator-value ${sc}" style="font-size:0.75rem;margin-right:var(--space-2)">${ind.value}</span>
                            <span class="badge badge-${bc}" style="font-size:0.65rem">${ind.signal}</span>
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
    fetch('<?php echo e(url('/api/market/ticker')); ?>')
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
        } catch(e) {}
    };
    
    binanceWS.onerror = () => console.warn('Binance WS error');
    binanceWS.onclose = () => setTimeout(initBinanceWS, 5000);
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Project\market_pro\resources\views/analysis/index.blade.php ENDPATH**/ ?>