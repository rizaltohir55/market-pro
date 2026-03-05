@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard Overview')

@section('content')
<div class="kpi-grid fade-in-up" id="kpi-grid" style="--delay: 0.1s;">
    @php
        $kpis = [
            ['label' => 'IHSG', 'icon' => '🇮🇩', 'type' => 'stock'],
            ['label' => 'S&P 500 ETF', 'icon' => '🇺🇸', 'type' => 'stock'],
            ['label' => 'NASDAQ', 'icon' => '📈', 'type' => 'stock'],
            ['label' => 'BTC/USDT', 'icon' => '₿', 'type' => 'crypto'],
            ['label' => 'GOLD (XAU)', 'icon' => '🪙', 'type' => 'crypto'],
        ];
    @endphp

    @foreach($kpis as $i => $kpi)
    <div class="kpi-card js-tilt acrylic" id="kpi-card-{{ $i }}">
        <div class="kpi-header">
            <span class="kpi-label">{{ $kpi['label'] }}</span>
            <span class="kpi-icon" style="font-size:1.2rem; filter:drop-shadow(0 0 8px var(--accent-glow))">{{ $kpi['icon'] }}</span>
        </div>
        <div class="kpi-value">
            <div class="skeleton-value" style="height: 2rem; width: 100%;"></div>
        </div>
    </div>
    @endforeach
</div>

{{-- Market Heatmap --}}
<div class="panel acrylic fade-in-up" style="--delay: 0.15s; margin-top: var(--space-4)">
    <div class="panel-header">
        <span class="panel-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent2)" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            Market Heatmap (Top Stocks)
        </span>
    </div>
    <div class="panel-body no-padding">
        <div class="heatmap-grid" id="heatmap-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 4px; padding: 10px; background: rgba(0,0,0,0.4); border-radius: var(--radius-md);">
            @for($i=0; $i<15; $i++)
            <div class="heatmap-cell skeleton" style="min-height: 90px; border-radius: 4px; background: rgba(255,255,255,0.05);"></div>
            @endfor
        </div>
    </div>
</div>

{{-- Main Grid: Top Movers + Active --}}
<div class="grid-3 fade-in-up" style="--delay: 0.2s; align-items:start; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-4); margin-top: var(--space-4);">
    {{-- Top Gainers (Stocks) --}}
    <div class="panel acrylic">
        <div class="panel-header" style="border-bottom:none; padding-bottom:0">
            <span class="panel-title" style="text-shadow: 0 0 10px rgba(0, 255, 136, 0.3)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2" style="filter: drop-shadow(0 0 5px var(--success))"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                Top Gainers (Stocks)
            </span>
        </div>
        <div class="panel-body">
            <div id="gainers-tbody" style="display:flex; flex-direction: column; gap: var(--space-2)">
                @for($i=0; $i<5; $i++)
                <div class="skeleton" style="height: 45px; border-radius: 6px; background: rgba(255,255,255,0.05);"></div>
                @endfor
            </div>
        </div>
    </div>

    {{-- Top Losers (Stocks) --}}
    <div class="panel acrylic">
        <div class="panel-header" style="border-bottom:none; padding-bottom:0">
            <span class="panel-title" style="text-shadow: 0 0 10px rgba(255, 51, 102, 0.3)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" style="filter: drop-shadow(0 0 5px var(--danger))"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/></svg>
                Top Losers (Stocks)
            </span>
        </div>
        <div class="panel-body">
            <div id="losers-tbody" style="display:flex; flex-direction: column; gap: var(--space-2)">
                @for($i=0; $i<5; $i++)
                <div class="skeleton" style="height: 45px; border-radius: 6px; background: rgba(255,255,255,0.05);"></div>
                @endfor
            </div>
        </div>
    </div>

    {{-- Most Active (Stocks) --}}
    <div class="panel acrylic">
        <div class="panel-header" style="border-bottom:none; padding-bottom:0">
            <span class="panel-title" style="text-shadow: 0 0 10px rgba(0, 170, 255, 0.3)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="2" style="filter: drop-shadow(0 0 5px var(--info))"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Most Active (Stocks)
            </span>
        </div>
        <div class="panel-body">
            <div id="active-tbody" style="display:flex; flex-direction: column; gap: var(--space-2)">
                @for($i=0; $i<5; $i++)
                <div class="skeleton" style="height: 45px; border-radius: 6px; background: rgba(255,255,255,0.05);"></div>
                @endfor
            </div>
        </div>
    </div>
</div>

{{-- Market Overview Table --}}
<div class="panel acrylic fade-in-up" style="--delay: 0.3s;">
    <div class="panel-header">
        <span class="panel-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Market Overview — Top Volume USDT Pairs
        </span>
        <div class="panel-actions">
            <button class="btn btn-ghost btn-sm btn-sweep" onclick="location.reload()">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                Refresh
            </button>
        </div>
    </div>
    <div class="panel-body no-padding" style="overflow-x:auto; max-height:400px; overflow-y:auto">
        <table class="data-table" id="market-table">
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
                </tr>
            </thead>
            <tbody>
                @for($i=0; $i<10; $i++)
                <tr>
                    <td colspan="8"><div class="skeleton" style="height: 30px; background: rgba(255,255,255,0.02); width: 100%;"></div></td>
                </tr>
                @endfor
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
// ── Vanilla JS 3D Magnetic Tilt ──────────────────────────────────
function initTilt() {
    const cards = document.querySelectorAll('.js-tilt');
    cards.forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateX = ((y - centerY) / centerY) * -10;
            const rotateY = ((x - centerX) / centerX) * 10;

            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            card.style.boxShadow = `${-rotateY}px ${rotateX}px 30px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1)`;
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg)`;
            card.style.boxShadow = 'none';
        });
    });
}

let mixedTickerData = [];

document.addEventListener('DOMContentLoaded', function() {
    initTilt();
    loadDashboardData();
    initSSE();
});

async function loadDashboardData() {
    // 1. Load Market Summary (Crypto + Ticker Base)
    fetch('/api/market/dashboard/market-summary')
        .then(r => r.json())
        .then(data => {
            renderMarketTable(data.topPairs);
            
            // Build initial mixed ticker data from crypto
            data.topPairs.slice(0, 10).forEach(p => {
                mixedTickerData.push({ symbol: p.pair, display: p.pair, price: p.price, change: p.change, type: 'crypto' });
            });
            renderMixedTicker();
        });

    // 2. Load Stock Summary (Stocks + Heatmap + KPIs)
    fetch('/api/market/dashboard/stock-summary')
        .then(r => r.json())
        .then(data => {
            renderKPIs(data);
            renderHeatmap(data.stocks);
            renderStockMovers(data);
            
            // Add stocks to mixed ticker
            data.stocks.slice(0, 7).forEach(s => {
                mixedTickerData.push({ symbol: 'stock:' + s.symbol, display: s.symbol, price: s.price, change: s.change_pct, type: 'stock' });
            });
            renderMixedTicker();
        });

    // 3. Load Global Rates (Forex + Commodities)
    fetch('/api/market/dashboard/global-rates')
        .then(r => r.json())
        .then(data => {
            // Add forex to mixed ticker
            const forexRates = data.forex.rates || {};
            ['EUR', 'GBP', 'JPY', 'AUD'].forEach(cur => {
                if (forexRates[cur]) {
                    const isInverse = ['JPY', 'CAD', 'CHF', 'CNY', 'SGD', 'HKD', 'IDR', 'MYR', 'THB'].includes(cur);
                    const rate = isInverse ? (forexRates[cur].rate_inverse || 1) : (forexRates[cur].rate || 1);
                    const display = isInverse ? `USD/${cur}` : `${cur}/USD`;
                    mixedTickerData.push({ symbol: 'forex:' + cur, display: display, price: rate, change: 0, type: 'forex' });
                }
            });

            // Add commodities to mixed ticker
            data.commodities.forEach(c => {
                mixedTickerData.push({ symbol: 'commodity:' + c.symbol, display: c.name || c.symbol, price: c.price, change: c.change_pct, type: 'commodity' });
            });
            renderMixedTicker();
        });
}

function renderKPIs(data) {
    const kpiData = [
        data.indices.find(i => i.symbol === '^JKSE'),
        data.indices.find(i => i.symbol === 'SPY'),
        data.indices.find(i => i.symbol === '^IXIC') || data.indices.find(i => i.symbol === 'QQQ'),
        data.btcTicker,
        data.xauTicker
    ];

    kpiData.forEach((d, i) => {
        const card = document.getElementById(`kpi-card-${i}`);
        if (!card || !d) return;

        const isStock = i < 3;
        const price = isStock ? d.price : (parseFloat(d.lastPrice) || 0);
        const change = isStock ? d.change_pct : (parseFloat(d.priceChangePercent) || 0);
        const label = card.querySelector('.kpi-label').textContent.trim();
        const isSPY = label.includes('S&P 500 ETF');
        const prefix = (isStock && label.includes('IHSG')) ? 'Rp' : '$';

        card.innerHTML = `
            <div class="kpi-header">
                <span class="kpi-label">${label}</span>
                <span class="kpi-icon" style="font-size:1.2rem; filter:drop-shadow(0 0 8px var(--accent-glow))">${card.querySelector('.kpi-icon').textContent}</span>
            </div>
            <div class="kpi-value text-mono">${prefix}${price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
            <div class="kpi-delta ${change >= 0 ? 'positive' : 'negative'}">
                ${change >= 0 ? '▲' : '▼'}
                ${Math.abs(change).toFixed(2)}%
                <span style="opacity:0.7;margin-left:4px">24h</span>
            </div>
        `;
        
        card.onclick = () => window.location = `/trading?symbol=${isStock ? 'stock:' + d.symbol : d.symbol}`;
    });
    initTilt();
}

function renderHeatmap(stocks) {
    const grid = document.getElementById('heatmap-grid');
    if (!grid) return;

    const sorted = [...stocks].sort((a,b) => b.volume - a.volume).slice(0, 15);
    grid.innerHTML = sorted.map(s => {
        const intensity = Math.min(1, Math.abs(s.change_pct) / 5);
        const bg = s.change_pct >= 0
              ? `rgba(0, 255, 136, ${0.15 + intensity * 0.8})`
              : `rgba(255, 51, 102, ${0.15 + intensity * 0.8})`;
        const color = intensity > 0.3 ? '#000' : 'var(--text-primary)';
        const shadow = s.change_pct >= 0 ? '0 0 10px rgba(0,255,136,0.2)' : '0 0 10px rgba(255,51,102,0.2)';
        
        return `<div class="heatmap-cell" style="background: ${bg}; color: ${color}; box-shadow: inset ${shadow}; border-radius: 4px; padding: 15px 10px; display: flex; flex-direction: column; justify-content: center; align-items: center; cursor: pointer; transition: transform 0.2s, filter 0.2s; min-height: 90px; border: 1px solid rgba(255,255,255,0.05);" onclick="window.location='/trading?symbol=stock:${s.symbol}'" onmouseover="this.style.filter='brightness(1.2)'" onmouseout="this.style.filter='none'">
            <div style="font-weight: 800; font-size: 1.2rem; letter-spacing: 0.5px; margin-bottom: 4px;">${s.symbol}</div>
            <div class="text-mono" style="font-size: 0.85rem; font-weight: 600; opacity: 0.9;">${s.change_pct >= 0 ? '+' : ''}${s.change_pct.toFixed(2)}%</div>
        </div>`;
    }).join('');
}

function renderStockMovers(data) {
    const renderList = (id, list, type) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = list.map(item => `
            <div class="watchlist-item" onclick="window.location='/trading?symbol=stock:${item.symbol}'" style="background: rgba(${type==='gain'?'0,255,136':'255,51,102'},0.05); padding: 8px 12px; border-radius: 6px;">
                <div style="flex:1">
                    <div style="font-weight:700; color: var(--text-primary);">${item.symbol}</div>
                    <div style="font-size:0.7rem; color: var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: 100px;">${item.name}</div>
                </div>
                <div class="text-mono" style="text-align:right">
                    <div style="color: var(--text-primary); font-size: 1rem;">$${item.price.toFixed(2)}</div>
                    <div style="font-size: 0.75rem; color: var(--${type==='gain'?'success':'danger'});">${item.change_pct >= 0 ? '+' : ''}${item.change_pct.toFixed(2)}%</div>
                </div>
            </div>
        `).join('');
    };

    renderList('gainers-tbody', data.stockGainers, 'gain');
    renderList('losers-tbody', data.stockLosers, 'loss');
    
    const activeEl = document.getElementById('active-tbody');
    if (activeEl) {
        activeEl.innerHTML = data.activeStocks.slice(0, 10).map(a => `
            <div class="watchlist-item" onclick="window.location='/trading?symbol=stock:${a.symbol}'" style="background: rgba(0,170,255,0.05); padding: 8px 12px; border-radius: 6px;">
                <div style="flex:1">
                    <div style="font-weight:700; color: var(--text-primary);">${a.symbol}</div>
                    <div style="font-size:0.7rem; color: var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: 100px;">${a.name}</div>
                </div>
                <div class="text-mono" style="text-align:right">
                    <div style="color: var(--text-primary); font-size: 0.9rem;">Vol: ${(a.volume/1000000).toFixed(1)}M</div>
                    <div style="font-size: 0.75rem; color: ${a.change_pct >= 0 ? 'var(--success)' : 'var(--danger)'};">${a.change_pct >= 0 ? '+' : ''}${a.change_pct.toFixed(2)}%</div>
                </div>
            </div>
        `).join('');
    }
}

function renderMarketTable(pairs) {
    const tbody = document.querySelector('#market-table tbody');
    if (!tbody) return;
    tbody.innerHTML = pairs.map((p, i) => {
        const cls = p.change >= 0 ? 'positive' : 'negative';
        const vol = p.quoteVolume >= 1e9 ? (p.quoteVolume / 1e9).toFixed(2) + 'B' : (p.quoteVolume / 1e6).toFixed(1) + 'M';
        return `<tr onclick="window.location='/trading?symbol=${p.symbol}'" style="cursor:pointer">
            <td style="color:var(--text-muted)">${i+1}</td>
            <td style="font-weight:600">${p.pair}</td>
            <td class="align-right">$${formatPrice(p.price)}</td>
            <td class="align-right ${cls}">${p.change >= 0 ? '+' : ''}${p.change.toFixed(2)}%</td>
            <td class="align-right">$${formatPrice(p.high || 0)}</td>
            <td class="align-right">$${formatPrice(p.low || 0)}</td>
            <td class="align-right">${vol}</td>
            <td class="align-right">${(p.trades||0).toLocaleString()}</td>
        </tr>`;
    }).join('');
}

function initSSE() {
    const sseSource = new EventSource('{{ url('/api/market/stream') }}?page=dashboard');
    sseSource.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            if (data.market) {
                renderMarketTable(data.market);
                updateTickerPrices(data.market);
            }
            if (data.watchlist) {
                updateKPIPrices(data.watchlist);
            }
        } catch(e) { console.error('SSE Error:', e); }
    };
}

function updateKPIPrices(tickers) {
    const map = {}; tickers.forEach(t => map[t.symbol] = t);
    const kpiSymbols = [null, null, null, 'BTCUSDT', 'PAXGUSDT']; // Stocks not updated via SSE yet
    
    kpiSymbols.forEach((sym, i) => {
        if (!sym || !map[sym]) return;
        const card = document.getElementById(`kpi-card-${i}`);
        if (!card) return;
        
        const t = map[sym];
        const valEl = card.querySelector('.kpi-value');
        const deltaEl = card.querySelector('.kpi-delta');
        const price = parseFloat(t.lastPrice), pct = parseFloat(t.priceChangePercent);
        
        if (valEl) valEl.textContent = '$' + formatPrice(price);
        if (deltaEl) {
            deltaEl.className = `kpi-delta ${pct >= 0 ? 'positive' : 'negative'}`;
            deltaEl.innerHTML = `${pct >= 0 ? '▲' : '▼'} ${Math.abs(pct).toFixed(2)}% <span style="opacity:0.7;margin-left:4px">24h</span>`;
        }
    });
}

function updateTickerPrices(cryptoData) {
    const cryptoMap = {}; cryptoData.forEach(c => cryptoMap[c.pair] = c);
    mixedTickerData = mixedTickerData.map(p => {
        if (p.type === 'crypto' && cryptoMap[p.symbol]) {
            p.price = cryptoMap[p.symbol].price;
            p.change = cryptoMap[p.symbol].change;
        }
        return p;
    });
    renderMixedTicker();
}

function renderMixedTicker() {
    const track = document.getElementById('mixed-ticker-track') || document.getElementById('ticker-track');
    if (!track) return;
    
    if (!mixedTickerData.length) return; // Don't overwrite the crypto ticker if we have nothing yet
    
    const html = mixedTickerData.map(p => {
        const cls = p.change >= 0 ? 'positive' : 'negative';
        const displaySym = p.display || p.symbol;
        const changeStr = p.change ? (p.change>=0?'▲':'▼') + Math.abs(p.change).toFixed(2) + '%' : '';
        const priceStr = p.type === 'forex' ? p.price.toFixed(4) : (p.price >= 1000 ? p.price.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) : p.price.toFixed(2));
        return `<span class="ticker-item" style="cursor:pointer" onclick="window.location='/trading?symbol=${p.symbol}'"><span class="symbol">${displaySym}</span><span class="price">$${priceStr}</span><span class="change ${cls}">${changeStr}</span></span>`;
    }).join('');
    
    track.innerHTML = html + html;
}

function formatPrice(price) {
    if (price >= 1000) return price.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (price >= 1) return price.toFixed(2);
    if (price >= 0.01) return price.toFixed(4);
    return price.toFixed(6);
}
</script>
@endsection
