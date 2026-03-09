@extends('layouts.app')

@section('title', 'Market Scanner')
@section('page-title', 'Market Scanner')

@section('content')
<div class="scanner-layout">

{{-- ═══ KPI SUMMARY STRIP (TOP) ═══ --}}
<div class="kpi-grid scanner-kpi-grid" id="scanner-kpi-grid">
    {{-- Total Pairs --}}
    <div class="kpi-card" id="kpi-total">
        <div class="kpi-header-row">
            <div class="kpi-label">Total Pairs</div>
            <div class="kpi-icon-badge neutral">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </div>
        </div>
        <div class="kpi-value text-mono" id="kpi-total-val">{{ count($pairs) }}</div>
        <div class="kpi-sub-row">
            <span id="kpi-total-sub">—</span>
            <div class="kpi-ratio-bar"><div class="kpi-ratio-fill" id="kpi-total-bar" style="width:50%;background:var(--accent)"></div></div>
        </div>
        <canvas class="kpi-sparkline-canvas" id="sparkline-total"></canvas>
    </div>

    {{-- Gainers --}}
    <div class="kpi-card" id="kpi-gainers">
        <div class="kpi-header-row">
            <div class="kpi-label">Gainers</div>
            <div class="kpi-icon-badge gain">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
        </div>
        <div class="kpi-value text-mono text-success" id="kpi-gainers-val">{{ collect($pairs)->filter(fn($p) => $p['change'] > 0)->count() }}</div>
        <div class="kpi-sub-row">
            <span id="kpi-gainers-sub" style="color:var(--success)">—</span>
            <div class="kpi-ratio-bar"><div class="kpi-ratio-fill" id="kpi-gainers-bar" style="width:0%;background:var(--success)"></div></div>
        </div>
        <canvas class="kpi-sparkline-canvas" id="sparkline-gainers"></canvas>
    </div>

    {{-- Losers --}}
    <div class="kpi-card" id="kpi-losers">
        <div class="kpi-header-row">
            <div class="kpi-label">Losers</div>
            <div class="kpi-icon-badge loss">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
            </div>
        </div>
        <div class="kpi-value text-mono text-danger" id="kpi-losers-val">{{ collect($pairs)->filter(fn($p) => $p['change'] < 0)->count() }}</div>
        <div class="kpi-sub-row">
            <span id="kpi-losers-sub" style="color:var(--danger)">—</span>
            <div class="kpi-ratio-bar"><div class="kpi-ratio-fill" id="kpi-losers-bar" style="width:0%;background:var(--danger)"></div></div>
        </div>
        <canvas class="kpi-sparkline-canvas" id="sparkline-losers"></canvas>
    </div>

    {{-- Avg Change --}}
    <div class="kpi-card" id="kpi-avg">
        @php $avgChange = count($pairs) > 0 ? collect($pairs)->avg('change') : 0; @endphp
        <div class="kpi-header-row">
            <div class="kpi-label">Avg Change</div>
            <div class="kpi-icon-badge {{ $avgChange >= 0 ? 'gain' : 'loss' }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="{{ $avgChange >= 0 ? 'var(--success)' : 'var(--danger)' }}" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/></svg>
            </div>
        </div>
        <div class="kpi-value text-mono {{ $avgChange >= 0 ? 'text-success' : 'text-danger' }}" id="kpi-avg-val">{{ number_format($avgChange, 2) }}%</div>
        <div class="kpi-sub-row">
            <span id="kpi-avg-sub">—</span>
            <div class="kpi-ratio-bar"><div class="kpi-ratio-fill" id="kpi-avg-bar" style="width:50%;background:var(--accent)"></div></div>
        </div>
        <canvas class="kpi-sparkline-canvas" id="sparkline-avg"></canvas>
    </div>
</div>

{{-- ═══ SCANNER TABLE (fills remaining space) ═══ --}}
<div class="panel scanner-table-panel">
    <div class="panel-header">
        <span class="panel-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Multi-Pair Scanner — Real-Time USDT Pairs
        </span>
        <div class="panel-actions scanner-actions-wrapper">
            <div class="scanner-search-field">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="scanner-filter" placeholder="Filter pairs..." spellcheck="false" autocomplete="off">
            </div>

            <div class="scanner-select-field">
                <select id="scanner-sort">
                    <option value="volume_desc">Sort: Volume ↓</option>
                    <option value="gainers">Change ↓</option>
                    <option value="losers">Change ↑</option>
                </select>
                <svg class="select-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"/></svg>
            </div>

            <button aria-label="Refresh Scanner" class="btn-refresh-scanner" id="scanner-refresh">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="refresh-icon"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                <span>REFRESH</span>
            </button>
        </div>
    </div>
    <div class="panel-body no-padding scanner-table-body">

        <table class="data-table scanner-stripe" id="scanner-table">
            <thead>
                <tr>
                    <th style="width: 3%;">#</th>
                    <th style="width: 10%;">Pair</th>
                    <th class="align-right" style="width: 11%;">Price</th>
                    <th class="align-right" style="width: 10%;">24h Change</th>
                    <th class="align-right" style="width: 12%;">24h High</th>
                    <th class="align-right" style="width: 12%;">24h Low</th>
                    <th class="align-right" style="width: 12%;">Volume (USDT)</th>
                    <th class="align-right" style="width: 12%;">Trades</th>
                    <th class="align-right" style="width: 18%;">Signal</th>
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
                    <td class="align-right scanner-signal"><span class="badge badge-neutral signal-loading" style="display:inline-block;width:70px;height:18px;margin:0;border:none"></span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
</div>{{-- /.scanner-layout --}}

<style>
/* ─── SCANNER PAGE LAYOUT ─── */
.scanner-layout {
    display: flex;
    flex-direction: row; /* Force side-by-side */
    gap: var(--space-4);
    padding-top: 8px; 
    height: 100%;
    min-height: 100%;
    flex: 1;
}

/* Sidebar KPI Grid */
.scanner-kpi-grid {
    display: flex;
    flex-direction: column;
    flex: 0 0 160px; /* Locked sidebar width */
    gap: var(--space-3);
    overflow: visible;
}

/* Table panel stretches to fill remaining height */
.scanner-table-panel {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: hidden;
    /* Removed border radius resets to keep corners rounded */
    margin-bottom: 0;
}

.scanner-table-panel .panel-header {
    flex-shrink: 0;
}

/* Scrollable table body fills remaining panel space */
.scanner-table-body {
    flex: 1;
    min-height: 0;
    overflow-x: auto;
    overflow-y: auto;
}

/* Alternating stripes + hover with accent glow */
.scanner-stripe tbody tr:nth-child(even) {
    background: rgba(255,255,255,0.02);
}
.scanner-stripe tbody tr {
    cursor: pointer;
    transition: background var(--transition-fast), box-shadow var(--transition-fast);
}
.scanner-stripe tbody tr:hover {
    background: var(--bg-elevated);
    box-shadow: inset 3px 0 0 var(--accent), inset 0 0 20px rgba(0, 240, 255, 0.04);
}
.scanner-stripe tbody tr:hover td:first-child {
    color: var(--accent) !important;
}

/* Compact Table Layout for Scanner to show more data */
.scanner-stripe {
    table-layout: fixed;
    width: 100%;
}
.scanner-stripe th,
.scanner-stripe td {
    padding-top: 2px !important;
    padding-bottom: 2px !important;
    padding-left: 6px !important;
    padding-right: 6px !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.scanner-stripe td {
    font-size: 0.68rem !important; /* Smaller text to fit more rows */
}
.scanner-signal .badge {
    white-space: nowrap;
    padding: 2px 6px;
    font-size: 0.62rem;
}

/* Override global kpi-card for scanner — tighter layout */
.scanner-kpi-grid .kpi-card {
    position: relative;
    min-height: 100px;
    flex-direction: column;
    justify-content: flex-start;
    gap: 4px;
    padding: 10px 12px 14px;
    overflow: hidden; /* Back to hidden, fixed hover clipping via layout padding */
    border-radius: var(--radius-lg);
    background: var(--bg-surface);
}
.scanner-kpi-grid .kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

/* KPI sparkline canvas */
.kpi-sparkline-canvas {
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 48px;
    opacity: 0.45;
    pointer-events: none;
    z-index: 0;
}
.kpi-header-row, .kpi-value, .kpi-sub-row {
    position: relative;
    z-index: 1;
}

/* --- Signal Loading Shimmer --- */
.signal-loading {
    position: relative;
    overflow: hidden;
    background: rgba(255,255,255,0.05) !important;
}
.signal-loading::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
    animation: signal-shimmer 1.5s infinite;
}
@keyframes signal-shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* KPI header row with icon */
.kpi-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: -4px;
}

/* Secondary stat row */
.kpi-sub-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.68rem;
    color: var(--text-muted);
    font-family: var(--font-mono);
}

/* Ratio progress bar */
.kpi-ratio-bar {
    flex: 1;
    height: 4px;
    border-radius: 2px;
    background: rgba(255,255,255,0.06);
    overflow: hidden;
}

.kpi-ratio-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.4s ease;
}

.kpi-icon-badge {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,240,255,0.08);
    border: 1px solid rgba(0,240,255,0.12);
    flex-shrink: 0;
}

.kpi-icon-badge.gain  { background: rgba(0,255,136,0.08);  border-color: rgba(0,255,136,0.15); }
.kpi-icon-badge.loss  { background: rgba(255,51,102,0.08); border-color: rgba(255,51,102,0.15); }
.kpi-icon-badge.neutral { background: rgba(0,240,255,0.08); border-color: rgba(0,240,255,0.12); }
/* --- SCANNER ACTIONS PREMIUM STYLING --- */
.scanner-actions-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Base Styles for Search & Select containers */
.scanner-search-field, .scanner-select-field {
    height: 32px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 6px;
    display: flex;
    align-items: center;
    padding: 0 10px;
    transition: all var(--transition-fast);
    position: relative;
    backdrop-filter: blur(4px);
}

.scanner-search-field:focus-within, .scanner-select-field:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(0, 240, 255, 0.25);
    box-shadow: 0 0 15px rgba(0, 240, 255, 0.06);
}

.scanner-search-field svg {
    color: var(--text-muted);
    opacity: 0.5;
    transition: color 0.2s, opacity 0.2s;
}

.scanner-search-field:focus-within svg {
    color: var(--accent);
    opacity: 1;
}

.scanner-search-field input {
    background: transparent;
    border: none;
    outline: none;
    color: var(--text-primary);
    font-size: 0.72rem;
    padding-left: 8px;
    width: 140px;
}

.scanner-search-field input::placeholder {
    color: var(--text-muted);
    opacity: 0.4;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Custom Select Field */
.scanner-select-field {
    padding-right: 28px; /* Room for custom chevron */
}

.scanner-select-field select {
    background: transparent;
    border: none;
    outline: none;
    color: var(--text-primary);
    font-size: 0.72rem;
    font-weight: 500;
    cursor: pointer;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    width: 100%;
}

.scanner-select-field .select-chevron {
    position: absolute;
    right: 10px;
    pointer-events: none;
    color: var(--text-muted);
    opacity: 0.4;
    transition: transform 0.2s;
}

.scanner-select-field:hover .select-chevron {
    opacity: 0.7;
    color: var(--accent);
}

/* Refresh Button Premium */
.btn-refresh-scanner {
    height: 32px;
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-secondary);
    font-family: var(--font-display);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    padding: 0 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.btn-refresh-scanner:hover {
    background: var(--bg-elevated);
    border-color: var(--border-accent);
    color: var(--accent);
    box-shadow: var(--shadow-accent-sm);
}

.btn-refresh-scanner .refresh-icon {
    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.btn-refresh-scanner:hover .refresh-icon {
    transform: rotate(30deg);
}

.btn-refresh-scanner.spinning .refresh-icon {
    animation: spin-refresh 0.8s linear infinite;
}

@keyframes spin-refresh {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

@endsection

@section('scripts')
<script>
let currentPairsData = [];

document.addEventListener('DOMContentLoaded', function() {
    refreshScannerTable(); // Initial fast load
    loadSignalsForTopPairs();

    // Transitioned to real-time WebSockets
    // initSSE(); // Deprecated
    initWebSockets();

    // Refresh button now updates data via API manually if user requests it
    document.getElementById('scanner-refresh')?.addEventListener('click', function() {
        const btn = this;
        btn.classList.add('spinning');
        refreshScannerTable();
        loadSignalsForTopPairs();
        setTimeout(() => btn.classList.remove('spinning'), 1000);
    });

    document.getElementById('scanner-sort')?.addEventListener('change', function() {
        renderScannerTable(); // Re-render with new sort
    });

    document.getElementById('scanner-filter')?.addEventListener('input', function() {
        renderScannerTable();
    });
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

    console.info('[Scanner] Subscribing to market.all...');

    // In Scanner, we mainly care about 'market.all' which contains the global pairs list
    window.Echo.channel('market.all')
        .listen('.updated', (e) => {
            const data = e.data;
            
            if (data.pairs) {
                currentPairsData = data.pairs;
                renderScannerTable();
            }
            if (data.top_pairs && typeof window.renderTickerBar === 'function') {
                window.renderTickerBar(data.top_pairs);
            }
            if (data.watchlist && typeof window.renderWatchlistFromSSE === 'function') {
                window.renderWatchlistFromSSE(data.watchlist);
            }
            
            if (window.updateLastUpdate) window.updateLastUpdate();
        });
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

        return `<tr data-symbol="${p.symbol}" data-pair="${p.pair}" onclick="window.location='/trading?symbol=${p.symbol}'">
            <td style="color:var(--text-muted)">${i + 1}</td>
            <td style="font-weight:600">${p.pair}</td>
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
    const losers  = filtered.length - gainers;
    const avgChange = filtered.length ? (filtered.reduce((sum, p) => sum + p.change, 0) / filtered.length) : 0;
    const total = filtered.length;

    const pairsEl   = document.getElementById('kpi-total-val');
    const gainersEl = document.getElementById('kpi-gainers-val');
    const losersEl  = document.getElementById('kpi-losers-val');
    const avgEl     = document.getElementById('kpi-avg-val');

    if (pairsEl)  pairsEl.textContent  = total;
    if (gainersEl) gainersEl.textContent = gainers;
    if (losersEl)  losersEl.textContent  = losers;
    if (avgEl) {
        avgEl.textContent = `${avgChange >= 0 ? '+' : ''}${avgChange.toFixed(2)}%`;
        avgEl.className = `kpi-value text-mono ${avgChange >= 0 ? 'text-success' : 'text-danger'}`;
    }

    // Update ratio bars and sub-labels
    const gainPct  = total ? Math.round(gainers / total * 100) : 0;
    const losePct  = total ? Math.round(losers  / total * 100) : 0;
    const maxChange = filtered.length ? Math.max(...filtered.map(p => Math.abs(p.change))) : 10;
    const avgBarPct = Math.min(100, Math.abs(avgChange) / Math.max(maxChange, 0.01) * 100);

    // Total card: show active pairs ratio vs 100 max
    const setBar = (id, pct) => { const el = document.getElementById(id); if (el) el.style.width = pct + '%'; };
    const setSub = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };

    setSub('kpi-total-sub',   `${total} active`);
    setBar('kpi-total-bar',   Math.min(100, total / 200 * 100));  // 200 = rough max

    setSub('kpi-gainers-sub', `${gainPct}% of market`);
    setBar('kpi-gainers-bar', gainPct);

    setSub('kpi-losers-sub',  `${losePct}% of market`);
    setBar('kpi-losers-bar',  losePct);

    setSub('kpi-avg-sub',     `Max: ${maxChange.toFixed(2)}%`);
    const avgBarEl = document.getElementById('kpi-avg-bar');
    if (avgBarEl) {
        avgBarEl.style.width = avgBarPct + '%';
        avgBarEl.style.background = avgChange >= 0 ? 'var(--success)' : 'var(--danger)';
    }

    // Draw sparklines after a short delay to allow layout to settle
    requestAnimationFrame(() => drawSparklines(filtered));
}

/* ─── SPARKLINE DRAWING ─── */
function drawSparkline(canvasEl, data, color, fillColor) {
    if (!canvasEl || !data.length) return;
    const dpr = window.devicePixelRatio || 1;
    const w = canvasEl.offsetWidth  || canvasEl.parentElement.clientWidth || 180;
    const h = canvasEl.offsetHeight || 52;
    canvasEl.width  = w * dpr;
    canvasEl.height = h * dpr;
    canvasEl.style.width  = w + 'px';
    canvasEl.style.height = h + 'px';
    const ctx = canvasEl.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, w, h);

    const padX = 2;
    const padY = 8; // More vertical padding to avoid bar collision
    const min = Math.min(...data);
    const max = Math.max(...data);
    const range = max - min || 1;
    const xStep = (w - padX * 2) / Math.max(data.length - 1, 1);

    // --- Draw Baseline ---
    const baselineVal = (min < 0 && max > 0) ? 0 : min;
    const baselineY = h - padY - ((baselineVal - min) / range) * (h - padY * 2);
    
    ctx.setLineDash([2, 2]);
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.15)';
    ctx.lineWidth = 0.8;
    ctx.beginPath();
    ctx.moveTo(padX, baselineY);
    ctx.lineTo(w - padX, baselineY);
    ctx.stroke();
    ctx.setLineDash([]);

    // --- Draw Labels (Context) ---
    ctx.font = '700 7px var(--font-mono)';
    ctx.fillStyle = 'rgba(255, 255, 255, 0.25)';
    ctx.fillText('24H', w - 18, h - 3);
    
    // REMOVED min/max hints as they cluttered sidebar view

    // --- Draw Data Line ---
    ctx.beginPath();
    data.forEach((v, i) => {
        const x = padX + i * xStep;
        const y = h - padY - ((v - min) / range) * (h - padY * 2);
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });

    // Stroke
    ctx.strokeStyle = color;
    ctx.lineWidth = 1.2;
    ctx.lineJoin = 'round';
    ctx.stroke();

    // Fill under line
    const lastX = padX + (data.length - 1) * xStep;
    ctx.lineTo(lastX, h);
    ctx.lineTo(padX, h);
    ctx.closePath();
    const grad = ctx.createLinearGradient(0, baselineY, 0, h);
    grad.addColorStop(0, fillColor);
    grad.addColorStop(1, 'transparent');
    ctx.fillStyle = grad;
    ctx.fill();
}

function drawSparklines(filtered) {
    if (!filtered || !filtered.length) return;

    // Use all filtered coins for a more accurate market "shape"
    const changes = filtered.map(p => p.change);
    const volumes = filtered.map(p => p.quoteVolume);

    // Total Pairs: Use absolute volumes to show activity distribution
    drawSparkline(
        document.getElementById('sparkline-total'),
        volumes.map(Math.log10), // Log scale for volume to avoid spikes hiding detail
        '#00f0ff',
        'rgba(0,240,255,0.06)'
    );

    // Gainers: only positive values
    const gainerChanges = [...changes].filter(v => v > 0).sort((a,b) => a - b);
    drawSparkline(
        document.getElementById('sparkline-gainers'),
        gainerChanges.length ? gainerChanges : [0, 0.1],
        '#00ff88',
        'rgba(0,255,136,0.08)'
    );

    // Losers: only negative values (abs)
    const loserChanges = [...changes].filter(v => v < 0).map(Math.abs).sort((a,b) => a - b);
    drawSparkline(
        document.getElementById('sparkline-losers'),
        loserChanges.length ? loserChanges : [0, 0.1],
        '#ff3366',
        'rgba(255,51,102,0.08)'
    );

    // Avg Change: cumulative rolling average
    let runSum = 0;
    const rollingAvg = changes.map((v, i) => { runSum += v; return runSum / (i + 1); });
    const avgPositive = (runSum / changes.length) >= 0;
    drawSparkline(
        document.getElementById('sparkline-avg'),
        rollingAvg,
        avgPositive ? '#00ff88' : '#ff3366',
        avgPositive ? 'rgba(0,255,136,0.07)' : 'rgba(255,51,102,0.07)'
    );
}

function loadSignalsForTopPairs() {
    // Load signals for up to 50 pairs shown in the scanner table
    const allSymbols = currentPairsData.slice(0, 50).map(p => p.symbol);
    if (!allSymbols.length) {
        setTimeout(loadSignalsForTopPairs, 2000);
        return;
    }

    // Split into two batches of 25 to avoid URL length issues and timeouts
    const batch1 = allSymbols.slice(0, 25);
    const batch2 = allSymbols.slice(25, 50);

    const fetchBatch = (symbols) => {
        if (!symbols.length) return;
        fetch(`{{ url('/api/market/batch-predictions') }}?symbols=${symbols.join(',')}&interval=15m`)
            .then(r => r.json())
            .then(results => {
                Object.entries(results).forEach(([symbol, data]) => {
                    const row = document.querySelector(`tr[data-symbol="${symbol}"] .scanner-signal`);
                    if (row) {
                        const cls = data.signal.includes('BUY') ? 'badge-buy' : data.signal.includes('SELL') ? 'badge-sell' : 'badge-neutral';
                        row.innerHTML = `<span class="badge ${cls}">${data.signal} ${data.confidence}%</span>`;
                        const span = row.querySelector('span');
                        if (span) span.classList.remove('signal-loading');
                    }
                });
            })
            .catch(err => console.error('Batch Prediction Error:', err));
    };

    fetchBatch(batch1);
    setTimeout(() => fetchBatch(batch2), 1500); // Stagger the second batch
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
