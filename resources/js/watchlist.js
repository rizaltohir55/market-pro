/**
 * Watchlist & Ticker Bar Module
 * Handles category tabs (Crypto, Stocks, Forex, Commodities)
 * and rendering of the global ticker bar.
 */

export const Watchlist = {
    pairTabData: { crypto: null, stocks: null, forex: null, commodities: null },
    activeCategory: 'crypto',
    pairRefreshTimer: null,

    init() {
        this.initPairTabs();
        this.initTickerBar();

        // Listen for WebSocket updates
        window.addEventListener('market:ticker:update', (e) => {
            this.handleTickerUpdate(e.detail);
        });
    },

    initPairTabs() {
        const tabs = document.querySelectorAll('.pair-tab');
        if (!tabs.length) return;

        tabs.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget;
                tabs.forEach(b => b.classList.remove('active'));
                target.classList.add('active');

                this.activeCategory = target.dataset.cat;
                this.renderPairList(this.activeCategory);

                // Fetch fresh if > 2 min old or not loaded
                if (!this.pairTabData[this.activeCategory]) {
                    this.fetchPairCategory(this.activeCategory);
                }
            });
        });

        // Load crypto by default (this handles the UI initially)
        this.fetchPairCategory('crypto');

        // Silently preload other categories for global search autocomplete
        setTimeout(() => {
            this.fetchPairCategory('stocks');
            this.fetchPairCategory('forex');
            this.fetchPairCategory('commodities');
        }, 1000); // 1s delay to not block the main render

        // Auto-refresh active category every 2 minutes
        this.pairRefreshTimer = setInterval(() => {
            this.fetchPairCategory(this.activeCategory);
        }, 120000);
    },

    async fetchPairCategory(cat) {
        const urlMap = {
            crypto: '/api/market/top-pairs?limit=30',
            stocks: '/api/market/stocks',
            forex: '/api/market/forex',
            commodities: '/api/market/commodities',
        };

        try {
            const r = await fetch(urlMap[cat], { signal: AbortSignal.timeout(30000) });
            if (!r.ok) return;
            const data = await r.json();

            // Format Crypto pairs directly from API response to match Binance data structure
            if (cat === 'crypto' && Array.isArray(data)) {
                this.pairTabData[cat] = this.normalizeCryptoData(data);
                this.renderTickerBar(this.pairTabData[cat]);
            } else {
                this.pairTabData[cat] = data;
            }

            if (this.activeCategory === cat) this.renderPairList(cat);
        } catch (e) {
            console.warn('[Watchlist] fetch failed for', cat, e.message);
        }
    },

    renderPairList(cat) {
        const container = document.getElementById('watchlist-items');
        if (!container) return;

        const data = this.pairTabData[cat];
        if (!data) {
            container.innerHTML = '<div style="padding:12px;color:var(--text-muted);font-size:0.75rem;text-align:center">Loading...</div>';
            return;
        }

        if (!Array.isArray(data) && typeof data !== 'object') {
            container.innerHTML = '<div style="padding:12px;color:var(--text-muted);font-size:0.75rem;text-align:center">No data</div>';
            return;
        }

        let items = [];
        if (cat === 'crypto') items = this.renderCryptoPairs(data);
        else if (cat === 'stocks') items = this.renderStockPairs(Array.isArray(data) ? data : []);
        else if (cat === 'forex') items = this.renderForexPairs(data);
        else if (cat === 'commodities') items = this.renderCommodityPairs(Array.isArray(data) ? data : []);

        container.innerHTML = items.length
            ? items.join('')
            : '<div style="padding:12px;color:var(--text-muted);font-size:0.75rem;text-align:center">No data available</div>';
    },

    fmtPrice(p) {
        p = parseFloat(p);
        if (isNaN(p)) return '—';
        if (p >= 1000) return '$' + p.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (p >= 1) return '$' + p.toFixed(2);
        if (p >= 0.01) return '$' + p.toFixed(4);
        return '$' + p.toFixed(6);
    },

    fmtChg(c) {
        c = parseFloat(c);
        if (isNaN(c)) return '';
        return (c >= 0 ? '+' : '') + c.toFixed(2) + '%';
    },

    chgClass(c) {
        return parseFloat(c) >= 0 ? 'positive' : 'negative';
    },

    pairItem(symbol, name, price, change, onclick) {
        return `<div class="watchlist-item" onclick="${onclick}" title="${name}" data-symbol="${symbol}">
            <span class="wl-symbol">${symbol}</span>
            <span class="wl-name">${name}</span>
            <span class="wl-price" data-role="price">${this.fmtPrice(price)}</span>
            <span class="wl-change ${this.chgClass(change)}" data-role="change">${this.fmtChg(change)}</span>
        </div>`;
    },

    renderCryptoPairs(data) {
        return (data || [])
            .slice(0, 30)
            .map(p => {
                const nav = `window.location='/trading?symbol=${p.symbol}'`;
                // Derive a clean base name from the pair (e.g. "BTC" from "BTC/USDT")
                const baseName = (p.pair || '').split('/')[0] || p.pair;
                return this.pairItem(p.pair, baseName, p.price, p.change, nav);
            });
    },

    renderStockPairs(data) {
        return data.slice(0, 25).map(s => {
            const nav = `window.location='/trading?symbol=stock:${s.symbol}'`;
            return this.pairItem(s.symbol, s.name || s.symbol, s.price, s.change_pct, nav);
        });
    },

    renderForexPairs(data) {
        const rates = data.rates || {};
        const PAIRS = [
            { cur: 'EUR', label: 'EUR/USD', field: 'rate' },
            { cur: 'GBP', label: 'GBP/USD', field: 'rate' },
            { cur: 'JPY', label: 'USD/JPY', field: 'rate' },
            { cur: 'AUD', label: 'AUD/USD', field: 'rate' },
            { cur: 'CAD', label: 'USD/CAD', field: 'rate' },
            { cur: 'CHF', label: 'USD/CHF', field: 'rate' },
            { cur: 'CNY', label: 'USD/CNY', field: 'rate' },
            { cur: 'SGD', label: 'USD/SGD', field: 'rate' },
            { cur: 'HKD', label: 'USD/HKD', field: 'rate' },
            { cur: 'IDR', label: 'USD/IDR', field: 'rate' },
            { cur: 'MYR', label: 'USD/MYR', field: 'rate' },
            { cur: 'THB', label: 'USD/THB', field: 'rate' },
        ];
        return PAIRS.filter(p => rates[p.cur] && rates[p.cur][p.field] != null).map(({ cur, label, field }) => {
            const rateObj = rates[cur];
            const displayPrice = parseFloat(rateObj[field]);
            const decimals = (cur === 'JPY' || cur === 'IDR') ? 2 : 4;
            const priceStr = isNaN(displayPrice) ? '—' : displayPrice.toFixed(decimals);
            const nav = `window.location='/trading?symbol=forex:${cur}'`;
            return `<div class="watchlist-item" title="${label}" onclick="${nav}" data-symbol="${cur}">
                <span class="wl-symbol">${label}</span>
                <span class="wl-name" style="font-size:0.6rem">${cur}</span>
                <span class="wl-price" style="font-size:0.7rem" data-role="price">${priceStr}</span>
                <span class="wl-change" style="color:var(--text-muted)" data-role="change">—</span>
            </div>`;
        });
    },

    renderCommodityPairs(data) {
        return data.map(c => {
            const nav = `window.location='/trading?symbol=${c.symbol === 'XAU' ? 'PAXGUSDT' : c.symbol === 'BTC' ? 'BTCUSDT' : c.symbol}'`;
            return this.pairItem(c.symbol, c.name || c.symbol, c.price, c.change_pct, nav);
        });
    },

    normalizeCryptoData(data) {
        const skipBases = ['USDT', 'USDC', 'BUSD', 'FDUSD', 'TUSD', 'USD1', 'U'];
        return data.filter(p => {
            const b = (p.pair || p.symbol || '').replace('/USDT', '').replace('USDT', '');
            return b.length > 0 && !skipBases.includes(b);
        }).map(p => {
            let sym = p.pair || p.symbol || '';
            if (sym === 'PAXG/USDT' || sym === 'PAXGUSDT') sym = 'XAU/USDT';
            return {
                symbol: (p.symbol || sym).replace('/', ''), // No slash e.g. BTCUSDT
                pair: sym, // With slash e.g. BTC/USDT
                price: p.price || p.lastPrice || 0,
                change: p.change || p.priceChangePercent || 0
            };
        });
    },

    // ── TICKER BAR (Global) ──────────────────────────────────────────
    initTickerBar() {
        // Ticker bar is now populated via fetchPairCategory('crypto')
        // called during initPairTabs/init. No separate fetch needed.
        if (this.pairTabData.crypto) {
            this.renderTickerBar(this.pairTabData.crypto);
        }
    },

    renderTickerBar(pairs) {
        const track = document.getElementById('ticker-track');
        if (!track) return;
        let html = '';

        pairs.slice(0, 30).forEach(p => {
            const cls = p.change >= 0 ? 'positive' : 'negative';
            const priceStr = this.fmtPrice(p.price).replace('$', '');
            html += `<span class="ticker-item" data-symbol="${p.symbol}">
                <span class="symbol">${p.pair}</span>
                <span class="price" data-role="price">$${priceStr}</span>
                <span class="change ${cls}" data-role="change">${p.change >= 0 ? '▲' : '▼'}${Math.abs(p.change).toFixed(2)}%</span>
            </span>`;
        });

        track.innerHTML = html + html;
    },

    handleTickerUpdate(tickersArray) {
        // Update Watchlist Data
        if (this.pairTabData.crypto && Array.isArray(this.pairTabData.crypto)) {
            const dt = Date.now();
            let changed = false;

            this.pairTabData.crypto = this.pairTabData.crypto.map(p => {
                const tick = tickersArray.find(t => t.s === p.symbol);
                if (tick) {
                    changed = true;
                    // 'c' is close (last price), 'P' is price change percent (or we might get open 'o' to calc it)
                    // Note: !miniTicker@arr provides 'c' (Close/Price), 'o' (Open), 'h', 'l', 'v', 'q'
                    // Price change % = ((Close - Open) / Open) * 100
                    const price = parseFloat(tick.c);
                    const open = parseFloat(tick.o);
                    const chg = open > 0 ? ((price - open) / open) * 100 : p.change;

                    p.price = price;
                    p.change = chg;

                    // Update DOM element directly for performance to avoid re-rendering entire list constantly
                    if (this.activeCategory === 'crypto') {
                        this.updateWatchlistDOM(p.symbol, p.price, p.change, dt);
                    }
                    this.updateTickerBarDOM(p.symbol, p.price, p.change, dt);
                }
                return p;
            });

            if (changed && typeof window.updateLastUpdate === 'function') {
                window.updateLastUpdate();
            }
        }
    },

    updateWatchlistDOM(symbol, price, change, timestamp) {
        const item = document.querySelector(`#watchlist-items .watchlist-item[data-symbol="${symbol}"]`);
        if (!item) return;

        const prEl = item.querySelector('[data-role="price"]');
        const chEl = item.querySelector('[data-role="change"]');

        if (prEl) {
            prEl.textContent = this.fmtPrice(price);
        }
        if (chEl) {
            chEl.className = `wl-change ${this.chgClass(change)}`;
            chEl.textContent = this.fmtChg(change);
        }
    },

    updateTickerBarDOM(symbol, price, change, timestamp) {
        const items = document.querySelectorAll(`#ticker-track .ticker-item[data-symbol="${symbol}"]`);
        if (!items.length) return;

        const priceStr = this.fmtPrice(price).replace('$', '');
        const chgClass = this.chgClass(change);
        const chgStr = `${change >= 0 ? '▲' : '▼'}${Math.abs(change).toFixed(2)}%`;

        items.forEach(item => {
            const prEl = item.querySelector('[data-role="price"]');
            const chEl = item.querySelector('[data-role="change"]');

            if (prEl) {
                // Flash effect disabled for smooth scrolling, just update text
                prEl.textContent = '$' + priceStr;
            }
            if (chEl) {
                chEl.className = `change ${chgClass}`;
                chEl.textContent = chgStr;
            }
        });
    }
};

export default Watchlist;
