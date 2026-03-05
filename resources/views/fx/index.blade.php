@extends('layouts.app')

@section('title', 'FX & Rates')
@section('page-title', 'Global FX & Rates')

@section('content')
<div class="grid-2 fade-in-up" style="--delay: 0.1s; align-items: start;">
    {{-- Live Exchange Rates Table --}}
    <div class="panel acrylic flex-col" style="height: 600px;">
        <div class="panel-header fixed-header">
            <span class="panel-title">💱 Live Exchange Rates (vs USD)</span>
            <div class="panel-actions">
                <span id="fx-update-time" style="font-size: 0.7rem; color: var(--text-muted); margin-right: 8px;"></span>
                <button class="btn btn-ghost btn-sm btn-sweep" onclick="loadForexData()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                </button>
            </div>
        </div>
        <div class="panel-body no-padding" style="flex:1; overflow-y:auto; overflow-x:auto;">
            <table class="data-table" id="forex-table">
                <thead>
                    <tr>
                        <th>Currency</th>
                        <th class="align-right">Rate (per USD)</th>
                        <th class="align-right">Inverse (USD per 1)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:2rem;">Loading rates...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap: var(--space-4)">
        {{-- Currency Converter --}}
        <div class="panel acrylic">
            <div class="panel-header">
                <span class="panel-title">🔄 Currency Converter</span>
            </div>
            <div class="panel-body">
                <div style="display:flex; gap: var(--space-3); align-items:center; margin-bottom: var(--space-3)">
                    <div style="flex:1">
                        <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:4px">Amount</label>
                        <input type="number" id="conv-amount" class="form-input" style="width:100%" value="1000" oninput="convertCurrency()">
                    </div>
                </div>
                <div style="display:flex; gap: var(--space-3); align-items:center;">
                    <div style="flex:1">
                        <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:4px">From</label>
                        <select id="conv-from" class="form-input" style="width:100%" onchange="convertCurrency()">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="JPY">JPY</option>
                        </select>
                    </div>
                    <div style="margin-top: 20px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </div>
                    <div style="flex:1">
                        <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:4px">To</label>
                        <select id="conv-to" class="form-input" style="width:100%" onchange="convertCurrency()">
                            <option value="EUR">EUR</option>
                            <option value="USD">USD</option>
                            <option value="GBP">GBP</option>
                            <option value="JPY">JPY</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top: var(--space-4); padding: var(--space-3); background: rgba(0,0,0,0.2); border-radius: var(--radius-sm); border: 1px solid rgba(255,255,255,0.05);">
                    <div style="font-size: 0.75rem; color: var(--text-muted)">Converted Amount</div>
                    <div class="text-mono" style="font-size: 1.5rem; font-weight: 700; color: var(--accent)" id="conv-result">--</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px" id="conv-rate">--</div>
                </div>
            </div>
        </div>

        {{-- Cross-Rate Matrix --}}
        <div class="panel acrylic">
            <div class="panel-header">
                <span class="panel-title">🔲 Major Cross-Rates</span>
            </div>
            <div class="panel-body no-padding" style="overflow-x:auto;">
                <table class="data-table" id="cross-table" style="font-size: 0.8rem;">
                    <thead>
                        <tr>
                            <th style="background: rgba(0,0,0,0.2)">Base \ Quote</th>
                            <th>USD</th><th>EUR</th><th>GBP</th><th>JPY</th><th>AUD</th><th>CHF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:2rem;">Loading matrix...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let globalRates = {};

document.addEventListener('DOMContentLoaded', () => {
    loadForexData();
    setInterval(loadForexData, 300000); // 5 min
});

function loadForexData() {
    fetch('/api/market/forex-full')
        .then(r => r.json())
        .then(data => {
            if (!data || !data.rates) return;
            globalRates = data.rates;
            globalRates['USD'] = { rate: 1, rate_inverse: 1 }; // Base
            
            if (data.date) {
                document.getElementById('fx-update-time').textContent = 'Live • ' + new Date().toLocaleTimeString();
            }

            renderRatesTable();
            populateSelects();
            renderCrossMatrix();
            convertCurrency();
        })
        .catch(console.error);
}

function renderRatesTable() {
    const tbody = document.querySelector('#forex-table tbody');
    const priority = ['EUR','GBP','JPY','AUD','CAD','CHF','CNY','HKD','SGD','NZD','INR','BRL','ZAR','MXN','IDR'];
    let html = '';
    
    // Ordered
    priority.forEach(cur => {
        if (globalRates[cur]) {
            html += _trRate(cur, globalRates[cur]);
        }
    });

    // The rest
    Object.keys(globalRates).forEach(cur => {
        if (!priority.includes(cur) && cur !== 'USD') {
            html += _trRate(cur, globalRates[cur]);
        }
    });

    tbody.innerHTML = html;
}

function _trRate(cur, r) {
    const isJpyIds = ['JPY','IDR','KRW','VND'].includes(cur);
    const dec1 = isJpyIds ? 2 : 4;
    const dec2 = r.rate_inverse < 0.01 ? 6 : (r.rate_inverse < 1 ? 4 : 2);
    
    return `<tr>
        <td style="font-weight:600"><span style="color:var(--text-muted);font-size:0.8em;margin-right:4px">USD/</span>${cur}</td>
        <td class="align-right text-mono">${r.rate.toFixed(dec1)}</td>
        <td class="align-right text-mono" style="color:var(--text-muted)">${r.rate_inverse.toFixed(dec2)}</td>
    </tr>`;
}

function renderCrossMatrix() {
    const tbody = document.querySelector('#cross-table tbody');
    const majors = ['USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CHF'];
    let html = '';
    
    majors.forEach(base => {
        html += `<tr><td style="font-weight:bold; background:rgba(0,0,0,0.2)">${base}</td>`;
        majors.forEach(quote => {
            if (base === quote) {
                html += `<td class="align-right" style="color:var(--text-muted)">—</td>`;
            } else {
                // Rate of Base/Quote = Rate(USD/Quote) / Rate(USD/Base)
                const rb = globalRates[base] ? globalRates[base].rate : 0;
                const rq = globalRates[quote] ? globalRates[quote].rate : 0;
                let cross = 0;
                if (rb > 0) cross = rq / rb;
                
                const dec = quote === 'JPY' ? 2 : 5;
                html += `<td class="align-right text-mono ${cross>1?'positive':''}">${cross>0?cross.toFixed(dec):'--'}</td>`;
            }
        });
        html += `</tr>`;
    });
    
    tbody.innerHTML = html;
}

function populateSelects() {
    const fromSel = document.getElementById('conv-from');
    const toSel = document.getElementById('conv-to');
    
    const currFrom = fromSel.value;
    const currTo = toSel.value;
    
    const curs = Object.keys(globalRates).sort();
    let opts = '';
    curs.forEach(c => {
        opts += `<option value="${c}">${c}</option>`;
    });
    
    fromSel.innerHTML = opts;
    toSel.innerHTML = opts;
    
    if (curs.includes(currFrom)) fromSel.value = currFrom;
    else fromSel.value = 'USD';
    
    if (curs.includes(currTo)) toSel.value = currTo;
    else toSel.value = 'EUR';
}

function convertCurrency() {
    if (Object.keys(globalRates).length === 0) return;
    
    const amt = parseFloat(document.getElementById('conv-amount').value) || 0;
    const from = document.getElementById('conv-from').value;
    const to = document.getElementById('conv-to').value;
    
    const rFrom = globalRates[from] ? globalRates[from].rate : 1;
    const rTo = globalRates[to] ? globalRates[to].rate : 1;
    
    // Amount usually given in BASE. USD is the common link. 
    // USD amount = amt / rFrom
    // To amount = USD amount * rTo
    let res = 0;
    let rate = 0;
    if (rFrom > 0) {
        res = (amt / rFrom) * rTo;
        rate = rTo / rFrom;
    }
    
    const eDec = ['JPY','IDR','KRW'].includes(to) ? 2 : 2; // Always format result to 2 for display, except maybe some with 0
    document.getElementById('conv-result').textContent = res.toLocaleString('en-US', {minimumFractionDigits:eDec, maximumFractionDigits:eDec}) + ' ' + to;
    document.getElementById('conv-rate').textContent = `1 ${from} = ${rate.toFixed(6)} ${to}`;
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
#cross-table th, #cross-table td {
    padding: 8px 12px;
}
</style>
@endsection
