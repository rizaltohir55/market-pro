@extends('layouts.app')

@section('title', 'News & Media')
@section('page-title', 'News & Media')

@section('content')


{{-- Category Tabs --}}
<div class="news-tabs fade-in-up" style="--delay: 0.05s; display:flex; gap: var(--space-2); margin-bottom: var(--space-4); flex-wrap: wrap; align-items: center;">
    <button class="news-tab active" data-category="general" id="tab-general">
        <svg class="tab-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        General
    </button>
    <button class="news-tab" data-category="markets" id="tab-markets">
        <svg class="tab-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
        Markets
    </button>
    <button class="news-tab" data-category="economy" id="tab-economy">
        <svg class="tab-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        Economy
    </button>
    <button class="news-tab" data-category="tech" id="tab-tech">
        <svg class="tab-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        Tech
    </button>
    <button class="news-tab" data-category="politics" id="tab-politics">
        <svg class="tab-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Politics
    </button>
    <button class="news-tab" data-category="business" id="tab-business">
        <svg class="tab-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
        Business
    </button>
    <button class="news-tab" data-category="calendar" id="tab-calendar">
        <svg class="tab-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Economic Calendar
    </button>
    <button class="news-tab" data-category="bookmarks" id="tab-bookmarks" style="margin-left:auto; background: rgba(255,180,0,0.1); border-color: rgba(255,180,0,0.2);">
        <svg class="tab-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        Bookmarks
    </button>
    <div style="flex:1;"></div>
    {{-- Company News Search --}}
    <div class="company-search acrylic" style="display:flex; align-items:center; gap:8px; padding:6px 14px; border-radius:var(--radius-md); border:1px solid rgba(0,240,255,0.1);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="company-search-input" placeholder="Company news (e.g. AAPL)" style="background:transparent; border:none; outline:none; color:var(--text-primary); font-family:var(--font-mono); font-size:0.8rem; width:180px;">
        <button class="btn btn-primary btn-sm" onclick="loadCompanyNews()" id="btn-company-search" style="padding:4px 12px; font-size:0.7rem;">Search</button>
    </div>
</div>

{{-- News Status Bar --}}
<div class="fade-in-up" style="--delay: 0.1s; display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-3); padding: 0 var(--space-1);">
    <div style="display:flex; align-items:center; gap:8px;">
        <span id="news-live-dot" style="width:6px;height:6px;border-radius:50%;background:var(--success);box-shadow:0 0 6px var(--success);animation:pulse 2s infinite;"></span>
        <span id="news-status" style="font-size:0.72rem; color:var(--text-muted); font-family:var(--font-mono);">Loading news...</span>
    </div>
    <div style="display:flex; align-items:center; gap:12px;">
        <input type="text" id="news-filter-input" placeholder="Filter articles..." class="acrylic" style="padding:4px 8px; border-radius:4px; border:1px solid rgba(255,255,255,0.1); background:transparent; color:#fff; font-size:0.75rem; width:150px;">
        <span id="news-count" style="font-size:0.68rem; color:var(--text-muted);"></span>
        <button aria-label="Refresh News" class="btn btn-ghost btn-sm" onclick="refreshNews()" style="font-size:0.68rem; gap:4px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            Refresh
        </button>
    </div>
</div>

{{-- Featured / Hero Article --}}
<div id="hero-article" class="panel acrylic fade-in-up" style="--delay: 0.15s; margin-bottom: var(--space-4); display:none; cursor:pointer; overflow:hidden; position:relative;" onclick="">
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0; min-height: 250px;" id="hero-inner">
        <!-- Populated by JS -->
    </div>
</div>

{{-- News Grid --}}
<div id="news-grid" class="news-grid fade-in-up" style="--delay: 0.2s;">
</div>

{{-- Economic Calendar Panel --}}
<div id="calendar-panel" class="fade-in-up" style="--delay: 0.2s; display:none;">
    <div class="calendar-filters acrylic" style="display:flex; gap:12px; margin-bottom:var(--space-4); padding:10px 15px; border-radius:var(--radius-md); border:1px solid rgba(255,255,255,0.05);">
        <span style="font-size:0.75rem; color:var(--text-muted); align-self:center;">Impact Filter:</span>
        <button class="filter-btn active" onclick="filterCalendar('all')">All</button>
        <button class="filter-btn" onclick="filterCalendar('high')"><span style="color:var(--danger);">●</span> High</button>
        <button class="filter-btn" onclick="filterCalendar('medium')"><span style="color:var(--warning);">●</span> Medium</button>
        <button class="filter-btn" onclick="filterCalendar('low')"><span style="color:var(--success);">●</span> Low</button>
    </div>
    <div id="calendar-list" class="calendar-grid">
        <!-- Populated by JS -->
    </div>
</div>

{{-- Company News Panel --}}
<div id="company-news-panel" class="panel acrylic fade-in-up" style="display:none; margin-top: var(--space-4);">
    <div class="panel-header">
        <span class="panel-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
            <span id="company-news-title">Company News</span>
        </span>
        <button class="btn btn-ghost btn-sm" onclick="closeCompanyNews()">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Close
        </button>
    </div>
    <div class="panel-body" id="company-news-body">
        <div style="text-align:center; padding:2rem; color:var(--text-muted);">Loading...</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentCategory = 'general';
let newsData = {};
let autoRefreshTimer = null;
let savedArticles = new Set();
let lastSeenIds = new Set();

document.addEventListener('DOMContentLoaded', () => {
    // Request Notification Permission
    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
    }

    // Init category tabs
    document.querySelectorAll('.news-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.news-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.dataset.category;
            renderNews();
            if (!newsData[currentCategory]) {
                fetchNews(currentCategory);
            }
        });
    });

    // Filtering
    const filterInput = document.getElementById('news-filter-input');
    if (filterInput) {
        filterInput.addEventListener('input', () => renderNews());
    }

    // Enter key for company search
    document.getElementById('company-search-input').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') loadCompanyNews();
    });

    // Fetch initial bookmarks then general news
    fetchBookmarks().then(() => fetchNews('general'));

    // Auto-refresh every 10 minutes
    autoRefreshTimer = setInterval(() => fetchNews(currentCategory), 600000);
});

async function fetchBookmarks() {
    try {
        const r = await fetch('/api/market/news/bookmarks');
        if (r.ok) {
            const data = await r.json();
            savedArticles = new Set(data.map(a => a.url));
            newsData['bookmarks'] = data;
        }
    } catch(e) { console.error('Error fetching bookmarks', e); }
}

async function fetchNews(category) {
    if (category === 'bookmarks') {
        await fetchBookmarks();
        if (currentCategory === category) renderNews();
        return;
    }

    const statusEl = document.getElementById('news-status');
    if (statusEl) statusEl.textContent = `Loading ${category} news...`;

    try {
        const url = category === 'calendar' ? '/api/market/economic-calendar' : `/api/market/news?category=${category}`;
        const r = await fetch(url, { signal: AbortSignal.timeout(30000) });
        if (!r.ok) throw new Error('API error');
        const data = await r.json();
        newsData[category] = data;

        if (category === 'calendar') {
             renderCalendar();
             return;
        }

        if (category === 'general' || category === 'markets') {
            checkPushNotifications(data);
        }

        if (currentCategory === category) renderNews();
    } catch (e) {
        console.warn('[News] fetch failed for', category, e.message);
        if (statusEl) statusEl.textContent = `Failed to load ${category} news. Retrying...`;
        // Retry after 5s
        setTimeout(() => fetchNews(category), 5000);
    }
}



function checkPushNotifications(data) {
    if (!("Notification" in window) || Notification.permission !== "granted") return;
    
    const keywords = ['breaking', 'alert', 'crash', 'surge', 'plunge', 'soar', 'emergency', 'hike', 'cut'];
    
    data.slice(0, 5).forEach(article => {
        if (!lastSeenIds.has(article.id)) {
            lastSeenIds.add(article.id);
            
            const titleLower = article.headline.toLowerCase();
            const isImportant = keywords.some(kw => titleLower.includes(kw));
            
            if (isImportant) {
                new Notification("Market Alert: " + article.source, {
                    body: stripHtml(article.headline),
                    icon: article.image || ''
                });
            }
        }
    });
}

async function toggleBookmark(event, articleJson) {
    event.stopPropagation();
    const article = JSON.parse(decodeURIComponent(articleJson));
    try {
        // Need CSRF token if not using API stateless or if session-based
        // Assuming session cookie is enough because GET requests worked without token, but POST might fail if CSRF is active.
        // Actually, in api.php, CSRF is usually exempt. Wait, if it's api.php it usually uses tokens and no CSRF.
        // If it's a typical Laravel setup, API routes don't have CSRF.
        const r = await fetch('/api/market/news/bookmarks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(article)
        });
        if (r.ok) {
            const res = await r.json();
            if (res.status === 'saved') {
                savedArticles.add(article.url);
            } else {
                savedArticles.delete(article.url);
            }
            
            await fetchBookmarks();
            renderNews();
        }
    } catch(e) { console.error('Error toggling bookmark', e); }
}

function refreshNews() {
    newsData[currentCategory] = null;
    if (currentCategory === 'calendar') fetchNews('calendar');
    else fetchNews(currentCategory);
}

function renderNews() {
    const grid = document.getElementById('news-grid');
    const hero = document.getElementById('hero-article');
    const calendarPanel = document.getElementById('calendar-panel');

    if (currentCategory === 'calendar') {
        grid.style.display = 'none';
        hero.style.display = 'none';
        calendarPanel.style.display = 'block';
        renderCalendar();
        return;
    } else {
        grid.style.display = 'grid';
        calendarPanel.style.display = 'none';
    }

    let data = newsData[currentCategory];
    const statusEl = document.getElementById('news-status');
    const countEl = document.getElementById('news-count');
    const filterInput = document.getElementById('news-filter-input');

    if (!data || !data.length) {
        grid.innerHTML = `<div class="news-loading"><div class="news-loader-spinner"></div><span>${currentCategory === 'bookmarks' ? 'No saved articles yet.' : 'Fetching ' + currentCategory + ' news...'}</span></div>`;
        hero.style.display = 'none';
        if (countEl) countEl.textContent = '0 articles';
        return;
    }

    if (filterInput && filterInput.value.trim()) {
        const q = filterInput.value.toLowerCase();
        data = data.filter(a => (a.headline && a.headline.toLowerCase().includes(q)) || (a.summary && a.summary.toLowerCase().includes(q)));
    }

    const catLabels = { general: 'General', markets: 'Markets', economy: 'Economy', tech: 'Tech', politics: 'Politics', business: 'Business', bookmarks: 'Bookmarks' };
    if (statusEl) statusEl.textContent = `${catLabels[currentCategory] || 'General'} — ${currentCategory === 'bookmarks' ? 'Saved' : 'Live News'}`;
    if (countEl) countEl.textContent = `${data.length} articles`;

    function getBkBtn(a) {
        const isSaved = savedArticles.has(a.url);
        const iconColor = isSaved ? 'var(--warning, #ffb400)' : 'rgba(255,255,255,0.4)';
        const fill = isSaved ? 'currentColor' : 'none';
        const json = encodeURIComponent(JSON.stringify(a).replace(/'/g, "&apos;"));
        return `<button aria-label="Save Article" class="btn btn-ghost btn-sm news-bk-btn" onclick="toggleBookmark(event, '${json}')" style="padding:4px; color:${iconColor};" title="Save Article">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="${fill}" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
        </button>`;
    }

    // Hero article (first with an image, if user wants a visual hero, skip if bookmarks tab for grid view)
    let heroArticle = null;
    if (currentCategory !== 'bookmarks' && filterInput && !filterInput.value.trim()) {
        heroArticle = data.find(a => a.image && a.image.length > 10) || data[0];
    }
    
    if (heroArticle) {
        const hasHeroImg = heroArticle.image && heroArticle.image.length > 10;
        hero.style.display = 'block';
        hero.onclick = () => window.open(heroArticle.url, '_blank');
        const cleanSummary = stripHtml(heroArticle.summary);
        
        let heroHtml = '';
        if (hasHeroImg) {
            heroHtml += `
            <div style="background:url('${heroArticle.image}') center/cover no-repeat; min-height:250px; position:relative;">
                <div style="position:absolute;inset:0;background:linear-gradient(90deg, transparent 60%, var(--bg-primary) 100%);"></div>
            </div>`;
        }
        
        const paddingStyle = hasHeroImg ? "padding:var(--space-5);" : "padding:var(--space-5); grid-column: 1 / -1;";
        
        heroHtml += `
            <div style="${paddingStyle} display:flex; flex-direction:column; justify-content:center; gap:var(--space-3);">
                <div style="display:flex; justify-content:space-between; align-items:start;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="news-badge">${heroArticle.source}</span>
                        <span style="font-size:0.68rem; color:var(--text-muted); font-family:var(--font-mono);">${timeAgo(heroArticle.datetime)}</span>
                    </div>
                    ${getBkBtn(heroArticle)}
                </div>
                <h2 style="font-size:1.4rem; font-weight:700; color:var(--text-primary); line-height:1.3; margin:0; font-family:var(--font-display);">${stripHtml(heroArticle.headline)}</h2>
                <p style="font-size:0.8rem; color:var(--text-secondary); line-height:1.6; margin:0; display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">${cleanSummary}</p>
                <div style="display:flex; align-items:center; gap:6px; margin-top:auto;">
                    <span style="font-size:0.7rem; color:var(--accent); font-weight:600;">Read more →</span>
                </div>
            </div>
        `;
        document.getElementById('hero-inner').innerHTML = heroHtml;
        document.getElementById('hero-inner').style.gridTemplateColumns = hasHeroImg ? '1fr 1fr' : '1fr';
    } else {
        hero.style.display = 'none';
    }

    // Grid articles
    const articles = heroArticle ? data.filter(a => a !== heroArticle) : data;
    const catIcons = { general: 'NEWS', markets: 'MKTS', economy: 'ECON', tech: 'TECH', politics: 'POL', business: 'BIZ', bookmarks: 'BKMK' };
    const catIcon = catIcons[currentCategory] || 'NEWS';
    
    if (articles.length === 0 && currentCategory !== 'bookmarks') {
         grid.innerHTML = `<div class="news-loading"><span>No articles match your filter.</span></div>`;
         return;
    }

    grid.innerHTML = articles.map((a, i) => {
        const hasImage = a.image && a.image.length > 10;
        const delay = Math.min(i * 0.03, 0.5);
        const cleanSummary = stripHtml(a.summary);
        const headerArea = hasImage
            ? `<div class="news-card-image" style="background-image:url('${a.image}');">
                   <div class="news-card-overlay"></div>
               </div>`
            : `<div class="news-card-abstract">
                   <div class="abstract-pattern"></div>
                   <div class="abstract-content">
                       <span class="abstract-icon-wrapper" style="color:var(--accent); font-weight:800; font-size:1.6rem; font-family:var(--font-mono); filter:drop-shadow(0 0 8px var(--accent-glow)); letter-spacing:0.1em; opacity: 0.9;">${catIcon}</span>
                       <span class="abstract-source">${a.source}</span>
                   </div>
               </div>`;
        return `
        <div class="news-card acrylic" style="animation-delay:${delay}s" onclick="window.open('${a.url}', '_blank')">
            ${headerArea}
            <div class="news-card-body">
                <div style="display:flex; justify-content:space-between; align-items:start;">
                    <div class="news-card-meta" style="flex:1;">
                        <span class="news-badge">${a.source}</span>
                        <span class="news-time">${timeAgo(a.datetime)}</span>
                    </div>
                    ${getBkBtn(a)}
                </div>
                <h3 class="news-card-title">${stripHtml(a.headline)}</h3>
                ${cleanSummary ? `<p class="news-card-summary">${truncate(cleanSummary, 120)}</p>` : ''}
            </div>
        </div>`;
    }).join('');
}

async function loadCompanyNews() {
    const input = document.getElementById('company-search-input');
    const symbol = (input.value || '').trim().toUpperCase();
    if (!symbol) return;

    const panel = document.getElementById('company-news-panel');
    const body = document.getElementById('company-news-body');
    const title = document.getElementById('company-news-title');

    panel.style.display = 'block';
    title.textContent = `${symbol} — Company News`;
    body.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--text-muted);">Loading news for ' + symbol + '...</div>';

    // Scroll to panel
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

    try {
        const r = await fetch(`/api/market/company-news?symbol=${symbol}&days=7`);
        const data = await r.json();

        if (!data || !data.length) {
            body.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--text-muted);">No recent news found for ' + symbol + '</div>';
            return;
        }

        body.innerHTML = `<div class="company-news-list">${data.map((a, i) => {
            const cleanSummary = stripHtml(a.summary);
            return `
            <div class="company-news-item" onclick="window.open('${a.url}', '_blank')">
                <div style="display:flex; align-items:flex-start; gap:var(--space-3);">
                    ${a.image && a.image.length > 10 ? `<img src="${a.image}" class="company-news-thumb" onerror="this.style.display='none'" alt="">` : ''}
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                            <span class="news-badge" style="font-size:0.6rem;">${a.source}</span>
                            <span style="font-size:0.62rem; color:var(--text-muted); font-family:var(--font-mono);">${timeAgo(a.datetime)}</span>
                        </div>
                        <h4 style="font-size:0.85rem; font-weight:600; color:var(--text-primary); margin:0 0 4px 0; line-height:1.35;">${stripHtml(a.headline)}</h4>
                        <p style="font-size:0.72rem; color:var(--text-secondary); margin:0; line-height:1.5; display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${cleanSummary}</p>
                    </div>
                </div>
            </div>
            `;
        }).join('')}</div>`;
    } catch (e) {
        body.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--danger);">Failed to load company news.</div>';
    }
}

function closeCompanyNews() {
    document.getElementById('company-news-panel').style.display = 'none';
}

let calendarImpactFilter = 'all';

function filterCalendar(impact) {
    calendarImpactFilter = impact;
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.textContent.toLowerCase().includes(impact) || (impact === 'all' && btn.textContent === 'All'));
    });
    renderCalendar();
}

function renderCalendar() {
    let data = newsData['calendar'];
    const container = document.getElementById('calendar-list');
    const statusEl = document.getElementById('news-status');
    const countEl = document.getElementById('news-count');
    
    if (!data || !data.length) {
        container.innerHTML = `<div class="news-loading"><div class="news-loader-spinner"></div><span>Fetching Economic Calendar...</span></div>`;
        return;
    }

    // Update status bar for calendar
    if (statusEl) statusEl.textContent = `Economic Calendar — Live Events`;

    if (calendarImpactFilter !== 'all') {
        data = data.filter(e => e.importance.toLowerCase() === calendarImpactFilter);
    }

    if (countEl) countEl.textContent = `${data.length} events`;

    if (data.length === 0) {
        container.innerHTML = `<div style="text-align:center; padding:3rem; color:var(--text-muted);">No ${calendarImpactFilter} impact events found for the next 7 days.</div>`;
        return;
    }

    container.innerHTML = `
        <div class="calendar-table-header">
            <div style="flex: 0 0 80px;">Time</div>
            <div style="flex: 0 0 60px;">Cur</div>
            <div style="flex: 1;">Event</div>
            <div style="flex: 0 0 80px; text-align:center;">Impact</div>
            <div style="flex: 0 0 80px; text-align:right;">Actual</div>
            <div style="flex: 0 0 80px; text-align:right;">Forecast</div>
            <div style="flex: 0 0 80px; text-align:right;">Prev</div>
        </div>
        ${data.map(e => {
            const isSoon = e.timestamp > (Date.now()/1000) && e.timestamp < (Date.now()/1000 + 3600);
            const isPast = e.timestamp < (Date.now()/1000);
            const impactColor = e.importance === 'high' ? 'var(--danger)' : (e.importance === 'medium' ? 'var(--warning)' : 'var(--success)');
            
            return `
            <div class="calendar-row ${isSoon ? 'event-soon' : ''} ${isPast ? 'event-past' : ''}">
                <div style="flex: 0 0 80px; font-family:var(--font-mono); font-size:0.75rem;">
                    ${new Date(e.timestamp * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false })}
                    <div style="font-size:0.6rem; color:var(--text-muted);">${new Date(e.timestamp * 1000).toLocaleDateString([], { month: 'short', day: 'numeric' })}</div>
                </div>
                <div style="flex: 0 0 60px; display:flex; align-items:center; gap:4px;">
                    <span class="currency-flag">${e.currency}</span>
                </div>
                <div style="flex: 1; font-weight:600; font-size:0.85rem; color:var(--text-primary);">
                    ${e.event}
                    ${isSoon ? '<span class="soon-badge">STARTS SOON</span>' : ''}
                </div>
                <div style="flex: 0 0 80px; display:flex; justify-content:center;">
                    <span class="impact-dot" style="background:${impactColor}; box-shadow:0 0 8px ${impactColor}44;"></span>
                </div>
                <div style="flex: 0 0 80px; text-align:right; font-family:var(--font-mono); font-size:0.8rem; color:${e.actual ? 'var(--text-primary)' : 'var(--text-muted)'}">
                    ${e.actual || '--'}
                </div>
                <div style="flex: 0 0 80px; text-align:right; font-family:var(--font-mono); font-size:0.8rem; color:var(--text-muted)">
                    ${e.forecast || '--'}
                </div>
                <div style="flex: 0 0 80px; text-align:right; font-family:var(--font-mono); font-size:0.8rem; color:var(--text-muted)">
                    ${e.previous || '--'}
                </div>
            </div>
            `;
        }).join('')}
    `;
}

function timeAgo(unixTimestamp) {
    if (!unixTimestamp) return '';
    const now = Math.floor(Date.now() / 1000);
    const diff = now - unixTimestamp;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return new Date(unixTimestamp * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function stripHtml(html) {
    if (!html) return '';
    const tmp = document.createElement("DIV");
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || "";
}

function truncate(str, n) {
    if (!str) return '';
    return str.length > n ? str.slice(0, n) + '…' : str;
}
</script>

<style>

/* ─── Economic Calendar ─────────────────────────────────────────── */
.calendar-grid {
    display: flex;
    flex-direction: column;
    background: rgba(0, 10, 20, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: var(--radius-lg);
    overflow: hidden;
}
.calendar-table-header {
    display: flex;
    padding: 12px 20px;
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.calendar-row {
    display: flex;
    padding: 14px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    transition: all 0.2s ease;
    align-items: center;
}
.calendar-row:hover {
    background: rgba(0, 240, 255, 0.03);
}
.calendar-row.event-soon {
    background: rgba(245, 158, 11, 0.05);
    border-left: 3px solid var(--warning);
}
.calendar-row.event-past {
    opacity: 0.6;
}
.impact-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}
.soon-badge {
    font-size: 0.6rem;
    padding: 1px 6px;
    background: var(--warning);
    color: #000;
    border-radius: 4px;
    margin-left: 8px;
    font-weight: 800;
    animation: pulse 1.5s infinite;
}
.filter-btn {
    padding: 4px 12px;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--text-muted);
    font-size: 0.72rem;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.2s;
}
.filter-btn:hover {
    border-color: rgba(255, 255, 255, 0.3);
    color: var(--text-primary);
}
.filter-btn.active {
    background: var(--accent);
    color: #000;
    border-color: var(--accent);
    font-weight: 700;
}

/* ─── Bookmark Button ──────────────────────────────────────────── */
.news-bk-btn {
    transition: all 0.2s ease;
}
.news-bk-btn:hover {
    transform: scale(1.15);
    color: var(--warning, #ffb400) !important;
}

/* ─── News Tab Buttons ─────────────────────────────────────────── */
.news-tab {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    background: rgba(0, 15, 30, 0.5);
    border: 1px solid rgba(0, 240, 255, 0.08);
    border-radius: var(--radius-md);
    color: var(--text-muted);
    font-family: var(--font-display, 'Rajdhani', sans-serif);
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.news-tab::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(0,240,255,0.05), transparent);
    opacity: 0;
    transition: opacity 0.25s ease;
}
.news-tab:hover {
    background: rgba(0, 240, 255, 0.06);
    border-color: rgba(0, 240, 255, 0.15);
    color: var(--text-primary);
    transform: translateY(-1px);
}
.news-tab:hover::before { opacity: 1; }

.news-tab.active {
    background: rgba(0, 240, 255, 0.1);
    border-color: rgba(0, 240, 255, 0.3);
    color: var(--accent);
    text-shadow: 0 0 12px rgba(0, 240, 255, 0.4);
    box-shadow: 0 0 20px rgba(0, 240, 255, 0.08), inset 0 1px 0 rgba(255,255,255,0.05);
}
.tab-icon {
    font-size: 1rem;
    filter: drop-shadow(0 0 4px rgba(0,240,255,0.2));
}

/* ─── News Badge ───────────────────────────────────────────────── */
.news-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    background: rgba(0, 240, 255, 0.1);
    border: 1px solid rgba(0, 240, 255, 0.15);
    border-radius: 20px;
    font-size: 0.62rem;
    font-weight: 700;
    color: var(--accent);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-family: var(--font-mono);
    white-space: nowrap;
}

/* ─── News Grid ────────────────────────────────────────────────── */
.news-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: var(--space-4);
}

/* ─── News Card ────────────────────────────────────────────────── */
.news-card {
    border-radius: var(--radius-lg);
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.04);
    position: relative;
    animation: fadeInCard 0.4s ease both;
    display: flex;
    flex-direction: column;
}
@keyframes fadeInCard {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
.news-card:hover {
    transform: translateY(-4px);
    border-color: rgba(0, 240, 255, 0.15);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4), 0 0 30px rgba(0, 240, 255, 0.06);
}
.news-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: var(--radius-lg);
    padding: 1px;
    background: linear-gradient(135deg, rgba(0,240,255,0.1), transparent 50%, rgba(0,240,255,0.05));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.news-card:hover::after { opacity: 1; }

.news-card-image {
    height: 180px;
    background-size: cover;
    background-position: center;
    position: relative;
    flex-shrink: 0;
}
.news-card-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, transparent 40%, rgba(0, 8, 20, 0.95) 100%);
}

/* ─── Abstract Header (for cards without images) ──────────────── */
.news-card-abstract {
    height: 180px;
    flex-shrink: 0;
    position: relative;
    background: linear-gradient(135deg, rgba(0, 15, 35, 0.9), rgba(0, 40, 60, 0.7));
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.news-card-abstract .abstract-pattern {
    position: absolute;
    inset: 0;
    background:
        repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(0, 240, 255, 0.02) 20px, rgba(0, 240, 255, 0.02) 22px),
        repeating-linear-gradient(-45deg, transparent, transparent 20px, rgba(0, 240, 255, 0.015) 20px, rgba(0, 240, 255, 0.015) 22px);
    opacity: 1;
}
.news-card-abstract .abstract-pattern::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, rgba(0, 240, 255, 0.06) 0%, transparent 70%);
}
.news-card-abstract .abstract-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    opacity: 0.7;
}
.news-card-abstract .abstract-icon {
    font-size: 2.2rem;
    filter: drop-shadow(0 0 8px rgba(0, 240, 255, 0.3));
}
.news-card-abstract .abstract-source {
    font-family: var(--font-mono);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--accent);
    text-transform: uppercase;
    letter-spacing: 0.12em;
    text-shadow: 0 0 8px rgba(0, 240, 255, 0.3);
}
.news-card:hover .news-card-abstract .abstract-content { opacity: 1; }

.news-card-body {
    padding: var(--space-4);
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1;
}

.news-card-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}
.news-time {
    font-size: 0.65rem;
    color: var(--text-muted);
    font-family: var(--font-mono);
}

.news-card-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.35;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: color 0.2s ease;
}
.news-card:hover .news-card-title {
    color: var(--accent);
    text-shadow: 0 0 10px rgba(0,240,255,0.15);
}

.news-card-summary {
    font-size: 0.75rem;
    color: var(--text-secondary);
    line-height: 1.55;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.news-card-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: auto;
}
.news-tag {
    padding: 2px 6px;
    background: rgba(255, 255, 255, 0.04);
    border-radius: 4px;
    font-size: 0.6rem;
    color: var(--text-muted);
    font-family: var(--font-mono);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* ─── Hero Article ─────────────────────────────────────────────── */
#hero-article {
    transition: all 0.3s ease;
    border: 1px solid rgba(0,240,255,0.06);
}
#hero-article:hover {
    border-color: rgba(0,240,255,0.15);
    box-shadow: 0 12px 60px rgba(0,0,0,0.5), 0 0 40px rgba(0,240,255,0.05);
    transform: translateY(-2px);
}

/* ─── Company News ─────────────────────────────────────────────── */
.company-news-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.company-news-item {
    padding: var(--space-3) var(--space-4);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}
.company-news-item:hover {
    background: rgba(0, 240, 255, 0.04);
    border-color: rgba(0, 240, 255, 0.08);
}
.company-news-thumb {
    width: 80px;
    height: 56px;
    object-fit: cover;
    border-radius: var(--radius-sm);
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,0.05);
}

/* ─── Loading ──────────────────────────────────────────────────── */
.news-loading {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem var(--space-4);
    color: var(--text-muted);
    font-size: 0.82rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-3);
}
.news-loader-spinner {
    width: 32px;
    height: 32px;
    border: 2px solid rgba(0, 240, 255, 0.1);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── Responsive ───────────────────────────────────────────────── */
@media (max-width: 768px) {
    .news-grid {
        grid-template-columns: 1fr;
    }
    #hero-article > div {
        grid-template-columns: 1fr !important;
    }
    .news-card-image {
        height: 140px;
    }
    .company-search {
        width: 100%;
    }
    .news-tabs {
        gap: 4px !important;
    }
    .news-tab {
        padding: 6px 10px;
        font-size: 0.7rem;
    }
}
</style>
@endsection
