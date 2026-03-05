

<?php $__env->startSection('title', 'Derivatives & Options'); ?>
<?php $__env->startSection('page-title', 'Derivatives & Options'); ?>

<?php $__env->startSection('content'); ?>
<div class="grid-2 fade-in-up" style="--delay: 0.1s; align-items: start;">
    
    <div class="panel acrylic flex-col" style="height: 800px;">
        <div class="panel-header fixed-header" style="flex-wrap: wrap; gap: var(--space-2)">
            <span class="panel-title">🎯 Equity Options Chain</span>
            <div style="display:flex; gap: 8px; flex: 1; min-width: 250px;">
                <input type="text" id="opt-symbol" class="form-input" value="<?php echo e($symbol); ?>" style="width:100px; text-transform:uppercase" placeholder="Symbol">
                <select id="opt-expiry" class="form-input" style="flex:1" onchange="loadOptionsChain()">
                    <option value="">Loading Expirations...</option>
                </select>
                <button class="btn btn-primary btn-sm" onclick="loadExpirations()">Load</button>
            </div>
        </div>
        
        <div class="panel-body no-padding" style="flex:1; overflow-y:auto; overflow-x:auto;">
            <div id="opt-loader" style="text-align:center; padding: 2rem; color: var(--text-muted);">Enter a symbol to load options</div>
            
            <table class="data-table" id="opt-table" style="display:none; font-size:0.75rem;">
                <thead>
                    <tr>
                        <th colspan="5" style="text-align:center; background: rgba(34, 197, 94, 0.1); border-bottom: 2px solid var(--success)">CALLS</th>
                        <th style="text-align:center; background: rgba(0,0,0,0.3)">STRIKE</th>
                        <th colspan="5" style="text-align:center; background: rgba(239, 68, 68, 0.1); border-bottom: 2px solid var(--danger)">PUTS</th>
                    </tr>
                    <tr>
                        <th>Bid</th><th>Ask</th><th>Last</th><th>Vol</th><th>IV</th>
                        <th style="background: rgba(0,0,0,0.2); text-align:center">Price</th>
                        <th>IV</th><th>Vol</th><th>Last</th><th>Ask</th><th>Bid</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        <div class="panel-footer" style="padding: 8px 16px; font-size: 0.7rem; color: var(--text-muted); border-top: 1px solid rgba(255,255,255,0.05); text-align: center;">
            Underlying: <span id="opt-underlying" class="text-mono" style="color:var(--text-primary); font-weight:bold">--</span> | Pricing Model: Black-Scholes
        </div>
    </div>

    
    <div class="panel acrylic flex-col" style="height: 800px;">
        <div class="panel-header fixed-header">
            <span class="panel-title">⚡ Crypto Perpetuals (Binance)</span>
            <div class="panel-actions">
                <span id="fut-update-time" style="font-size: 0.7rem; color: var(--text-muted); margin-right: 8px;"></span>
                <button class="btn btn-ghost btn-sm btn-sweep" onclick="loadCryptoFutures()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                </button>
            </div>
        </div>
        <div class="panel-body no-padding" style="flex:1; overflow-y:auto; overflow-x:auto;">
            <table class="data-table" id="futures-table">
                <thead>
                    <tr>
                        <th>Symbol</th>
                        <th class="align-right">Mark Price</th>
                        <th class="align-right">24h Change</th>
                        <th class="align-right">Funding Rate</th>
                        <th class="align-right">24h Vol</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:2rem;">Loading futures...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


<div class="panel acrylic fade-in-up" style="--delay: 0.2s; margin-top: var(--space-4)">
    <div class="panel-header">
        <span class="panel-title">🧮 Black-Scholes Options Calculator</span>
    </div>
    <div class="panel-body">
        <div style="display:flex; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-4)">
            <div style="flex:1; min-width: 150px">
                <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:4px">Underlying Price (S)</label>
                <input type="number" id="bs-s" class="form-input" style="width:100%" value="150" step="0.01" oninput="runBS()">
            </div>
            <div style="flex:1; min-width: 150px">
                <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:4px">Strike Price (K)</label>
                <input type="number" id="bs-k" class="form-input" style="width:100%" value="150" step="0.01" oninput="runBS()">
            </div>
            <div style="flex:1; min-width: 150px">
                <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:4px">Time to Expiry (Days)</label>
                <input type="number" id="bs-t" class="form-input" style="width:100%" value="30" oninput="runBS()">
            </div>
            <div style="flex:1; min-width: 150px">
                <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:4px">Volatility (%)</label>
                <input type="number" id="bs-v" class="form-input" style="width:100%" value="30" step="0.1" oninput="runBS()">
            </div>
            <div style="flex:1; min-width: 150px">
                <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:4px">Risk-Free Rate (%)</label>
                <input type="number" id="bs-r" class="form-input" style="width:100%" value="4.5" step="0.1" oninput="runBS()">
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
            <div style="padding: var(--space-3); background: rgba(34, 197, 94, 0.1); border-radius: var(--radius-md); border: 1px solid rgba(34, 197, 94, 0.3);">
                <div style="color:var(--success); font-weight:bold; margin-bottom: 8px;">CALL OPTION</div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px">
                    <span style="color:var(--text-muted)">Theoretical Price</span>
                    <span class="text-mono" style="font-size:1.2rem; font-weight:bold" id="bs-call-p">--</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size: 0.8rem">
                    <span style="color:var(--text-muted)">Delta (Δ)</span><span class="text-mono" id="bs-call-d">--</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size: 0.8rem">
                    <span style="color:var(--text-muted)">Gamma (Γ)</span><span class="text-mono" id="bs-call-g">--</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size: 0.8rem">
                    <span style="color:var(--text-muted)">Theta (Θ)</span><span class="text-mono" id="bs-call-t">--</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size: 0.8rem">
                    <span style="color:var(--text-muted)">Vega (ν)</span><span class="text-mono" id="bs-call-v">--</span>
                </div>
            </div>
            
            <div style="padding: var(--space-3); background: rgba(239, 68, 68, 0.1); border-radius: var(--radius-md); border: 1px solid rgba(239, 68, 68, 0.3);">
                <div style="color:var(--danger); font-weight:bold; margin-bottom: 8px;">PUT OPTION</div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px">
                    <span style="color:var(--text-muted)">Theoretical Price</span>
                    <span class="text-mono" style="font-size:1.2rem; font-weight:bold" id="bs-put-p">--</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size: 0.8rem">
                    <span style="color:var(--text-muted)">Delta (Δ)</span><span class="text-mono" id="bs-put-d">--</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size: 0.8rem">
                    <span style="color:var(--text-muted)">Gamma (Γ)</span><span class="text-mono" id="bs-put-g">--</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size: 0.8rem">
                    <span style="color:var(--text-muted)">Theta (Θ)</span><span class="text-mono" id="bs-put-t">--</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size: 0.8rem">
                    <span style="color:var(--text-muted)">Vega (ν)</span><span class="text-mono" id="bs-put-v">--</span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    loadExpirations();
    loadCryptoFutures();
    runBS();
    
    document.getElementById('opt-symbol').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadExpirations();
    });
    
    setInterval(loadCryptoFutures, 30000); // 30s updates for futures
});

let currentExpirations = [];

function loadExpirations() {
    const sym = document.getElementById('opt-symbol').value.trim();
    if (!sym) return;
    
    document.getElementById('opt-loader').style.display = 'block';
    document.getElementById('opt-loader').textContent = 'Loading expirations...';
    document.getElementById('opt-table').style.display = 'none';
    
    // Update URL 
    const url = new URL(window.location);
    url.searchParams.set('symbol', sym);
    window.history.pushState({}, '', url);

    fetch(`/api/market/options-chain?symbol=${sym}`)
        .then(r => r.json())
        .then(data => {
            if (!data || !data.expirations || data.expirations.length === 0) {
                document.getElementById('opt-loader').textContent = 'No options data found for ' + sym;
                return;
            }
            currentExpirations = data.expirations;
            const sel = document.getElementById('opt-expiry');
            sel.innerHTML = currentExpirations.map(ts => {
                const date = new Date(ts * 1000).toISOString().split('T')[0];
                return `<option value="${ts}">${date}</option>`;
            }).join('');
            
            _renderOptionsChainData(data);
        })
        .catch(console.error);
}

function loadOptionsChain() {
    const sym = document.getElementById('opt-symbol').value.trim();
    const expiry = document.getElementById('opt-expiry').value;
    if (!sym || !expiry) return;
    
    document.getElementById('opt-loader').style.display = 'block';
    document.getElementById('opt-loader').textContent = 'Loading chain...';
    document.getElementById('opt-table').style.display = 'none';
    
    fetch(`/api/market/options-chain?symbol=${sym}&expiry=${expiry}`)
        .then(r => r.json())
        .then(data => {
            _renderOptionsChainData(data);
        })
        .catch(console.error);
}

function _renderOptionsChainData(data) {
    document.getElementById('opt-loader').style.display = 'none';
    document.getElementById('opt-table').style.display = 'table';
    
    const uPrice = data.underlyingPrice || 0;
    document.getElementById('opt-underlying').textContent = '$' + uPrice.toFixed(2);
    // pre-fill calculator
    document.getElementById('bs-s').value = uPrice;
    
    const opt = data.options || {};
    const calls = opt.calls || [];
    const puts = opt.puts || [];
    
    // Group by strike
    const strikes = new Set([...calls.map(c=>c.strike), ...puts.map(p=>p.strike)]);
    const sortedStrikes = Array.from(strikes).sort((a,b)=>a-b);
    
    const callMap = {}; calls.forEach(c => callMap[c.strike] = c);
    const putMap = {}; puts.forEach(p => putMap[p.strike] = p);
    
    const tbody = document.querySelector('#opt-table tbody');
    tbody.innerHTML = sortedStrikes.map(K => {
        const c = callMap[K] || {};
        const p = putMap[K] || {};
        
        const fmt = (n) => n !== undefined ? Number(n).toFixed(2) : '-';
        const pct = (n) => n !== undefined ? (Number(n)*100).toFixed(1)+'%' : '-';
        
        const isITMCall = K < uPrice;
        const isITMPut = K > uPrice;
        
        const cBg = isITMCall ? 'rgba(34, 197, 94, 0.05)' : 'transparent';
        const pBg = isITMPut ? 'rgba(239, 68, 68, 0.05)' : 'transparent';
        
        const trClick = `onclick="document.getElementById('bs-k').value='${K}'; runBS(); window.scrollTo(0, document.body.scrollHeight);" style="cursor:pointer" title="Click to price in Black-Scholes"`;
        
        return `<tr ${trClick}>
            <td class="align-right text-mono" style="background:${cBg}">${fmt(c.bid)}</td>
            <td class="align-right text-mono" style="background:${cBg}">${fmt(c.ask)}</td>
            <td class="align-right text-mono" style="background:${cBg}; font-weight:bold; color:var(--text-primary)">${fmt(c.lastPrice)}</td>
            <td class="align-right text-mono" style="background:${cBg}">${c.volume||'-'}</td>
            <td class="align-right text-mono" style="background:${cBg}">${pct(c.impliedVolatility)}</td>
            
            <td class="align-center text-mono" style="text-align:center; font-weight:bold; color:var(--accent); background: rgba(0,0,0,0.2)">${K.toFixed(2)}</td>
            
            <td class="align-right text-mono" style="background:${pBg}">${pct(p.impliedVolatility)}</td>
            <td class="align-right text-mono" style="background:${pBg}">${p.volume||'-'}</td>
            <td class="align-right text-mono" style="background:${pBg}; font-weight:bold; color:var(--text-primary)">${fmt(p.lastPrice)}</td>
            <td class="align-right text-mono" style="background:${pBg}">${fmt(p.ask)}</td>
            <td class="align-right text-mono" style="background:${pBg}">${fmt(p.bid)}</td>
        </tr>`;
    }).join('');
}


function loadCryptoFutures() {
    fetch('/api/market/crypto-futures')
        .then(r => r.json())
        .then(data => {
            const dt = new Date();
            document.getElementById('fut-update-time').textContent = 'Live • ' + dt.toLocaleTimeString();
            const tbody = document.querySelector('#futures-table tbody');
            tbody.innerHTML = data.map(f => {
                const cls = f.change_pct >= 0 ? 'positive' : 'negative';
                const sign= f.change_pct >= 0 ? '+' : '';
                const fRate = (f.funding_rate * 100).toFixed(4) + '%';
                const fCls = f.funding_rate >= 0 ? 'positive' : (f.funding_rate < 0 ? 'negative' : 'text-primary');
                
                return `<tr>
                    <td style="font-weight:bold" onclick="window.location='/trading?symbol=${f.symbol}'" title="Open Trading View">${f.symbol}</td>
                    <td class="align-right text-mono">$${parseFloat(f.mark_price) < 1 ? parseFloat(f.mark_price).toFixed(5) : parseFloat(f.mark_price).toFixed(2)}</td>
                    <td class="align-right text-mono ${cls}">${sign}${parseFloat(f.change_pct).toFixed(2)}%</td>
                    <td class="align-right text-mono ${fCls}">${fRate}</td>
                    <td class="align-right text-mono">${parseFloat(f.volume) > 1e9 ? (parseFloat(f.volume)/1e9).toFixed(2)+'B' : (parseFloat(f.volume)/1e6).toFixed(1)+'M'}</td>
                </tr>`;
            }).join('');
        })
        .catch(console.error);
}

// Simple normal CDF
function CND(x){
    const a1 =  0.31938153, a2 = -0.356563782, a3 =  1.781477937;
    const a4 = -1.821255978, a5 =  1.330274429;
    const L = Math.abs(x);
    const K = 1.0 / (1.0 + 0.2316419 * L);
    const w = 1.0 - 1.0 / Math.sqrt(2 * Math.PI) * Math.exp(-L * L / 2) * (a1 * K + a2 * K *K + a3 * Math.pow(K,3) + a4 * Math.pow(K,4) + a5 * Math.pow(K,5));
    if (x < 0) return 1.0 - w;
    return w;
}
function ND(x) { return Math.exp(-0.5*x*x) / Math.sqrt(2*Math.PI); }

function runBS() {
    const S = parseFloat(document.getElementById('bs-s').value);
    const K = parseFloat(document.getElementById('bs-k').value);
    const tDays = parseFloat(document.getElementById('bs-t').value);
    const vPct = parseFloat(document.getElementById('bs-v').value);
    const rPct = parseFloat(document.getElementById('bs-r').value);
    
    if(!S || !K || tDays===0 || !vPct) return;
    
    const T = tDays / 365.25;
    const v = vPct / 100;
    const r = rPct / 100;
    
    const d1 = (Math.log(S/K) + (r + v*v/2)*T) / (v * Math.sqrt(T));
    const d2 = d1 - v * Math.sqrt(T);
    
    const cPrice = S * CND(d1) - K * Math.exp(-r*T) * CND(d2);
    const pPrice = K * Math.exp(-r*T) * CND(-d2) - S * CND(-d1);
    
    document.getElementById('bs-call-p').textContent = '$' + cPrice.toFixed(2);
    document.getElementById('bs-put-p').textContent = '$' + pPrice.toFixed(2);
    
    // Greeks
    const nd1 = ND(d1);
    const cDelta = CND(d1);
    const pDelta = cDelta - 1;
    
    const gamma = nd1 / (S * v * Math.sqrt(T));
    const vega = S * nd1 * Math.sqrt(T) / 100; // per 1%
    
    const cTheta = (- (S * v * nd1) / (2 * Math.sqrt(T)) - r * K * Math.exp(-r*T) * CND(d2)) / 365;
    const pTheta = (- (S * v * nd1) / (2 * Math.sqrt(T)) + r * K * Math.exp(-r*T) * CND(-d2)) / 365;
    
    document.getElementById('bs-call-d').textContent = cDelta.toFixed(3);
    document.getElementById('bs-put-d').textContent = pDelta.toFixed(3);
    
    document.getElementById('bs-call-g').textContent = gamma.toFixed(4);
    document.getElementById('bs-put-g').textContent = gamma.toFixed(4);
    
    document.getElementById('bs-call-v').textContent = vega.toFixed(3);
    document.getElementById('bs-put-v').textContent = vega.toFixed(3);
    
    document.getElementById('bs-call-t').textContent = cTheta.toFixed(3);
    document.getElementById('bs-put-t').textContent = pTheta.toFixed(3);
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
#opt-table th { padding: 4px 8px; font-size: 0.7rem; }
#opt-table td { padding: 4px 8px; border-bottom: 1px solid rgba(255,255,255,0.05); }
#opt-table tbody tr:hover { background: rgba(255,255,255,0.05); }
</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\Project\market_pro\resources\views/derivatives/index.blade.php ENDPATH**/ ?>