<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MarketPro — Bloomberg Terminal-style crypto trading dashboard with real-time monitoring, technical analysis, and scalping predictions">
    <title>MarketPro | @yield('title', 'Dashboard')</title>

    {{-- Google Fonts (Cyber Professional) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- TradingView Lightweight Charts --}}
    <script src="https://unpkg.com/lightweight-charts@4.1.1/dist/lightweight-charts.standalone.production.js"></script>
    <script src="/js/ta-math.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/svg+xml" href="/images/logo.svg">

    <style>
        [v-cloak] { display: none !important; }
    </style>
</head>
<body>
    <div class="app-layout" id="app">
        {{-- ═══ TICKER BAR ═══ --}}
        <div class="layout-ticker-area">
            <div class="ticker-bar ticker-bar--flush" id="ticker-bar">
                <div class="ticker-track" id="ticker-track">
                    {{-- Populated by JS --}}
                    <span class="ticker-item ticker-item--muted">Loading market data...</span>
                </div>
            </div>

            {{-- ═══ BREAKING NEWS TICKER (Global) ═══ --}}
            <div class="news-ticker-container news-ticker-container--flush fade-in-down" id="global-news-ticker" style="--delay: 0.02s;">
                <div class="news-ticker-label">BREAKING</div>
                <div class="news-ticker-content" id="breaking-ticker-global">
                    <div class="ticker-scroll" id="ticker-scroll-global">
                        <span class="ticker-item--muted">Loading latest headlines...</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ SIDEBAR ═══ --}}
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <span class="sidebar-logo">
                    <svg class="logo-icon" width="24" height="24" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="logo-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:var(--accent);stop-opacity:1" />
                                <stop offset="100%" style="stop-color:var(--accent2);stop-opacity:1" />
                            </linearGradient>
                            <filter id="glow">
                                <feGaussianBlur stdDeviation="1.5" result="coloredBlur"/>
                                <feMerge>
                                    <feMergeNode in="coloredBlur"/>
                                    <feMergeNode in="SourceGraphic"/>
                                </feMerge>
                            </filter>
                        </defs>
                        <path d="M20 2L36 11V29L20 38L4 29V11L20 2Z" stroke="url(#logo-grad)" stroke-width="2" stroke-linejoin="round" fill="rgba(0, 240, 255, 0.05)" />
                        <path d="M10 28V16L20 25L30 14" stroke="url(#logo-grad)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" filter="url(#glow)" />
                        <circle cx="10" cy="28" r="1.5" fill="var(--accent)" />
                        <circle cx="30" cy="14" r="1.5" fill="var(--accent2)" />
                    </svg>
                    <span>MARKET<span class="logo-pro">PRO</span></span>
                </span>
                <button aria-label="Close Sidebar" class="btn btn-ghost btn-icon sidebar-close-btn" id="sidebar-close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>

            <div class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="/" class="nav-item {{ request()->is('/') ? 'active' : '' }}" id="nav-dashboard">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        <span>Dashboard</span>
                    </a>
                    <a href="/trading" class="nav-item {{ request()->is('trading*') ? 'active' : '' }}" id="nav-trading">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
                        <span>Trading</span>
                    </a>
                    <a href="/scanner" class="nav-item {{ request()->is('scanner*') ? 'active' : '' }}" id="nav-scanner">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <span>Scanner</span>
                    </a>
                    <a href="/analysis" class="nav-item {{ request()->is('analysis*') ? 'active' : '' }}" id="nav-analysis">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                        <span>Analysis</span>
                    </a>
                    <a href="/settings" class="nav-item {{ request()->is('settings*') ? 'active' : '' }}" id="nav-settings">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        <span>Settings</span>
                    </a>
                </div>

                <div class="nav-section nav-section--markets">
                    <div class="nav-section-title">Markets</div>
                    <a href="/equity" class="nav-item {{ request()->is('equity*') ? 'active' : '' }}" id="nav-equity">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
                        <span>Equity Analytics</span>
                    </a>
                    <a href="/fx-rates" class="nav-item {{ request()->is('fx-rates*') ? 'active' : '' }}" id="nav-fx">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9 9h.01"></path><path d="M15 9h.01"></path><path d="M8 13s1.5 2 4 2 4-2 4-2"></path><line x1="8" y1="9" x2="16" y2="15"></line><line x1="16" y1="9" x2="8" y2="15"></line></svg>
                        <span>FX & Rates</span>
                    </a>
                    <a href="/derivatives" class="nav-item {{ request()->is('derivatives*') ? 'active' : '' }}" id="nav-deriv">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                        <span>Derivatives</span>
                    </a>
                    <a href="/commodities" class="nav-item {{ request()->is('commodities*') ? 'active' : '' }}" id="nav-commod">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                        <span>Commodities</span>
                    </a>
                    <a href="/news" class="nav-item {{ request()->is('news*') ? 'active' : '' }}" id="nav-news">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"></path><path d="M18 14h-8"></path><path d="M15 18h-5"></path><path d="M10 6h8v4h-8V6Z"></path></svg>
                        <span>News & Media</span>
                    </a>
                </div>
            </div>

            {{-- Watchlist — Collapsible Pairs --}}
            @php
                $watchlistOpen = !request()->is('/') && !request()->is('scanner*') && !request()->routeIs('dashboard');
            @endphp
            <details class="watchlist watchlist-details" id="watchlist" {{ $watchlistOpen ? 'open' : '' }}>
                <summary class="watchlist-title watchlist-title--row watchlist-summary">
                    <span class="wl-summary-text">
                        <svg class="wl-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        Pairs
                    </span>
                    <span class="wl-live-dot" id="wl-live-dot"></span>
                </summary>

                {{-- Category Tab Bar --}}
                <div class="pair-tabs-bar" id="pair-tabs">
                    <button class="pair-tab active" data-cat="crypto">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.5 9.5c.4-1 1.3-1.5 2.5-1.5 1.7 0 2.5.9 2.5 2 0 1.5-1.5 2-2.5 2.5V14"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Crypto
                    </button>
                    <button class="pair-tab" data-cat="stocks">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                        Stocks
                    </button>
                    <button class="pair-tab" data-cat="forex">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
                        Forex
                    </button>
                    <button class="pair-tab" data-cat="commodities">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                        Commod
                    </button>
                </div>

                {{-- Pair Lists --}}
                <div class="watchlist-items-list" id="watchlist-items">
                    <div class="wl-loading wl-loading--center">Loading...</div>
                </div>
            </details>


        </nav>

        {{-- ═══ TOPBAR ═══ --}}
        <header class="topbar">
            <div class="topbar-left">
                <button aria-label="Toggle Menu" class="btn btn-ghost btn-icon menu-toggle menu-toggle-btn" id="menu-toggle">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
                <div class="topbar-title-group">
                    <h1 class="topbar-title">@yield('page-title', 'Dashboard Overview')</h1>
                </div>

                @php
                    $showSearch = request()->is('/') || 
                                 request()->routeIs('dashboard') ||
                                 request()->is('trading*') || 
                                 request()->is('analysis*') || 
                                 request()->is('equity*') || 
                                 request()->is('fx-rates*') || 
                                 request()->is('derivatives*') || 
                                 request()->is('commodities*');
                @endphp

                @if($showSearch)
                <div id="topbar-search-container" class="topbar-command-bar acrylic" style="border-radius: 999px !important;">
                    <div class="search-box" id="search-box">
                        <svg class="search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" placeholder="SEARCH..." id="search-input" spellcheck="false" autocomplete="off">
                    </div>
                </div>
                @endif
            </div>
            
            <div class="topbar-right">
                <div hidden>
                    <span id="conn-status"></span>
                    <span id="conn-dot"></span>
                    <span id="conn-text"></span>
                    <span id="status-clock"></span>
                    <span id="status-last-update"></span>
                </div>
            </div>
        </header>

        {{-- ═══ MAIN CONTENT ═══ --}}
        <main class="main-content" id="main-content">
            @yield('content')
        </main>
        
        {{-- Hidden status variables for JS compatibility --}}
        <div hidden>
            <span id="ws-status-text"></span>
            <span id="ws-status-dot"></span>
            <span id="status-api">API: Binance Public</span>
        </div>

        {{-- ═══ SINGLE BOTTOM-RIGHT CONNECTION DOT ═══ --}}
        <div id="conn-status-dot-wrap" style="position:fixed;bottom:14px;right:14px;z-index:9999;width:10px;height:10px;" title="Connection status">
            <span id="global-status-dot" style="display:block;width:10px;height:10px;border-radius:50%;background:var(--success);box-shadow:0 0 6px var(--success);transition:background 0.4s,box-shadow 0.4s;"></span>
        </div>
    </div>

    @yield('scripts')

    <script>
        // ─── STATUS CLOCK ────────────────────────────────────────────
        function updateClock() {
            const now = new Date();
            document.getElementById('status-clock').textContent = now.toLocaleTimeString('en-US', { hour12: false });
        }
        setInterval(updateClock, 1000);
        updateClock();

        window.updateLastUpdate = function() {
            const el = document.getElementById('status-last-update');
            if (el) el.textContent = 'Updated: ' + new Date().toLocaleTimeString('en-US', { hour12: false });
        };

        // ─── PAIR SELECTOR (topbar) ──────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            const sel = document.getElementById('pair-selector');
            if (sel) {
                const urlParams = new URLSearchParams(window.location.search);
                const currentSymbol = urlParams.get('symbol') || 'BTCUSDT';
                sel.value = currentSymbol;
                sel.addEventListener('change', function() {
                    const val = this.value;
                    window.location.href = '/trading?symbol=' + val;
                });
            }

            // Mobile Menu
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar    = document.getElementById('sidebar');
            const sidebarClose = document.getElementById('sidebar-close');
            if (menuToggle && sidebar) menuToggle.addEventListener('click', () => sidebar.classList.add('mobile-open'));
            if (sidebarClose && sidebar) sidebarClose.addEventListener('click', () => sidebar.classList.remove('mobile-open'));
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('mobile-open')) {
                    if (!sidebar.contains(e.target) && menuToggle && !menuToggle.contains(e.target))
                        sidebar.classList.remove('mobile-open');
                }
            });
        });

        // initGlobalNewsTicker() is called by app.js — no duplicate call needed here.
    </script>

</body>
</html>
