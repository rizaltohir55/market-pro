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

<div class="dashboard-layout-grid" style="display: grid; grid-template-columns: 1fr 340px; gap: var(--space-4); margin-top: var(--space-4); align-items: start;">
    <div class="dashboard-main-col" style="display: flex; flex-direction: column; gap: var(--space-4);">
        
        <div class="panel acrylic fade-in-up" style="--delay: 0.15s;">
            <div class="panel-header">
                <span class="panel-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="2"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    Global Market Pulse — Bond Yields & Commodities
                </span>
            </div>
            <div class="panel-body" style="padding: 15px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <div class="text-caption" style="margin-bottom: 12px; display: flex; align-items: center; gap: 6px; color: var(--text-muted);">
                            <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            US TREASURY YIELDS
                        </div>
                        <div id="bond-yields-list" style="display: flex; flex-direction: column; gap: 8px;">
                            @for($i=0; $i<4; $i++)
                            <div class="skeleton" style="height: 32px; border-radius: 4px; background: rgba(255,255,255,0.03);"></div>
                            @endfor
                        </div>
                    </div>
                    
                    <div>
                        <div class="text-caption" style="margin-bottom: 12px; display: flex; align-items: center; gap: 6px; color: var(--text-muted);">
                            <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                            COMMODITIES INDEX
                        </div>
                        <div id="commodities-list" style="display: flex; flex-direction: column; gap: 8px;">
                            @for($i=0; $i<4; $i++)
                            <div class="skeleton" style="height: 32px; border-radius: 4px; background: rgba(255,255,255,0.03);"></div>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-3 fade-in-up" style="--delay: 0.2s; align-items:start; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-4);">
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

            <div class="panel acrylic">
                <div class="panel-header" style="border-bottom:none; padding-bottom:0">
                    <span class="panel-title" style="text-shadow: 0 0 10px rgba(0, 170, 255, 0.3)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="2" style="filter: drop-shadow(0 0 5px var(--info))"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        Most Active
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

        <div class="panel acrylic fade-in-up" style="--delay: 0.3s;">
            <div class="panel-header">
                <span class="panel-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Market Overview & AI Smart Signals
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
                            <th>Aset</th>
                            <th class="align-right">Harga</th>
                            <th class="align-right">Perubahan 24j</th>
                            <th class="align-center" style="color:var(--accent);">🤖 Sinyal AI</th>
                            <th class="align-center">🎯 Area Target</th>
                            <th class="align-right">Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for($i=0; $i<10; $i++)
                        <tr>
                            <td colspan="7"><div class="skeleton" style="height: 30px; background: rgba(255,255,255,0.02); width: 100%;"></div></td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="dashboard-sidebar-col" style="display: flex; flex-direction: column; gap: var(--space-4);">
        <div class="panel acrylic fade-in-up" style="--delay: 0.2s;">
            <div class="panel-header">
                <span class="panel-title" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" style="filter: drop-shadow(0 0 5px var(--danger))"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33 2.78 2.78 0 0 0 1.94 2c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0-1.94-2 29 29 0 0 0 .46-5.33 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/></svg>
                    Live Financial News
                </span>
                <div class="panel-actions">
                    <span class="live-indicator" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.70rem; color: var(--danger); font-weight: 700; text-transform: uppercase; animation: pulse 2s infinite;"><div style="width: 6px; height: 6px; border-radius: 50%; background: var(--danger);"></div> LIVE</span>
                </div>
            </div>
            <div class="panel-body no-padding" style="display: flex; flex-direction: column;">
                <div style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.2);">
                    <select id="live-tv-channel" class="form-control" style="width: 100%; border-radius: var(--radius-md); font-family: var(--font-sans); font-size: 0.85rem; background-color: rgba(255,255,255,0.05); color: var(--text-main); border: 1px solid rgba(255,255,255,0.1); padding: 8px 12px; cursor: pointer; appearance: auto;" onchange="changeLiveTvChannel()">
                        <option value="iEpJwprxDdk">Bloomberg Television</option>
                        <option value="KQp-e_XQnDE">Yahoo Finance</option>
                        <option value="XWq5kBlakcQ">CNA</option>
                        <option value="LuKwFajn37U">DW News</option>
                    </select>
                </div>
                <div class="youtube-player-wrapper" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; background: #0b0b0f; border-bottom-left-radius: var(--radius-lg); border-bottom-right-radius: var(--radius-lg);">
                    <iframe id="live-tv-iframe" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" src="https://www.youtube.com/embed/iEpJwprxDdk?autoplay=1&mute=1" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            </div>
        </div>

        <div class="panel acrylic fade-in-up" style="--delay: 0.3s; flex-shrink: 0;">
            <div class="panel-header">
                <span class="panel-title" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Upcoming Events
                </span>
            </div>
            <div class="panel-body no-padding">
                <div id="economic-calendar-list" style="display: flex; flex-direction: column; max-height: 420px; overflow-y: auto;">
                    @for($i=0; $i<5; $i++)
                    <div class="skeleton" style="height: 60px; margin: 10px; border-radius: 6px; background: rgba(255,255,255,0.03);"></div>
                    @endfor
                </div>
            </div>
            <div class="panel-secondary" style="padding: 10px; border-top: 1px solid rgba(255,255,255,0.05); text-align: center; background: rgba(0,0,0,0.2);">
                <a href="{{ route('news') }}" style="font-size: 0.75rem; color: var(--accent); text-decoration: none; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Full Calendar &rarr;</a>
            </div>
        </div>
    </div>
</div>

<script>
    function changeLiveTvChannel() {
        var videoId = document.getElementById('live-tv-channel').value;
        var iframe = document.getElementById('live-tv-iframe');
        iframe.src = "https://www.youtube.com/embed/" + videoId + "?autoplay=1&mute=1";
    }
    
    fetch('/api/market/dashboard/global-overview')
        .then(response => response.json())
        .then(data => {
            updateGlobalOverview(data);
        });

    function updateGlobalOverview(data) {
        const bondList = document.getElementById('bond-yields-list');
        const commList = document.getElementById('commodities-list');

        if (data.bonds && data.bonds.yields && data.bonds.yields.length > 0) {
            bondList.innerHTML = data.bonds.yields.map(b => `
                <div style="display:flex; justify-content:space-between; align-items:center; padding: 6px 10px; background: rgba(255,255,255,0.02); border-radius: 4px; font-family: var(--font-mono); font-size: 0.8rem;">
                    <span style="color: var(--text-secondary)">${b.type}</span>
                    <span style="color: var(--info); font-weight: 700;">${b.rate.toFixed(3)}%</span>
                </div>
            `).join('');
        } else {
            bondList.innerHTML = '<div style="padding: 10px; text-align: center; color: var(--text-muted); font-size: 0.75rem;">Yield data unavailable</div>';
        }

        if (data.commodities && data.commodities.length > 0) {
            commList.innerHTML = data.commodities.slice(0, 4).map(c => {
                const change = parseFloat(c.change_pct);
                const color = change >= 0 ? 'var(--success)' : 'var(--danger)';
                return `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding: 6px 10px; background: rgba(255,255,255,0.02); border-radius: 4px; font-family: var(--font-mono); font-size: 0.8rem;">
                        <span style="color: var(--text-secondary)">${c.name}</span>
                        <span style="color: ${color}; font-weight: 700;">${parseFloat(c.price).toLocaleString()}</span>
                    </div>
                `;
            }).join('');
        } else {
            commList.innerHTML = '<div style="padding: 10px; text-align: center; color: var(--text-muted); font-size: 0.75rem;">Commodity data unavailable</div>';
        }
    }

    fetch('/api/market/economic-calendar')
        .then(response => response.json())
        .then(data => {
            renderEconomicCalendar(data);
        });

    function renderEconomicCalendar(events) {
        const list = document.getElementById('economic-calendar-list');
        if (!events || events.length === 0) {
            list.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.8rem;">Tidak ada jadwal event</div>';
            return;
        }

        const filtered = events.filter(e => e.importance !== 'low').slice(0, 8);

        list.innerHTML = filtered.map(e => {
            const importanceColor = e.importance === 'high' ? 'var(--danger)' : (e.importance === 'medium' ? 'var(--warning)' : 'var(--info)');
            const statusLabel = e.actual ? 'COMPLETED' : 'UPCOMING';
            const statusColor = e.actual ? 'var(--success)' : 'var(--text-muted)';
            
            return `
                <div style="padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,0.03); display: flex; flex-direction: column; gap: 6px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.65rem; font-weight: 800; color: ${importanceColor}; text-transform: uppercase;">
                            ${e.currency} • ${e.importance} IMPACT
                        </span>
                        <span style="font-size: 0.65rem; color: var(--text-muted); font-family: var(--font-mono);">${e.time || e.date}</span>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-main); font-weight: 500; line-height: 1.3;">
                        ${e.event}
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; gap: 10px; font-size: 0.7rem; color: var(--text-muted); font-family: var(--font-mono);">
                            <span>F: <b style="color:var(--text-secondary)">${e.forecast || '--'}</b></span>
                            <span>P: <b style="color:var(--text-secondary)">${e.previous || '--'}</b></span>
                        </div>
                        <span style="font-size: 0.6rem; font-weight: 700; color: ${statusColor}; letter-spacing: 0.02em;">${statusLabel}</span>
                    </div>
                </div>
            `;
        }).join('');
    }
</script>

<style>
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.4; }
        100% { opacity: 1; }
    }
    
    @media (max-width: 1200px) {
        .dashboard-layout-grid {
            grid-template-columns: 1fr !important;
        }
    }

    .badge-buy { background: rgba(0, 255, 136, 0.15); color: var(--success); border: 1px solid rgba(0, 255, 136, 0.3); padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; display: inline-block; }
    .badge-sell { background: rgba(255, 51, 102, 0.15); color: var(--danger); border: 1px solid rgba(255, 51, 102, 0.3); padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; display: inline-block; }
    .badge-neutral { background: rgba(255, 255, 255, 0.05); color: var(--text-muted); border: 1px solid rgba(255, 255, 255, 0.1); padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; display: inline-block; }
</style>
@endsection