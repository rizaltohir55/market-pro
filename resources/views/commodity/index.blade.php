@extends('layouts.app')

@section('title', 'Commodity Markets')
@section('page-title', 'Global Commodity Markets')

@section('content')
{{-- Fix: override compiled CSS overflow:hidden on .panel that clips the price numbers --}}
<style>
    /* The compiled app CSS sets .panel { overflow: hidden } which clips large price text.
       Specifically for commodity cards, we need overflow visible. */
    #metals-grid .bento-card,
    #energy-grid .bento-card,
    #agri-grid .bento-card {
        overflow: visible !important;
        min-height: 0 !important;
        height: auto !important;
    }
    /* Allow the panel-body to not clip its children */
    #metals-grid,
    #energy-grid,
    #agri-grid {
        overflow: visible !important;
    }
    /* The panel wrapper itself clips via overflow:hidden; we must lift it */
    .panel.acrylic.fade-in-up {
        overflow: visible !important;
    }
</style>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: var(--space-4)">
    <p style="color:var(--text-muted); margin:0;">Real-time & delayed quotes from Global Exchanges</p>
    <div style="font-size: 0.75rem; color: var(--text-muted);">
        <span id="cmd-update-time"></span>
    </div>
</div>

{{-- Metals --}}
<div class="panel acrylic fade-in-up" style="--delay: 0.1s; margin-bottom: var(--space-4)">
    <div class="panel-header" style="border-bottom: 1px solid rgba(255,255,255,0.05)">
        <span class="panel-title">🥇 Precious & Industrial Metals</span>
    </div>
    <div class="panel-body">
        <div id="metals-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: var(--space-4);">
            <div style="color:var(--text-muted); padding:2rem; text-align:center; grid-column:1/-1">Loading metals...</div>
        </div>
    </div>
</div>

{{-- Energy --}}
<div class="panel acrylic fade-in-up" style="--delay: 0.2s; margin-bottom: var(--space-4)">
    <div class="panel-header" style="border-bottom: 1px solid rgba(255,255,255,0.05)">
        <span class="panel-title">🛢️ Energy Markets</span>
    </div>
    <div class="panel-body">
        <div id="energy-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: var(--space-4);">
            <div style="color:var(--text-muted); padding:2rem; text-align:center; grid-column:1/-1">Loading energy...</div>
        </div>
    </div>
</div>

{{-- Agriculture --}}
<div class="panel acrylic fade-in-up" style="--delay: 0.3s;">
    <div class="panel-header" style="border-bottom: 1px solid rgba(255,255,255,0.05)">
        <span class="panel-title">🌾 Agriculture</span>
    </div>
    <div class="panel-body">
        <div id="agri-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: var(--space-4);">
            <div style="color:var(--text-muted); padding:2rem; text-align:center; grid-column:1/-1">Loading agriculture...</div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    loadCommodities();
    setInterval(loadCommodities, 60000); // refresh every minute
});

function loadCommodities() {
    fetch('/api/market/commodities-extended')
        .then(r => r.json())
        .then(data => {
            if (!data || !data.length) return;
            document.getElementById('cmd-update-time').textContent = 'Live • ' + new Date().toLocaleTimeString();
            
            const metalsSymbols = ['XAU','XAG','COPPER','PLAT','PALL'];
            const energySymbols = ['WTI','BRENT','NGAS'];
            const agriSymbols   = ['WHEAT','CORN','SOY','COFFEE','SUGAR'];
            
            const metals = data.filter(c => metalsSymbols.includes(c.symbol));
            const energy = data.filter(c => energySymbols.includes(c.symbol));
            const agri   = data.filter(c => agriSymbols.includes(c.symbol));
            
            _renderGrid('metals-grid', metals);
            _renderGrid('energy-grid', energy);
            _renderGrid('agri-grid', agri);
        })
        .catch(console.error);
}

function _renderGrid(id, items) {
    const grid = document.getElementById(id);
    if (!items.length) {
        grid.innerHTML = '<div style="color:var(--text-muted)">No data available</div>';
        return;
    }
    
    grid.innerHTML = items.map(c => {
        const cls = c.change_pct >= 0 ? 'positive' : 'negative';
        const sign= c.change_pct >= 0 ? '+' : '';
        const price = c.price < 10 ? c.price.toFixed(4) : c.price.toFixed(2);
        const bgShadow = c.change_pct >= 0 ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)';
        const borderColor = c.change_pct >= 0 ? 'rgba(34, 197, 94, 0.3)' : 'rgba(239, 68, 68, 0.3)';
        
        let link = '#';
        if (c.symbol === 'XAU') link = '/trading?symbol=PAXGUSDT';
        // if wanted to link others, we'd create a specific view logic. For now just view only except gold.
        const onclickAttr = c.symbol === 'XAU' ? `onclick="window.location='${link}'" style="cursor:pointer"` : '';

        return `<div class="bento-card js-tilt" ${onclickAttr} style="background: ${bgShadow}; border: 1px solid ${borderColor}; padding: var(--space-4); padding-bottom: var(--space-5); overflow: visible;">
            <div style="display:flex; justify-content:space-between; margin-bottom: 8px;">
                <div>
                    <div style="font-weight:bold; font-size: 1.1rem; color: var(--text-primary); line-height: 1.3;">
                        ${c.name}
                    </div>
                    <div style="font-size: 0.7rem; color: var(--text-muted); line-height: 1.4;">
                        ${c.unit}
                    </div>
                </div>
            </div>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 12px; padding-bottom: 4px;">
                <div class="text-mono" style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); line-height: 1.3; overflow: visible;">
                    ${price}
                </div>
                <div class="text-mono ${cls}" style="font-size: 0.9rem; font-weight: 600; line-height: 1.4;">
                    ${sign}${c.change_pct.toFixed(2)}%
                </div>
            </div>
            
            <div style="margin-top: 12px; font-size: 0.7rem; color: var(--text-muted); display:flex; justify-content:space-between; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 8px;">
                <span>H: ${c.high_24h ? c.high_24h.toFixed(price<10?4:2) : '--'}</span>
                <span>L: ${c.low_24h ? c.low_24h.toFixed(price<10?4:2) : '--'}</span>
            </div>
            <div style="margin-top: 4px; font-size: 0.65rem; color: rgba(255,255,255,0.3); text-align:right;">
                src: ${c.source === 'yahoo_finance' ? 'Yahoo Finance API' : (c.source === 'okx' ? 'OKX API' : 'Binance Live')}
            </div>
        </div>`;
    }).join('');
    
    // Reinit tilt if defined globally
    if (typeof initTilt === 'function') initTilt();
}
</script>
@endsection
