@extends('layouts.app')

@section('title', 'Platform Settings')
@section('page-title', 'Platform Settings')

@section('content')
<div class="grid-2" style="align-items:start">
    {{-- API Configuration --}}
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Exchange API Configuration
            </span>
        </div>
        <div class="panel-body">
            <div style="margin-bottom:var(--space-4)">
                <label style="display:block;margin-bottom:var(--space-2);font-weight:600;font-size:0.8125rem">Active Data Source</label>
                <select style="width:100%">
                    <option value="binance_public">Binance (Public API & WebSocket)</option>
                    <option value="binance_auth">Binance (Authenticated)</option>
                    <option value="coingecko">CoinGecko (Fallback)</option>
                </select>
                <div class="text-small" style="margin-top:var(--space-1)">Public API requires no authentication but has stricter rate limits.</div>
            </div>

            <div style="margin-bottom:var(--space-4)">
                <label style="display:block;margin-bottom:var(--space-2);font-weight:600;font-size:0.8125rem">API Key</label>
                <input type="text" placeholder="Ented API Key (optional)" style="width:100%">
            </div>

            <div style="margin-bottom:var(--space-4)">
                <label style="display:block;margin-bottom:var(--space-2);font-weight:600;font-size:0.8125rem">API Secret</label>
                <input type="text" placeholder="Enter API Secret (optional)" style="width:100%">
            </div>

            <button class="btn btn-primary" style="width:100%" onclick="alert('Settings saved locally.')">
                Save API Configuration
            </button>
        </div>
    </div>

    {{-- Algorithm Parameters --}}
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Prediction Algorithm Parameters
            </span>
        </div>
        <div class="panel-body">
            <div style="margin-bottom:var(--space-4)">
                <label style="display:block;margin-bottom:var(--space-2);font-weight:600;font-size:0.8125rem">RSI Oversold Threshold</label>
                <div style="display:flex;align-items:center;gap:var(--space-3)">
                    <input type="range" min="10" max="40" value="30" style="flex:1" oninput="document.getElementById('rsi-over').innerText=this.value">
                    <span id="rsi-over" class="text-mono" style="width:30px;text-align:right">30</span>
                </div>
            </div>

            <div style="margin-bottom:var(--space-4)">
                <label style="display:block;margin-bottom:var(--space-2);font-weight:600;font-size:0.8125rem">RSI Overbought Threshold</label>
                <div style="display:flex;align-items:center;gap:var(--space-3)">
                    <input type="range" min="60" max="90" value="70" style="flex:1" oninput="document.getElementById('rsi-bought').innerText=this.value">
                    <span id="rsi-bought" class="text-mono" style="width:30px;text-align:right">70</span>
                </div>
            </div>

            <div style="margin-bottom:var(--space-4)">
                <label style="display:block;margin-bottom:var(--space-2);font-weight:600;font-size:0.8125rem">MACD Fast Period</label>
                <input type="number" value="12" style="width:100%">
            </div>

            <div style="margin-bottom:var(--space-4)">
                <label style="display:block;margin-bottom:var(--space-2);font-weight:600;font-size:0.8125rem">MACD Slow Period</label>
                <input type="number" value="26" style="width:100%">
            </div>

            <button class="btn btn-primary" style="width:100%" onclick="alert('Algorithm settings saved.')">
                Update Parameters
            </button>
        </div>
    </div>

    {{-- Appearance & UI --}}
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                Appearance
            </span>
        </div>
        <div class="panel-body">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-2) 0;border-bottom:1px solid var(--border)">
                <div>
                    <div style="font-weight:600;font-size:0.8125rem">Theme Style</div>
                    <div class="text-small">Choose visual appearance</div>
                </div>
                <div style="display:flex;gap:var(--space-2)">
                    <button class="btn btn-primary btn-sm">Dark (Pro)</button>
                    <button class="btn btn-ghost btn-sm" disabled title="Light theme not supported yet">Light</button>
                </div>
            </div>
            
            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-3) 0">
                <div>
                    <div style="font-weight:600;font-size:0.8125rem">Animations</div>
                    <div class="text-small">Toggle Flash/Pulse effects</div>
                </div>
                <label style="display:flex;align-items:center;cursor:pointer">
                    <input type="checkbox" checked style="margin-right:var(--space-2)"> Enabled
                </label>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    initWatchlist();

    // Start Real-Time Updates
    // initSSE(); // Deprecated
    initWebSockets();
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

    console.info('[Settings] Subscribing to market.all...');

    // Settings page only needs shared components like Watchlist
    window.Echo.channel('market.all')
        .listen('.updated', (e) => {
            const data = e.data;
            if (data.top_pairs && typeof window.renderTickerBar === 'function') {
                window.renderTickerBar(data.top_pairs);
            }
            if (data.watchlist) renderWatchlist(data.watchlist);
            if (window.updateLastUpdate) window.updateLastUpdate();
        });
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
</script>
@endsection
