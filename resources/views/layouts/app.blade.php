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
                    <svg class="logo-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
                    <span>MARKET<span class="logo-pro">PRO</span></span>
                </span>
                <button class="btn btn-ghost btn-icon sidebar-close-btn" id="sidebar-close">
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
                        <span class="nav-emoji">📊</span>
                        <span>Equity Analytics</span>
                    </a>
                    <a href="/fx-rates" class="nav-item {{ request()->is('fx-rates*') ? 'active' : '' }}" id="nav-fx">
                        <span class="nav-emoji">💱</span>
                        <span>FX & Rates</span>
                    </a>
                    <a href="/derivatives" class="nav-item {{ request()->is('derivatives*') ? 'active' : '' }}" id="nav-deriv">
                        <span class="nav-emoji">🎯</span>
                        <span>Derivatives</span>
                    </a>
                    <a href="/commodities" class="nav-item {{ request()->is('commodities*') ? 'active' : '' }}" id="nav-commod">
                        <span class="nav-emoji">🔶</span>
                        <span>Commodities</span>
                    </a>
                    <a href="/news" class="nav-item {{ request()->is('news*') ? 'active' : '' }}" id="nav-news">
                        <span class="nav-emoji">📰</span>
                        <span>News & Media</span>
                    </a>
                </div>
            </div>

            {{-- Watchlist — Category Tabs --}}
            <div class="watchlist watchlist--flex" id="watchlist">
                <div class="watchlist-title watchlist-title--row">
                    <span>Pairs</span>
                    <span class="wl-live-dot" id="wl-live-dot"></span>
                </div>

                {{-- Category Tab Bar --}}
                <div class="pair-tabs-bar" id="pair-tabs">
                    <button class="pair-tab active" data-cat="crypto">🪙 Crypto</button>
                    <button class="pair-tab" data-cat="stocks">📈 Stocks</button>
                    <button class="pair-tab" data-cat="forex">💱 Forex</button>
                    <button class="pair-tab" data-cat="commodities">🔶 Commod</button>
                </div>

                {{-- Pair Lists --}}
                <div class="watchlist-items-list" id="watchlist-items">
                    <div class="wl-loading wl-loading--center">Loading...</div>
                </div>
            </div>


        </nav>

        {{-- ═══ TOPBAR ═══ --}}
        <header class="topbar">
            <div class="topbar-left">
                <button class="btn btn-ghost btn-icon menu-toggle menu-toggle-btn" id="menu-toggle">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
                <h1 class="topbar-title">@yield('page-title', 'Dashboard Overview')</h1>
            </div>
            
            <div class="topbar-pill acrylic">
                <div class="search-box" id="search-box">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" placeholder="Search pairs... (Ctrl+K)" id="search-input">
                </div>
                <select class="pair-selector" id="pair-selector">
                    <optgroup label="── Crypto ──">
                        <option value="BTCUSDT">BTC/USDT</option>
                        <option value="ETHUSDT">ETH/USDT</option>
                        <option value="SOLUSDT">SOL/USDT</option>
                        <option value="BNBUSDT">BNB/USDT</option>
                        <option value="XRPUSDT">XRP/USDT</option>
                        <option value="ADAUSDT">ADA/USDT</option>
                        <option value="DOGEUSDT">DOGE/USDT</option>
                        <option value="AVAXUSDT">AVAX/USDT</option>
                    </optgroup>
                    <optgroup label="── Commodities ──">
                        <option value="PAXGUSDT">XAU/USD (Gold)</option>
                    </optgroup>
                    <optgroup label="── Stocks (View Only) ──">
                        <option value="stock:AAPL">AAPL — Apple</option>
                        <option value="stock:MSFT">MSFT — Microsoft</option>
                        <option value="stock:NVDA">NVDA — Nvidia</option>
                        <option value="stock:GOOGL">GOOGL — Alphabet</option>
                        <option value="stock:AMZN">AMZN — Amazon</option>
                        <option value="stock:TSLA">TSLA — Tesla</option>
                    </optgroup>
                    <optgroup label="── Forex (View Only) ──">
                        <option value="forex:EUR">EUR/USD</option>
                        <option value="forex:GBP">GBP/USD</option>
                        <option value="forex:JPY">USD/JPY</option>
                        <option value="forex:AUD">AUD/USD</option>
                    </optgroup>
                </select>
                
            </div>
        </header>

        {{-- ═══ MAIN CONTENT ═══ --}}
        <main class="main-content" id="main-content">
            @yield('content')
        </main>
        
        {{-- Floating Status Dock (Bottom Right) --}}
        <div class="floating-status-dock acrylic fade-in-up" style="--delay: 0.5s;">
            <div class="conn-status conn-status--inline" id="conn-status">
                <span class="conn-dot" id="conn-dot"></span>
                <span class="conn-text" id="conn-text">Connecting...</span>
            </div>

            <div class="status-indicator">
                <span class="ws-dot" id="ws-status-dot"></span>
                <span class="ws-status-text" id="ws-status-text"></span>
            </div>

            <div class="status-divider"></div>

            <div class="status-clock-group">
                <span class="status-clock" id="status-clock"></span>
                <span class="status-last-update" id="status-last-update"></span>
            </div>
        </div>
        {{-- Hidden status variables for JS compatibility --}}
        <div hidden>
            <span id="status-api">API: Binance Public</span>
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
                    if (val.startsWith('stock:') || val.startsWith('forex:')) {
                        window.location.href = '/asset?symbol=' + val;
                        return;
                    }
                    window.location.href = '/asset?symbol=' + val;
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
