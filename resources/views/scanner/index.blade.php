@extends('layouts.app')

@section('title', 'Market Scanner')
@section('page-title', 'Market Scanner')

@section('content')
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Multi-Pair Scanner — Real-Time USDT Pairs
        </span>
        <div class="panel-actions" style="display:flex;gap:var(--space-3);align-items:center;flex-wrap:wrap">
            <input type="text" id="scanner-filter" placeholder="Filter pairs..." style="min-width:100px;flex:1">
            <select id="scanner-sort" style="min-width:120px;flex:1">
                <option value="volume">Sort: Volume ↓</option>
                <option value="change_desc">Change ↓</option>
                <option value="change_asc">Change ↑</option>
                <option value="price">Price ↓</option>
                <option value="trades">Trades ↓</option>
            </select>
            <button aria-label="Refresh Scanner" class="btn btn-ghost btn-sm" id="scanner-refresh" style="flex:1">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                Refresh
            </button>
        </div>
    </div>
    <div class="panel-body no-padding" style="overflow-x:auto;max-height:calc(100vh - 220px);overflow-y:auto">
        <table class="data-table scanner-stripe" id="scanner-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Pair</th>
                    <th class="align-right">Price</th>
                    <th class="align-right">24h Change</th>
                    <th class="align-right">24h High</th>
                    <th class="align-right">24h Low</th>
                    <th class="align-right">Volume (USDT)</th>
                    <th class="align-right">Trades</th>
                    <th class="align-right">Signal</th>
                </tr>
            </thead>
            <tbody id="scanner-body">
                @foreach($pairs as $i => $p)
                <tr data-symbol="{{ $p['symbol'] }}" data-pair="{{ $p['pair'] }}" onclick="window.location='/trading?symbol={{ $p['symbol'] }}'" class="hover-glow">
                    <td style="color:var(--text-muted)">{{ $i + 1 }}</td>
                    <td style="font-weight:600">{{ $p['pair'] }}</td>
                    <td class="align-right scanner-price" style="font-family:var(--font-mono)">${{ number_format($p['price'], $p['price'] < 1 ? 6 : 2) }}</td>
                    <td class="align-right scanner-change {{ $p['change'] >= 0 ? 'positive' : 'negative' }}" style="font-family:var(--font-mono)">
                        {{ $p['change'] >= 0 ? '+' : '' }}{{ number_format($p['change'], 2) }}%
                    </td>
                    <td class="align-right" style="font-family:var(--font-mono);color:var(--text-muted)">${{ number_format($p['high'], $p['high'] < 1 ? 6 : 2) }}</td>
                    <td class="align-right" style="font-family:var(--font-mono);color:var(--text-muted)">${{ number_format($p['low'], $p['low'] < 1 ? 6 : 2) }}</td>
                    <td class="align-right" style="font-family:var(--font-mono)">{{ $p['quoteVolume'] > 1e9 ? number_format($p['quoteVolume'] / 1e9, 2) . 'B' : number_format($p['quoteVolume'] / 1e6, 1) . 'M' }}</td>
                    <td class="align-right" style="font-family:var(--font-mono)">{{ number_format($p['trades']) }}</td>
                    <td class="align-right scanner-signal"><span class="badge badge-neutral skeleton-text" style="display:inline-block;width:60px;height:18px;margin:0"></span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<style>
/* Add alternating stripes, preserving hover scale from app.css */
.scanner-stripe tbody tr:nth-child(even) {
    background: rgba(255,255,255,0.02);
}
.scanner-stripe tbody tr:hover {
    background: var(--bg-elevated);
}
</style>

{{-- Quick Stats --}}
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total Pairs</div>
        <div class="kpi-value text-mono">{{ count($pairs) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Gainers</div>
        <div class="kpi-value text-mono text-success">{{ collect($pairs)->filter(fn($p) => $p['change'] > 0)->count() }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Losers</div>
        <div class="kpi-value text-mono text-danger">{{ collect($pairs)->filter(fn($p) => $p['change'] < 0)->count() }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Avg Change</div>
        @php $avgChange = count($pairs) > 0 ? collect($pairs)->avg('change') : 0; @endphp
        <div class="kpi-value text-mono {{ $avgChange >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($avgChange, 2) }}%</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentPairsData = [];

document.addEventListener('DOMContentLoaded', function() {
    refreshScannerTable(); // Initial fast load
    loadSignalsForTopPairs();

    // Replaced intervals with Server-Sent Events (SSE)
    initSSE();

    // Refresh button now updates data via API manually if user requests it
    document.getElementById('scanner-refresh')?.addEventListener('click', () => {
        refreshScannerTable();
        loadSignalsForTopPairs();
    });

    document.getElementById('scanner-sort')?.addEventListener('change', function() {
        renderScannerTable(); // Re-render with new sort
    });

    document.getElementById('scanner-filter')?.addEventListener('input', function() {
        renderScannerTable();
    });
});

let sseSource = null;
function initSSE() {
    sseSource = new EventSource('{{ url('/api/market/stream') }}?page=scanner');
    
    sseSource.onopen = () => {
        document.getElementById('conn-dot')?.classList.remove('disconnected');
        if (document.getElementById('conn-text')) document.getElementById('conn-text').textContent = 'Connected (SSE)';
        if (document.getElementById('ws-status-text')) document.getElementById('ws-status-text').textContent = 'Real-time Server Stream Active';
    };
    
    sseSource.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            
            if (data.pairs) {
                currentPairsData = data.pairs;
                renderScannerTable();
            }
            if (data.top_pairs) {
                if (typeof window.renderTickerBar === 'function') {
                    window.renderTickerBar(data.top_pairs);
                }
            }
            if (data.watchlist) {
                if (typeof window.renderWatchlistFromSSE === 'function') {
                    window.renderWatchlistFromSSE(data.watchlist);
                }
            }
            
            if (window.updateLastUpdate) window.updateLastUpdate();
        } catch(e) { console.error('SSE Error:', e); }
    };
    
    sseSource.onerror = () => {
        console.warn('SSE connection lost, reconnecting...');
    };
}

function refreshScannerTable() {
    const btn = document.getElementById('scanner-refresh');
    if (btn) btn.style.opacity = '0.5';

    fetch('{{ url('/api/market/top-pairs') }}')
        .then(r => r.json())
        .then(pairs => {
            if (btn) btn.style.opacity = '1';
            currentPairsData = pairs;
            renderScannerTable();
        })
        .catch(() => { if (btn) btn.style.opacity = '1'; });
}

function renderScannerTable() {
    const tbody = document.getElementById('scanner-body');
    if (!tbody || !currentPairsData.length) return;

    const searchTerm = (document.getElementById('scanner-filter')?.value || '').toLowerCase();
    const sortVal = document.getElementById('scanner-sort')?.value || 'volume_desc';

    // Filter
    let filtered = currentPairsData.filter(p => p.pair.toLowerCase().includes(searchTerm));

    // Sort
    if (sortVal === 'volume_desc') filtered.sort((a,b) => b.quoteVolume - a.quoteVolume);
    if (sortVal === 'gainers')     filtered.sort((a,b) => b.change - a.change);
    if (sortVal === 'losers')      filtered.sort((a,b) => a.change - b.change);

    // Render
    tbody.innerHTML = filtered.map((p, i) => {
        const cls = p.change >= 0 ? 'positive' : 'negative';
        const sign = p.change >= 0 ? '+' : '';
        const vol = p.quoteVolume >= 1e9 ? (p.quoteVolume/1e9).toFixed(2)+'B' : (p.quoteVolume/1e6).toFixed(1)+'M';

        // Preserve existing signal if it was already loaded
        const existingRow = document.querySelector(`tr[data-symbol="${p.symbol}"] .scanner-signal`);
        const signalHtml = existingRow ? existingRow.innerHTML : '<span class="badge badge-neutral skeleton-text" style="display:inline-block;width:60px;height:18px;margin:0"></span>';

        return `<tr data-symbol="${p.symbol}" data-pair="${p.pair}">
            <td style="color:var(--text-muted)">${i + 1}</td>
            <td style="font-weight:600; cursor:pointer" onclick="window.location='/trading?symbol=${p.symbol}'">
                ${p.pair} <span style="font-size:0.75rem;color:var(--text-muted);font-weight:normal">Vol: ${vol}</span>
            </td>
            <td class="align-right" style="font-family:var(--font-mono)">$${formatPrice(p.price)}</td>
            <td class="align-right ${cls}" style="font-family:var(--font-mono)">${sign}${p.change.toFixed(2)}%</td>
            <td class="align-right" style="color:var(--text-muted);font-family:var(--font-mono)">$${formatPrice(p.high)}</td>
            <td class="align-right" style="color:var(--text-muted);font-family:var(--font-mono)">$${formatPrice(p.low)}</td>
            <td class="align-right" style="font-family:var(--font-mono)">${vol}</td>
            <td class="align-right" style="font-family:var(--font-mono)">${(p.trades||0).toLocaleString()}</td>
            <td class="align-right scanner-signal">${signalHtml}</td>
        </tr>`;
    }).join('');

    // Update summary stats
    const gainers = filtered.filter(p => p.change >= 0).length;
    const losers = filtered.length - gainers;
    const avgChange = filtered.length ? (filtered.reduce((sum, p) => sum + p.change, 0) / filtered.length) : 0;

    const pairsEl = document.querySelector('.kpi-card:nth-child(1) .kpi-value');
    const gainersEl = document.querySelector('.kpi-card:nth-child(2) .kpi-value');
    const losersEl = document.querySelector('.kpi-card:nth-child(3) .kpi-value');
    const avgEl = document.querySelector('.kpi-card:nth-child(4) .kpi-value');

    if (pairsEl) pairsEl.textContent = filtered.length;
    if (gainersEl) gainersEl.textContent = gainers;
    if (losersEl) losersEl.textContent = losers;
    if (avgEl) {
        avgEl.textContent = `${avgChange >= 0 ? '+' : ''}${avgChange.toFixed(2)}%`;
        avgEl.className = `kpi-value text-mono ${avgChange >= 0 ? 'text-success' : 'text-danger'}`;
    }
}

function loadSignalsForTopPairs() {
    const symbols = currentPairsData.slice(0, 20).map(p => p.symbol);
    if (!symbols.length) {
        // Retry if pairs not loaded yet
        setTimeout(loadSignalsForTopPairs, 2000);
        return;
    }

    symbols.forEach(symbol => {
        fetch(`{{ url('/api/market/prediction') }}?symbol=${symbol}&interval=15m`)
            .then(r => r.json())
            .then(data => {
                const row = document.querySelector(`tr[data-symbol="${symbol}"] .scanner-signal`);
                if (row) {
                    const cls = data.signal === 'BUY' ? 'badge-buy' : data.signal === 'SELL' ? 'badge-sell' : 'badge-neutral';
                    row.innerHTML = `<span class="badge ${cls}">${data.signal} ${data.confidence}%</span>`;
                }
            })
            .catch(() => {});
    });
}




// Watchlist is now handled globally by window.renderWatchlistFromSSE

function formatPrice(price) {
    if (price >= 1000) return price.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (price >= 1) return price.toFixed(2);
    if (price >= 0.01) return price.toFixed(4);
    return price.toFixed(6);
}
</script>
@endsection
