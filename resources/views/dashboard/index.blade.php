@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard Overview')

@section('content')
<div class="kpi-grid fade-in-up" id="kpi-grid" style="--delay: 0.1s;">
    @php
        $kpis = [
            ['label' => 'IHSG', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>', 'type' => 'stock', 'country' => 'ID'],
            ['label' => 'S&P 500 ETF', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>', 'type' => 'stock', 'country' => 'US'],
            ['label' => 'NASDAQ', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>', 'type' => 'stock', 'country' => null],
            ['label' => 'BTC/USDT', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9 8h4a2 2 0 0 1 0 4H9z"/><path d="M9 12h4.5a2 2 0 0 1 0 4H9z"/><path d="M11 6v2"/><path d="M11 16v2"/><path d="M13 6v2"/><path d="M13 16v2"/></svg>', 'type' => 'crypto', 'country' => null],
            ['label' => 'GOLD (XAU)', 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>', 'type' => 'crypto', 'country' => null],
        ];
    @endphp

    @foreach($kpis as $i => $kpi)
    <div class="kpi-card js-tilt acrylic" id="kpi-card-{{ $i }}">
        <div class="kpi-header">
            <span class="kpi-label">{{ $kpi['label'] }}</span>
            <span class="kpi-icon" style="display:inline-flex;align-items:center;gap:4px;color:var(--accent);filter:drop-shadow(0 0 6px var(--accent-glow));">
                @if($kpi['country'])
                    <span style="font-family:var(--font-mono);font-size:0.7rem;font-weight:700;color:var(--text-muted);letter-spacing:0.05em;">{{ $kpi['country'] }}</span>
                @endif
                <span style="display:inline-block; width:18px; height:18px;">
                    {!! str_replace('<svg ', '<svg width="18" height="18" ', $kpi['svg']) !!}
                </span>
            </span>
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
            <button aria-label="Refresh Market Overview" class="btn btn-ghost btn-sm btn-sweep" onclick="location.reload()">
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


