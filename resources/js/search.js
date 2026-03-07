/**
 * Global Search Module
 * Handles Terminal commands and autocomplete UI logic
 */

import { Watchlist } from './watchlist'; // Needed to fetch current pairs

// ── XSS guard: escape HTML special chars before injecting into innerHTML ──
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export const GlobalSearch = {
    init() {
        const searchInput = document.getElementById('search-input');
        const searchBox = document.getElementById('search-box');
        if (!searchInput || !searchBox) return;

        // Global Ctrl+K hotkey
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
        });

        // Autocomplete container
        const autocompleteContainer = document.createElement('div');
        autocompleteContainer.className = 'search-autocomplete acrylic';
        autocompleteContainer.style.display = 'none';
        searchBox.appendChild(autocompleteContainer);

        // Event delegation: handle navigation safely via data-nav-href (no inline onclick)
        autocompleteContainer.addEventListener('click', (e) => {
            const item = e.target.closest('[data-nav-href]');
            if (item) {
                const href = item.getAttribute('data-nav-href');
                if (href && href.startsWith('/')) {
                    window.location.href = href;
                }
            }
        });

        let searchTimeout = null;

        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim().toLowerCase();
            clearTimeout(searchTimeout);

            if (query.length < 2) {
                autocompleteContainer.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                this.performSearch(query, autocompleteContainer, searchInput.value);
            }, 200);
        });

        searchInput.addEventListener('focus', (e) => {
            if (e.target.value.trim().length >= 2) {
                autocompleteContainer.style.display = 'block';
            }
        });

        // Close when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchBox.contains(e.target)) {
                autocompleteContainer.style.display = 'none';
            }
        });

        // Handle Enter key for top result
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const firstMatch = autocompleteContainer.querySelector('.search-result-item');
                if (firstMatch) {
                    firstMatch.click();
                } else if (e.target.value.trim()) {
                    window.location.href = `/trading?symbol=${encodeURIComponent(e.target.value.trim().toUpperCase())}`;
                }
            }
        });
    },

    async performSearch(query, container, rawValue) {
        // ── BREAKDOWN TERMINAL COMMANDS ──
        const terminalCmds = ['srch ', 'bi ', 'anr ', 'crpr ', 'gp ', 'g '];
        const isTerminalCmd = terminalCmds.some(cmd => query.startsWith(cmd));

        if (isTerminalCmd) {
            const parts = query.split(' ');
            const cmd = parts[0];
            const arg = parts.slice(1).join(' ').trim();

            if (arg.length < 1) {
                container.innerHTML = `<div style="padding: 10px; color: var(--text-muted); font-size: 0.8rem; text-align: center;">Enter symbol for ${escapeHtml(cmd.toUpperCase())}...</div>`;
                container.style.display = 'block';
                return;
            }

            if (cmd === 'gp') {
                window.location.href = `/trading?symbol=${encodeURIComponent(arg.toUpperCase())}&mode=gp`;
                return;
            }

            if (cmd === 'g') {
                window.location.href = `/chart-builder?symbols=${encodeURIComponent(arg.toUpperCase())}`;
                return;
            }

            container.innerHTML = `<div style="padding: 10px; color: var(--text-muted); font-size: 0.8rem; text-align: center;">
                <div class="wl-loading" style="display:inline-block; width:12px; height:12px; border:2px solid var(--accent); border-top-color:transparent; border-radius:50%; animation:spin 1s linear infinite;"></div>
                <span style="margin-left:8px;">Executing ${escapeHtml(cmd.toUpperCase())}...</span>
            </div>`;
            container.style.display = 'block';

            try {
                const r = await fetch(`/api/market/terminal?command=${encodeURIComponent(cmd)}&query=${encodeURIComponent(arg)}`);
                const res = await r.json();

                if (!r.ok || res.error) {
                    container.innerHTML = `<div style="padding: 10px; color: var(--danger); font-size: 0.8rem; text-align: center;">Error: ${escapeHtml(res.error || 'Failed to execute command')}</div>`;
                    return;
                }

                this.renderTerminalResult(res, container);
            } catch (e) {
                container.innerHTML = `<div style="padding: 10px; color: var(--danger); font-size: 0.8rem; text-align: center;">Error: Network issue</div>`;
            }
            return;
        }

        // ── STANDARD LOCAL SEARCH ──
        let results = [];

        // Use Watchlist data for local search
        const pairData = Watchlist.pairTabData;

        // Search Crypto
        if (pairData.crypto && Array.isArray(pairData.crypto)) {
            const cryptoMatches = pairData.crypto.filter(p =>
                (p.pair || '').toLowerCase().includes(query) ||
                (p.symbol || '').toLowerCase().includes(query)
            ).map(p => ({
                label: p.pair, type: 'Crypto', symbol: (p.symbol || p.pair).replace('/', '')
            }));
            results = results.concat(cryptoMatches.slice(0, 3));
        }

        // Search Stocks
        if (pairData.stocks && Array.isArray(pairData.stocks)) {
            const stockMatches = pairData.stocks.filter(p =>
                (p.symbol || '').toLowerCase().includes(query) ||
                (p.name || '').toLowerCase().includes(query)
            ).map(p => ({
                label: `${p.symbol} - ${p.name || ''}`, type: 'Stock', symbol: `stock:${p.symbol}`
            }));
            results = results.concat(stockMatches.slice(0, 3));
        }

        // Search Forex
        if (pairData.forex && pairData.forex.rates) {
            const rates = Object.keys(pairData.forex.rates);
            const forexMatches = rates.filter(cur =>
                cur.toLowerCase().includes(query) ||
                `usd${cur.toLowerCase()}`.includes(query) ||
                `${cur.toLowerCase()}usd`.includes(query)
            ).map(cur => ({
                label: `${cur}/USD (or ${cur} pair)`, type: 'Forex', symbol: `forex:${cur}`
            }));
            results = results.concat(forexMatches.slice(0, 2));
        }

        // Search Commodities
        if (pairData.commodities && Array.isArray(pairData.commodities)) {
            const commodityMatches = pairData.commodities.filter(p =>
                (p.symbol || '').toLowerCase().includes(query) ||
                (p.name || '').toLowerCase().includes(query)
            ).map(p => ({
                label: `${p.name || p.symbol}`, type: 'Commodity', symbol: p.symbol === 'XAU' ? 'PAXGUSDT' : p.symbol === 'BTC' ? 'BTCUSDT' : p.symbol
            }));
            results = results.concat(commodityMatches.slice(0, 2));
        }

        if (results.length === 0) {
            container.innerHTML = `<div style="padding: 10px; color: var(--text-muted); font-size: 0.8rem; text-align: center;">No matches found</div>`;
        } else {
            // Use data-nav-href instead of inline onclick — navigation handled by event delegation
            container.innerHTML = results.map(r => `
                <div class="search-result-item" data-nav-href="/trading?symbol=${encodeURIComponent(r.symbol)}">
                    <span style="font-weight: 600; color: var(--text-primary);">${escapeHtml(r.label)}</span>
                    <span class="badge" style="font-size: 0.6rem; opacity: 0.7">${escapeHtml(r.type)}</span>
                </div>
            `).join('');
        }
        container.style.display = 'block';
    },

    renderTerminalResult(res, container) {
        const data = res.data;
        let html = '<div class="terminal-result-block">';

        if (res.type === 'srch') {
            html += `<div class="term-header">Terminal function: <span class="term-highlight">EQS</span> (Equity Screener)</div>`;
            if (!data || data.length === 0) {
                html += `<div class="term-body" style="text-align:center; padding: 10px;">No equities found or missing data.</div>`;
            } else {
                html += `<div class="term-body"><table class="term-table">
                    <tr><th>Symbol</th><th>Name</th><th>Price</th><th>Change</th></tr>`;
                data.forEach(q => {
                    const cls = q.change_pct >= 0 ? 'positive' : 'negative';
                    // data-nav-href used for safe navigation
                    html += `<tr class="term-tr" data-nav-href="/trading?symbol=stock:${encodeURIComponent(q.symbol)}">
                        <td style="font-weight:bold; color:var(--text-primary)">${escapeHtml(q.symbol)}</td>
                        <td style="color:var(--text-muted); max-width:120px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(q.name)}</td>
                        <td>$${escapeHtml(q.price.toFixed(2))}</td>
                        <td class="${cls}">${q.change_pct > 0 ? '+' : ''}${escapeHtml(q.change_pct.toFixed(2))}%</td>
                    </tr>`;
                });
                html += `</table></div>`;
            }
        }
        else if (res.type === 'bi') {
            const prof = data.profile || {};
            const news = data.news || [];
            html += `<div class="term-header">Terminal function: <span class="term-highlight">BI</span> (Bloomberg Intelligence)</div>`;
            html += `<div class="term-body">
                <div style="display:flex; justify-content:space-between; align-items:flex-end; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:8px; margin-bottom:8px;">
                    <div>
                        <div style="font-size:1.1rem; font-weight:700; color:var(--text-primary)">${escapeHtml(prof.name || 'Unknown')} (${escapeHtml(prof.symbol || '')})</div>
                        <div style="font-size:0.75rem; color:var(--text-muted)">${escapeHtml(prof.industry || '-')} | ${escapeHtml(prof.sector || '-')}</div>
                    </div>
                    <button class="btn btn-sm btn-primary" data-nav-href="/trading?symbol=stock:${encodeURIComponent(prof.symbol || '')}">View Chart</button>
                </div>
                <div style="font-size:0.8rem; font-weight:600; color:var(--text-secondary); margin-bottom:4px">Latest Intelligence:</div>
                <ul style="list-style:none; padding:0; margin:0;">`;

            if (news.length === 0) html += `<li style="color:var(--text-muted); font-size:0.75rem;">No recent news found.</li>`;
            news.slice(0, 4).forEach(n => {
                const time = new Date(n.datetime * 1000).toLocaleDateString();
                // Validate URL scheme to block javascript: URIs
                const safeUrl = (n.url && /^https?:\/\//i.test(n.url)) ? n.url : '#';
                html += `<li style="margin-bottom:6px; font-size:0.75rem;">
                    <span style="color:var(--text-muted); font-size:0.65rem; display:inline-block; width:65px;">${escapeHtml(time)}</span>
                    <a href="${escapeHtml(safeUrl)}" target="_blank" rel="noopener noreferrer" style="color:var(--text-primary); text-decoration:none;" class="term-link">${escapeHtml(n.headline)}</a>
                </li>`;
            });
            html += `</ul></div>`;
        }
        else if (res.type === 'anr') {
            html += `<div class="term-header">Terminal function: <span class="term-highlight">ANR</span> (Analyst Recommendations) • <span style="color:var(--text-primary)">${escapeHtml(data.symbol)}</span></div>`;
            if (!data.has_recommendation) {
                html += `<div class="term-body" style="text-align:center; padding: 10px;">Analyst data not available for this symbol.</div>`;
            } else {
                const tgt = data.target_price || {};
                const rec = data.recommendation || {};
                const total = Object.values(rec).reduce((a, b) => a + b, 0);
                const meanTxt = tgt.mean ? `$${escapeHtml(tgt.mean.toFixed(2))}` : 'N/A';

                html += `<div class="term-body" style="display:flex; gap:15px; padding:10px;">
                    <div style="flex:1; text-align:center; border-right:1px solid rgba(255,255,255,0.1)">
                        <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase;">Consensus Target</div>
                        <div style="font-size:1.4rem; font-weight:bold; color:var(--accent); margin-top:4px;">${meanTxt}</div>
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Total Ratings: ${escapeHtml(String(total))}</div>
                    </div>
                    <div style="flex:1.5; display:flex; flex-direction:column; justify-content:center; gap:4px; font-size:0.75rem;">
                        <div style="display:flex; justify-content:space-between;"><span>Strong Buy</span><span style="color:var(--success); font-weight:bold;">${escapeHtml(String(rec.strong_buy))}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span>Buy</span><span style="color:var(--success)">${escapeHtml(String(rec.buy))}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span>Hold</span><span style="color:var(--warning)">${escapeHtml(String(rec.hold))}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span>Sell</span><span style="color:var(--danger)">${escapeHtml(String(rec.sell))}</span></div>
                        <div style="display:flex; justify-content:space-between;"><span>Strong Sell</span><span style="color:var(--danger); font-weight:bold;">${escapeHtml(String(rec.strong_sell))}</span></div>
                    </div>
                </div>`;
            }
        }
        else if (res.type === 'crpr') {
            html += `<div class="term-header">Terminal function: <span class="term-highlight">CRPR</span> (Credit/Health Profile) • <span style="color:var(--text-primary)">${escapeHtml(data.valuation?.symbol || '')}</span></div>`;
            html += `<div class="term-body" style="padding:10px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:8px;">
                    <div>
                        <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase;">Synthetic Rating</div>
                        <div style="font-size:1.5rem; font-weight:bold; color:var(--text-primary); display:flex; align-items:center; gap:8px;">
                            ${escapeHtml(data.synthetic_rating)}
                            <span style="font-size:0.7rem; font-weight:normal; padding:2px 6px; border-radius:4px; background:rgba(255,255,255,0.1); color:var(--text-muted)">${escapeHtml(data.outlook)}</span>
                        </div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase;">Valuation</div>
                        <div style="font-size:0.85rem; color:var(--text-primary);">P/E: ${escapeHtml(data.valuation?.valuation?.pe_ratio?.toFixed(2) || 'N/A')}</div>
                        <div style="font-size:0.85rem; color:var(--text-primary);">P/B: ${escapeHtml(data.valuation?.valuation?.price_to_book?.toFixed(2) || 'N/A')}</div>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:0.75rem;">
                    <div style="display:flex; justify-content:space-between"><span style="color:var(--text-muted)">Debt/Eq:</span><span style="color:var(--text-primary)">${escapeHtml(data.valuation?.ratios?.debt_equity?.toFixed(2) || 'N/A')}%</span></div>
                    <div style="display:flex; justify-content:space-between"><span style="color:var(--text-muted)">Curr Ratio:</span><span style="color:var(--text-primary)">${escapeHtml(data.valuation?.ratios?.current_ratio?.toFixed(2) || 'N/A')}x</span></div>
                    <div style="display:flex; justify-content:space-between"><span style="color:var(--text-muted)">Gross Margin:</span><span style="color:var(--text-primary)">${escapeHtml(data.valuation?.ratios?.gross_margin?.toFixed(2) || 'N/A')}%</span></div>
                    <div style="display:flex; justify-content:space-between"><span style="color:var(--text-muted)">ROE:</span><span style="color:var(--text-primary)">${escapeHtml(data.valuation?.ratios?.roe?.toFixed(2) || 'N/A')}%</span></div>
                </div>
            </div>`;
        }

        html += '</div>';
        container.innerHTML = html;
    }
};

export default GlobalSearch;

