@extends('layouts.app')

@section('title', 'Equity Analytics')
@section('page-title', 'Equity Analytics')

@section('content')
<div style="margin-bottom: var(--space-4); display: flex; gap: var(--space-2);">
    <input type="text" id="symbol-input" value="{{ $symbol }}" class="form-input" style="width: 200px; text-transform: uppercase;" placeholder="Enter Stock Symbol (e.g. AAPL)">
    <button class="btn btn-primary" onclick="loadEquityData()">Analyze</button>
</div>

<div class="grid-2 fade-in-up" style="--delay: 0.1s; align-items: stretch;">
    {{-- Valuation --}}
    <div class="panel acrylic">
        <div class="panel-header">
            <span class="panel-title">📉 Valuation (<span id="disp-symbol">{{ $symbol }}</span>)</span>
        </div>
        <div class="panel-body">
            <div id="val-loader" style="text-align:center; padding: 2rem; color: var(--text-muted);">Loading valuation data...</div>
            <div id="val-grid" style="display:none; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">P/E Ratio</div>
                    <div class="text-mono" style="font-size: 1.2rem; font-weight: 700;" id="val-pe">--</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Forward P/E</div>
                    <div class="text-mono" style="font-size: 1.2rem; font-weight: 700;" id="val-fpe">--</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">PEG Ratio</div>
                    <div class="text-mono" style="font-size: 1.2rem; font-weight: 700;" id="val-peg">--</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Price to Book</div>
                    <div class="text-mono" style="font-size: 1.2rem; font-weight: 700;" id="val-pb">--</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Price to Sales</div>
                    <div class="text-mono" style="font-size: 1.2rem; font-weight: 700;" id="val-ps">--</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">EV / EBITDA</div>
                    <div class="text-mono" style="font-size: 1.2rem; font-weight: 700;" id="val-evebitda">--</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Financial Ratios --}}
    <div class="panel acrylic">
        <div class="panel-header">
            <span class="panel-title">📊 Financial Ratios</span>
        </div>
        <div class="panel-body">
            <div id="rat-loader" style="text-align:center; padding: 2rem; color: var(--text-muted);">Loading ratios...</div>
            <div id="rat-grid" style="display:none; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">ROE / ROA</div>
                    <div class="text-mono" style="font-size: 1rem; font-weight: 700;"><span id="rat-roe">--</span> / <span id="rat-roa">--</span></div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Profit Margin</div>
                    <div class="text-mono" style="font-size: 1rem; font-weight: 700;" id="rat-margin">--</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Debt/Equity</div>
                    <div class="text-mono" style="font-size: 1.2rem; font-weight: 700;" id="rat-de">--</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Current Ratio</div>
                    <div class="text-mono" style="font-size: 1.2rem; font-weight: 700;" id="rat-cr">--</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Revenue Growth</div>
                    <div class="text-mono" style="font-size: 1.2rem; font-weight: 700;" id="rat-revg">--</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Div Yield</div>
                    <div class="text-mono" style="font-size: 1.2rem; font-weight: 700;" id="rat-div">--</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid-2 fade-in-up" style="--delay: 0.2s; align-items: stretch; margin-top: var(--space-4)">
    {{-- Analyst Estimates --}}
    <div class="panel acrylic">
        <div class="panel-header">
            <span class="panel-title">🎯 Analyst Estimates</span>
        </div>
        <div class="panel-body">
            <div id="est-loader" style="text-align:center; padding: 2rem; color: var(--text-muted);">Loading estimates...</div>
            <div id="est-content" style="display:none;">
                <div style="display:flex; justify-content: space-between; margin-bottom: var(--space-3)">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Target Mean</div>
                        <div class="text-mono" style="font-size: 1.5rem; font-weight: 700; color: var(--accent)" id="est-target">--</div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Rating</div>
                        <div style="font-size: 1.1rem; font-weight: 600;" id="est-rating">--</div>
                    </div>
                </div>
                <div style="margin-bottom: var(--space-4)">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 6px;">Recommendations</div>
                    <div style="display:flex; height: 12px; border-radius: 6px; overflow:hidden;" id="est-bar">
                        <!-- Populated by JS -->
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.65rem; color:var(--text-muted); margin-top:4px" id="est-bar-labels"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Peer Comparison --}}
    <div class="panel acrylic flex-col">
        <div class="panel-header fixed-header">
            <span class="panel-title">🔗 Peer Comparison</span>
        </div>
        <div class="panel-body no-padding" style="flex:1; overflow-y:auto; overflow-x:auto;">
            <table class="data-table" id="peer-table">
                <thead>
                    <tr>
                        <th>Symbol</th>
                        <th>Price</th>
                        <th class="align-right">Change</th>
                        <th class="align-right">Mkt Cap</th>
                        <th class="align-right">P/E</th>
                        <th class="align-right">P/B</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:2rem;">Loading peers...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel acrylic fade-in-up" style="--delay: 0.3s; margin-top: var(--space-4)">
    <div class="panel-header">
        <span class="panel-title">📈 Year-to-Date Chart</span>
    </div>
    <div class="panel-body no-padding" style="height: 400px; position:relative" id="chart-container">
    </div>
</div>
@endsection

@section('scripts')
<script>
let chart, series;

document.addEventListener('DOMContentLoaded', () => {
    initChart();
    loadEquityData();
    
    document.getElementById('symbol-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadEquityData();
    });
});

function loadEquityData() {
    const sym = document.getElementById('symbol-input').value.trim() || 'AAPL';
    document.getElementById('disp-symbol').textContent = sym;
    
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('symbol', sym);
    window.history.pushState({}, '', url);

    // Reset UI
    document.getElementById('val-loader').style.display = 'block';
    document.getElementById('val-grid').style.display = 'none';
    document.getElementById('rat-loader').style.display = 'block';
    document.getElementById('rat-grid').style.display = 'none';
    document.getElementById('est-loader').style.display = 'block';
    document.getElementById('est-content').style.display = 'none';
    document.querySelector('#peer-table tbody').innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:2rem;">Loading peers...</td></tr>';

    // Fetch Valuation
    fetch(`/api/market/equity-valuation?symbol=${sym}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('val-loader').style.display = 'none';
            document.getElementById('rat-loader').style.display = 'none';
            if(!data || !data.valuation) {
                document.getElementById('val-loader').textContent = 'No data available';
                document.getElementById('val-loader').style.display = 'block';
                return;
            }
            document.getElementById('val-grid').style.display = 'grid';
            document.getElementById('rat-grid').style.display = 'grid';
            
            const v = data.valuation;
            const r = data.ratios;
            
            const fmt = (n, dec=2) => n ? (+n).toFixed(dec) : '--';
            const fmtPct = (n) => Object.is(n, null) || n === undefined ? '--' : ((+n)*100).toFixed(2)+'%';

            document.getElementById('val-pe').textContent = fmt(v.pe_ratio);
            document.getElementById('val-fpe').textContent = fmt(v.forward_pe);
            document.getElementById('val-peg').textContent = fmt(v.peg_ratio);
            document.getElementById('val-pb').textContent = fmt(v.price_to_book);
            document.getElementById('val-ps').textContent = fmt(v.price_to_sales);
            document.getElementById('val-evebitda').textContent = fmt(v.ev_ebitda);

            document.getElementById('rat-roe').textContent = fmtPct(r.roe);
            document.getElementById('rat-roa').textContent = fmtPct(r.roa);
            document.getElementById('rat-margin').textContent = fmtPct(r.net_margin);
            document.getElementById('rat-de').textContent = fmt(r.debt_equity);
            document.getElementById('rat-cr').textContent = fmt(r.current_ratio);
            document.getElementById('rat-revg').textContent = fmtPct(r.revenue_growth);
            document.getElementById('rat-div').textContent = fmtPct(r.dividend_yield);
        })
        .catch(console.error);

    // Fetch Estimates
    fetch(`/api/market/analyst-estimates?symbol=${sym}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('est-loader').style.display = 'none';
            if(!data || !data.recommendation) return;
            document.getElementById('est-content').style.display = 'block';
            
            const rec = data.recommendation;
            document.getElementById('est-target').textContent = rec.targetMean ? '$'+rec.targetMean.toFixed(2) : '--';
            
            let txt = 'HOLD';
            if (rec.mean < 2) txt = 'STRONG BUY';
            else if (rec.mean < 3) txt = 'BUY';
            else if (rec.mean > 4) txt = 'STRONG SELL';
            else if (rec.mean > 3) txt = 'SELL';
            
            document.getElementById('est-rating').textContent = txt;
            document.getElementById('est-rating').style.color = rec.mean < 3 ? 'var(--success)' : (rec.mean > 3 ? 'var(--danger)' : 'var(--text-primary)');

            // Rec Bar
            const total = rec.strongBuy + rec.buy + rec.hold + rec.sell + rec.strongSell;
            if (total > 0) {
                const w1 = (rec.strongBuy/total)*100;
                const w2 = (rec.buy/total)*100;
                const w3 = (rec.hold/total)*100;
                const w4 = (rec.sell/total)*100;
                const w5 = (rec.strongSell/total)*100;
                
                document.getElementById('est-bar').innerHTML = `
                    <div style="width:${w1}%; background:#22c55e" title="Strong Buy: ${rec.strongBuy}"></div>
                    <div style="width:${w2}%; background:#86efac" title="Buy: ${rec.buy}"></div>
                    <div style="width:${w3}%; background:#94a3b8" title="Hold: ${rec.hold}"></div>
                    <div style="width:${w4}%; background:#fca5a5" title="Sell: ${rec.sell}"></div>
                    <div style="width:${w5}%; background:#ef4444" title="Strong Sell: ${rec.strongSell}"></div>
                `;
                document.getElementById('est-bar-labels').innerHTML = `
                    <span>Buy (${rec.strongBuy+rec.buy})</span>
                    <span>Hold (${rec.hold})</span>
                    <span>Sell (${rec.sell+rec.strongSell})</span>
                `;
            }
        })
        .catch(console.error);

    // Fetch Peers
    fetch(`/api/market/peer-comparison?symbol=${sym}`)
        .then(r => r.json())
        .then(peers => {
            const tbody = document.querySelector('#peer-table tbody');
            if(!peers || !peers.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">No peer data found</td></tr>';
                return;
            }
            
            tbody.innerHTML = peers.map(p => {
                const cls = p.change_pct >= 0 ? 'positive' : 'negative';
                const sign= p.change_pct >= 0 ? '+' : '';
                const mcap= p.market_cap ? (p.market_cap / 1e9).toFixed(2)+'B' : '--';
                return `<tr>
                    <td style="font-weight:bold; color: var(--accent); cursor: pointer" onclick="document.getElementById('symbol-input').value='${p.symbol}'; loadEquityData()">${p.symbol}</td>
                    <td class="text-mono">$${(p.price||0).toFixed(2)}</td>
                    <td class="align-right text-mono ${cls}">${sign}${(p.change_pct||0).toFixed(2)}%</td>
                    <td class="align-right text-mono">${mcap}</td>
                    <td class="align-right text-mono">${p.pe_ratio ? p.pe_ratio.toFixed(2) : '--'}</td>
                    <td class="align-right text-mono">${p.price_to_book ? p.price_to_book.toFixed(2) : '--'}</td>
                </tr>`;
            }).join('');
        })
        .catch(console.error);

    // Fetch Chart Data
    fetch(`/api/market/stock-candles?symbol=${sym}&resolution=D&from=${Math.floor(Date.now()/1000) - 365*86400}&to=${Math.floor(Date.now()/1000)}`)
        .then(r => r.json())
        .then(data => {
            if (data && data.length) {
                series.setData(data.map(d => ({
                    time: d[0]/1000,
                    open: d[1], high: d[2], low: d[3], close: d[4]
                })));
                chart.timeScale().fitContent();
            }
        });
}

function initChart() {
    const el = document.getElementById('chart-container');
    chart = LightweightCharts.createChart(el, {
        layout: { background: { color: 'transparent' }, textColor: '#cbd5e1' },
        grid: { vertLines: { color: 'rgba(255,255,255,0.05)' }, horzLines: { color: 'rgba(255,255,255,0.05)' } },
        rightPriceScale: { borderColor: 'rgba(255,255,255,0.1)' },
        timeScale: { borderColor: 'rgba(255,255,255,0.1)' },
    });
    series = chart.addCandlestickSeries({
        upColor: '#10b981', downColor: '#ef4444',
        borderVisible: false, wickUpColor: '#10b981', wickDownColor: '#ef4444'
    });
    
    new ResizeObserver(() => {
        chart.applyOptions({ width: el.clientWidth, height: el.clientHeight });
    }).observe(el);
}
</script>
<style>
.form-input {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.1);
    color: var(--text-primary);
    padding: 8px 12px;
    border-radius: var(--radius-sm);
    outline: none;
    font-family: var(--font-mono);
}
.form-input:focus {
    border-color: var(--accent);
}
</style>
@endsection
