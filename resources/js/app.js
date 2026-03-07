import './bootstrap';
import '../css/app.css';

/**
 * MarketPro — Client-Side API Layer
 * ════════════════════════════════════
 * Provides a unified interface for fetching live market data.
 * Strategy:
 *   1. Calls the local Laravel API (served from this server – uses cache)
 *   2. If response is empty/error → falls back to Binance Public REST directly
 *   3. If Binance also fails    → falls back to CoinGecko Public REST
 */

// ── GLOBAL FETCH INTERCEPTOR ──────────────────────────────────────────────
const originalFetch = window.fetch;
let rateLimitWarningShown = false;

window.fetch = async function (...args) {
    const response = await originalFetch.apply(this, args);

    // Check for Finnhub/API Rate Limit
    if (response.status === 429 && !rateLimitWarningShown) {
        showRateLimitWarning();
    }

    return response;
};

function showRateLimitWarning() {
    rateLimitWarningShown = true;

    const banner = document.createElement('div');
    banner.id = 'rate-limit-banner';
    banner.innerHTML = `
        <div style="background: rgba(239, 68, 68, 0.9); backdrop-filter: blur(10px); color: white; padding: 12px 24px; text-align: center; font-family: var(--font-display, 'Rajdhani', sans-serif); font-size: 0.9rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #b91c1c; position: fixed; top: 0; left: 0; width: 100%; z-index: 9999; box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <span><strong>API Rate Limit Reached.</strong> Free tier limit exceeded. Some market data (news, stock profiles) may be temporarily unavailable.</span>
            </div>
            <button onclick="document.getElementById('rate-limit-banner').style.display='none'" style="background: transparent; border: 1px solid rgba(255,255,255,0.3); color: white; border-radius: 4px; padding: 4px 12px; cursor: pointer; font-size: 0.8rem; transition: background 0.2s;">Dismiss</button>
        </div>
    `;

    document.body.prepend(banner);
}

window.MarketAPI = {

    // ── CONFIG ────────────────────────────────────────────────────────────────
    localBase: '/api/market',
    binanceBase: 'https://data-api.binance.vision/api/v3', // Same bypass as server
    coinGeckoBase: 'https://api.coingecko.com/api/v3',

    /**
     * Generic fetch with fallback chain.
     * @param {string} localPath   e.g. '/top-pairs'
     * @param {Object} localParams query params for local API
     * @param {string} remotePath  Binance REST path (or null to skip)
     * @param {Object} remoteParams
     * @param {string} geckoPath   CoinGecko fallback path (or null)
     * @param {Object} geckoParams
     */
    async fetchWithFallback(localPath, localParams = {}, remotePath = null, remoteParams = {}, geckoPath = null, geckoParams = {}) {
        // 1. Try local Laravel API
        try {
            const url = new URL(this.localBase + localPath, window.location.origin);
            Object.entries(localParams).forEach(([k, v]) => url.searchParams.set(k, v));
            const res = await fetch(url.toString(), { signal: AbortSignal.timeout(15000) });
            if (res.ok) {
                const data = await res.json();
                if (data && (Array.isArray(data) ? data.length > 0 : Object.keys(data).length > 0)) {
                    return data;
                }
            }
        } catch (e) { console.warn('[MarketAPI] Local API failed:', e.message); }

        // 2. Fallback: Binance Public REST
        if (remotePath) {
            try {
                const url = new URL(this.binanceBase + remotePath);
                Object.entries(remoteParams).forEach(([k, v]) => url.searchParams.set(k, v));
                const res = await fetch(url.toString(), { signal: AbortSignal.timeout(8000) });
                if (res.ok) {
                    const data = await res.json();
                    if (data) {
                        console.info('[MarketAPI] Binance direct fallback used.');
                        return data;
                    }
                }
            } catch (e) { console.warn('[MarketAPI] Binance direct failed:', e.message); }
        }

        // 3. Final fallback: CoinGecko Public REST
        if (geckoPath) {
            try {
                const url = new URL(this.coinGeckoBase + geckoPath);
                Object.entries(geckoParams).forEach(([k, v]) => url.searchParams.set(k, v));
                const res = await fetch(url.toString(), { signal: AbortSignal.timeout(10000) });
                if (res.ok) {
                    const data = await res.json();
                    if (data) {
                        console.info('[MarketAPI] CoinGecko fallback used.');
                        return data;
                    }
                }
            } catch (e) { console.warn('[MarketAPI] CoinGecko fallback failed:', e.message); }
        }

        console.error('[MarketAPI] All sources failed for', localPath);
        return null;
    },

    // ── PUBLIC METHODS ────────────────────────────────────────────────────────

    async getTopPairs(limit = 30) {
        const data = await this.fetchWithFallback(
            '/top-pairs', {},
            '/ticker/24hr', {},
            '/coins/markets', { vs_currency: 'usd', order: 'volume_desc', per_page: limit, page: 1, sparkline: false }
        );

        if (!data) return [];

        // Stablecoin base symbols to exclude (produce /USDT anomalies)
        const skipBases = ['USDT', 'USDC', 'BUSD', 'FDUSD', 'TUSD', 'USDP', 'DAI', 'USD1', 'U'];

        // Normalize CoinGecko markets response
        if (Array.isArray(data) && data[0] && data[0].current_price !== undefined) {
            return data
                .filter(c => !skipBases.includes(c.symbol.toUpperCase()))
                .map(c => ({
                    pair: c.symbol.toUpperCase() + '/USDT',
                    symbol: c.symbol.toUpperCase() + 'USDT',
                    price: c.current_price,
                    change: c.price_change_percentage_24h || 0,
                    quoteVolume: c.total_volume || 0,
                }));
        }

        // Normalize Binance ticker/all response
        if (Array.isArray(data) && data[0] && data[0].lastPrice !== undefined) {
            return data
                .filter(t => {
                    if (!t.symbol.endsWith('USDT')) return false;
                    if (parseFloat(t.quoteVolume) <= 100000) return false;
                    const base = t.symbol.replace('USDT', '');
                    return !skipBases.includes(base) && base.length > 0;
                })
                .sort((a, b) => parseFloat(b.quoteVolume) - parseFloat(a.quoteVolume))
                .slice(0, limit)
                .map(t => ({
                    pair: t.symbol.replace('USDT', '') + '/USDT',
                    symbol: t.symbol,
                    price: parseFloat(t.lastPrice),
                    change: parseFloat(t.priceChangePercent),
                    quoteVolume: parseFloat(t.quoteVolume),
                }));
        }

        return data; // already normalized from local API
    },

    async getTicker(symbol) {
        const data = await this.fetchWithFallback(
            '/ticker', { symbol },
            `/ticker/24hr`, { symbol },
            null, {}
        );
        return data;
    },

    async getKlines(symbol, interval, limit = 200) {
        const data = await this.fetchWithFallback(
            '/klines', { symbol, interval, limit },
            '/klines', { symbol, interval, limit },
            null, {}
        );

        if (!Array.isArray(data)) return [];

        // If raw Binance format (array of arrays)
        if (Array.isArray(data[0])) {
            return data.map(k => ({
                time: Math.floor(k[0] / 1000),
                open: parseFloat(k[1]),
                high: parseFloat(k[2]),
                low: parseFloat(k[3]),
                close: parseFloat(k[4]),
                volume: parseFloat(k[5]),
            }));
        }

        return data; // already normalized from local API
    },
};

import { Watchlist } from './watchlist';
import { WebSocketManager } from './websocket';
import { GlobalSearch } from './search';
import { Dashboard } from './dashboard';

// Re-implement missing initGlobalNewsTicker
window.initGlobalNewsTicker = async function () {
    const contentEl = document.getElementById('breaking-ticker-global');
    if (!contentEl) return;

    // Clear immediately to avoid showing stale content from previous render
    const scrollEl = contentEl.querySelector('#ticker-scroll-global');
    if (scrollEl) scrollEl.innerHTML = '';

    try {
        const res = await fetch('/api/market/news');
        if (res.ok) {
            const news = await res.json();
            if (news && news.length > 0) {
                // Filter out articles older than 30 days to exclude stale/2019 news
                const cutoffTime = (Date.now() / 1000) - (30 * 24 * 3600);
                const recentNews = news.filter(n => !n.datetime || n.datetime > cutoffTime);
                const sourceNews = recentNews.length > 0 ? recentNews : news; // fallback to all if all are old

                // Deduplicate by headline text and take top 8
                const seen = new Set();
                const uniqueHeadlines = [];
                for (const n of sourceNews) {
                    const key = (n.headline || '').substring(0, 60);
                    if (key && !seen.has(key)) {
                        seen.add(key);
                        uniqueHeadlines.push(n);
                    }
                    if (uniqueHeadlines.length >= 8) break;
                }

                if (uniqueHeadlines.length > 0) {
                    const headlines = uniqueHeadlines.map(n => {
                        const cleanHeadline = (n.headline || '').replace(/<[^>]*>?/gm, '').trim();
                        const url = n.url || '#';
                        return `<a href="${url}" target="_blank" rel="noopener" style="margin-right: 3rem; position: relative; text-decoration: none; color: inherit;"><span style="color:var(--accent); margin-right:8px; display:inline-flex; align-items:center; filter:drop-shadow(0 0 6px var(--accent-glow));"><svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor" style="animation: pulse 2s infinite;"><circle cx="12" cy="12" r="10"/></svg></span>${cleanHeadline}</a>`;
                    }).join('');

                    contentEl.innerHTML = `<div class="ticker-scroll">${headlines}${headlines}</div>`; // duplicated for smooth infinite scroll
                    return;
                }
            }
        }
    } catch (e) {
        console.warn('[NewsTicker] Failed to load headlines:', e);
    }

    // Fallback if no news
    contentEl.innerHTML = `<div class="ticker-scroll"><span style="color:var(--text-muted)">Welcome to MarketPro Trading Terminal. Stay tuned for live market updates...</span></div>`;
};

// Initialize core UI modules on DOM load
document.addEventListener('DOMContentLoaded', () => {
    WebSocketManager.init();
    Watchlist.init();
    GlobalSearch.init();
    Dashboard.init();
    if (typeof window.initGlobalNewsTicker === 'function') {
        window.initGlobalNewsTicker();
    }
});

console.info('[MarketPro] API Layer ready — Binance + CoinGecko fallback active.');
