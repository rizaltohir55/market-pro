/**
 * Dashboard Module
 * Handles 3D tilt effects, data loading, rendering KPIs, heatmaps, and SSE updates for the dashboard.
 */

export const Dashboard = {
    mixedTickerData: [],

    init() {
        if (!document.getElementById('kpi-grid')) return; // Only run on dashboard page

        this.initTilt();
        this.loadDashboardData();
        this.initWebSockets();
    },

    initTilt() {
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
    },

    async loadDashboardData() {
        // 1. Load Market Summary (Crypto + Ticker Base)
        fetch('/api/market/dashboard/market-summary')
            .then(r => r.json())
            .then(data => {
                if (data.topPairs) {
                    this.renderMarketTable(data.topPairs);

                    // Build initial mixed ticker data from crypto
                    data.topPairs.slice(0, 10).forEach(p => {
                        this.mixedTickerData.push({ symbol: p.pair, display: p.pair, price: p.price, change: p.change, type: 'crypto' });
                    });
                    this.renderMixedTicker();
                }
            })
            .catch(e => console.error('Failed to load market summary:', e));

        // 2. Load Stock Summary (Stocks + Heatmap + KPIs)
        fetch('/api/market/dashboard/stock-summary')
            .then(r => r.json())
            .then(data => {
                if (data.stocks) {
                    this.renderKPIs(data);
                    this.renderHeatmap(data.stocks);
                    this.renderStockMovers(data);

                    // Add stocks to mixed ticker
                    data.stocks.slice(0, 7).forEach(s => {
                        this.mixedTickerData.push({ symbol: 'stock:' + s.symbol, display: s.symbol, price: s.price, change: s.change_pct, type: 'stock' });
                    });
                    this.renderMixedTicker();
                }
            })
            .catch(e => console.error('Failed to load stock summary:', e));

        // 3. Load Global Rates (Forex + Commodities)
        fetch('/api/market/dashboard/global-rates')
            .then(r => r.json())
            .then(data => {
                if (data.forex) {
                    // Add forex to mixed ticker
                    const forexRates = data.forex.rates || {};
                    ['EUR', 'GBP', 'JPY', 'AUD'].forEach(cur => {
                        if (forexRates[cur]) {
                            const isInverse = ['JPY', 'CAD', 'CHF', 'CNY', 'SGD', 'HKD', 'IDR', 'MYR', 'THB'].includes(cur);
                            const rate = isInverse ? (forexRates[cur].rate_inverse || 1) : (forexRates[cur].rate || 1);
                            const display = isInverse ? `USD/${cur}` : `${cur}/USD`;
                            this.mixedTickerData.push({ symbol: 'forex:' + cur, display: display, price: rate, change: 0, type: 'forex' });
                        }
                    });
                }

                if (data.commodities) {
                    // Add commodities to mixed ticker
                    data.commodities.forEach(c => {
                        this.mixedTickerData.push({ symbol: 'commodity:' + c.symbol, display: c.name || c.symbol, price: c.price, change: c.change_pct, type: 'commodity' });
                    });
                }

                this.renderMixedTicker();
            })
            .catch(e => console.error('Failed to load global rates:', e));
    },

    renderKPIs(data) {
        if (!data.indices) return;

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
            const labelEl = card.querySelector('.kpi-label');
            const iconEl = card.querySelector('.kpi-icon');

            if (!labelEl || !iconEl) return;

            const label = labelEl.textContent.trim();
            const prefix = (isStock && label.includes('IHSG')) ? 'Rp' : '$';

            card.innerHTML = `
                <div class="kpi-header">
                    <span class="kpi-label">${label}</span>
                    ${iconEl.outerHTML}
                </div>
                <div class="kpi-value text-mono">${prefix}${price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                <div class="kpi-delta ${change >= 0 ? 'positive' : 'negative'}">
                    ${change >= 0 ? '▲' : '▼'}
                    ${Math.abs(change).toFixed(2)}%
                    <span style="opacity:0.7;margin-left:4px">24h</span>
                </div>
            `;

            card.onclick = () => window.location = `/trading?symbol=${isStock ? 'stock:' + d.symbol : d.symbol}`;
        });
        this.initTilt();
    },

    renderHeatmap(stocks) {
        const grid = document.getElementById('heatmap-grid');
        if (!grid) return;

        const sorted = [...stocks].sort((a, b) => b.volume - a.volume).slice(0, 15);
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
    },

    renderStockMovers(data) {
        const renderList = (id, list, type) => {
            if (!list || !Array.isArray(list)) return;
            const el = document.getElementById(id);
            if (!el) return;
            el.innerHTML = list.map(item => `
                <div class="watchlist-item" onclick="window.location='/trading?symbol=stock:${item.symbol}'" style="background: rgba(${type === 'gain' ? '0,255,136' : '255,51,102'},0.05); padding: 8px 12px; border-radius: 6px;">
                    <div style="flex:1">
                        <div style="font-weight:700; color: var(--text-primary);">${item.symbol}</div>
                        <div style="font-size:0.7rem; color: var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: 100px;">${item.name}</div>
                    </div>
                    <div class="text-mono" style="text-align:right">
                        <div style="color: var(--text-primary); font-size: 1rem;">$${item.price.toFixed(2)}</div>
                        <div style="font-size: 0.75rem; color: var(--${type === 'gain' ? 'success' : 'danger'});">${item.change_pct >= 0 ? '+' : ''}${item.change_pct.toFixed(2)}%</div>
                    </div>
                </div>
            `).join('');
        };

        renderList('gainers-tbody', data.stockGainers, 'gain');
        renderList('losers-tbody', data.stockLosers, 'loss');

        const activeEl = document.getElementById('active-tbody');
        if (activeEl && data.activeStocks) {
            activeEl.innerHTML = data.activeStocks.slice(0, 10).map(a => `
                <div class="watchlist-item" onclick="window.location='/trading?symbol=stock:${a.symbol}'" style="background: rgba(0,170,255,0.05); padding: 8px 12px; border-radius: 6px;">
                    <div style="flex:1">
                        <div style="font-weight:700; color: var(--text-primary);">${a.symbol}</div>
                        <div style="font-size:0.7rem; color: var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: 100px;">${a.name}</div>
                    </div>
                    <div class="text-mono" style="text-align:right">
                        <div style="color: var(--text-primary); font-size: 0.9rem;">Vol: ${(a.volume / 1000000).toFixed(1)}M</div>
                        <div style="font-size: 0.75rem; color: ${a.change_pct >= 0 ? 'var(--success)' : 'var(--danger)'};">${a.change_pct >= 0 ? '+' : ''}${a.change_pct.toFixed(2)}%</div>
                    </div>
                </div>
            `).join('');
        }
    },

    renderMarketTable(pairs) {
        const tbody = document.querySelector('#market-table tbody');
        if (!tbody) return;
        tbody.innerHTML = pairs.map((p, i) => {
            const cls = p.change >= 0 ? 'positive' : 'negative';
            const vol = p.quoteVolume >= 1e9 ? (p.quoteVolume / 1e9).toFixed(2) + 'B' : (p.quoteVolume / 1e6).toFixed(1) + 'M';
            return `<tr onclick="window.location='/trading?symbol=${p.symbol}'" style="cursor:pointer">
                <td style="color:var(--text-muted)">${i + 1}</td>
                <td style="font-weight:600">${p.pair}</td>
                <td class="align-right">$${this.formatPrice(p.price)}</td>
                <td class="align-right ${cls}">${p.change >= 0 ? '+' : ''}${p.change.toFixed(2)}%</td>
                <td class="align-right">$${this.formatPrice(p.high || 0)}</td>
                <td class="align-right">$${this.formatPrice(p.low || 0)}</td>
                <td class="align-right">${vol}</td>
                <td class="align-right">${(p.trades || 0).toLocaleString()}</td>
            </tr>`;
        }).join('');
    },

    // initSSE is removed in favor of WebSockets.

    initWebSockets() {
        if (!window.Echo) {
            console.error('Laravel Echo not found. WebSocket updates disabled.');
            return;
        }

        console.info('[Dashboard] Subscribing to market.all WebSocket channel...');
        
        window.Echo.channel('market.all')
            .listen('.updated', (e) => {
                const data = e.data;
                console.debug('[WebSocket] Market Update Received:', data);

                if (data.dashboard && data.dashboard.market) {
                    this.renderMarketTable(data.dashboard.market);
                    this.updateTickerPrices(data.dashboard.market);
                }
                
                if (data.watchlist) {
                    this.updateKPIPrices(data.watchlist);
                }
            });
    },

    updateKPIPrices(tickers) {
        if (!Array.isArray(tickers)) return;

        const map = {};
        tickers.forEach(t => map[t.symbol] = t);

        const kpiSymbols = [null, null, null, 'BTCUSDT', 'PAXGUSDT']; // Stocks not updated via SSE yet

        kpiSymbols.forEach((sym, i) => {
            if (!sym || !map[sym]) return;
            const card = document.getElementById(`kpi-card-${i}`);
            if (!card) return;

            const t = map[sym];
            const valEl = card.querySelector('.kpi-value');
            const deltaEl = card.querySelector('.kpi-delta');
            const price = parseFloat(t.lastPrice);
            const pct = parseFloat(t.priceChangePercent);

            if (valEl && !isNaN(price)) valEl.textContent = '$' + this.formatPrice(price);
            if (deltaEl && !isNaN(pct)) {
                deltaEl.className = `kpi-delta ${pct >= 0 ? 'positive' : 'negative'}`;
                deltaEl.innerHTML = `${pct >= 0 ? '▲' : '▼'} ${Math.abs(pct).toFixed(2)}% <span style="opacity:0.7;margin-left:4px">24h</span>`;
            }
        });
    },

    updateTickerPrices(cryptoData) {
        if (!Array.isArray(cryptoData)) return;

        const cryptoMap = {};
        cryptoData.forEach(c => cryptoMap[c.pair] = c);

        this.mixedTickerData = this.mixedTickerData.map(p => {
            if (p.type === 'crypto' && cryptoMap[p.symbol]) {
                p.price = cryptoMap[p.symbol].price;
                p.change = cryptoMap[p.symbol].change;
            }
            return p;
        });

        this.renderMixedTicker();
    },

    renderMixedTicker() {
        const track = document.getElementById('mixed-ticker-track') || document.getElementById('ticker-track');
        if (!track || !this.mixedTickerData.length) return;

        const html = this.mixedTickerData.map(p => {
            const cls = p.change >= 0 ? 'positive' : 'negative';
            const displaySym = p.display || p.symbol;
            const changeStr = p.change !== undefined && p.change !== null ? (p.change >= 0 ? '▲' : '▼') + Math.abs(p.change).toFixed(2) + '%' : '';
            const priceStr = p.type === 'forex' ? p.price.toFixed(4) : (p.price >= 1000 ? p.price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : p.price.toFixed(2));
            return `<span class="ticker-item" style="cursor:pointer" onclick="window.location='/trading?symbol=${p.symbol}'"><span class="symbol">${displaySym}</span><span class="price">$${priceStr}</span><span class="change ${cls}">${changeStr}</span></span>`;
        }).join('');

        track.innerHTML = html + html;
    },

    formatPrice(price) {
        if (price === undefined || price === null || isNaN(price)) return '0.00';
        if (price >= 1000) return price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (price >= 1) return price.toFixed(2);
        if (price >= 0.01) return price.toFixed(4);
        return price.toFixed(6);
    }
};

export default Dashboard;
