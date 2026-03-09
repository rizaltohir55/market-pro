<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NewsMarketService extends BaseMarketService
{
    /**
     * Get market news from various categories using Google News RSS.
     * Categories: Markets, Economy, Tech, Politics, Business 
     * Supports fetching real-time categorization.
     *
     * @param string $category
     * @param int $limit
     */
    public function getNewsByCategory(string $category, int $limit = 30): array
    {
        $category = strtolower($category);
        $rssMap = [
            'markets'  => 'https://news.google.com/rss/search?q=Financial+Markets+OR+Stock+Market',
            'economy'  => 'https://news.google.com/rss/search?q=Global+Economy+OR+US+Economy',
            'tech'     => 'https://news.google.com/rss/headlines/section/topic/TECHNOLOGY',
            'politics' => 'https://news.google.com/rss/headlines/section/topic/POLITICS',
            'business' => 'https://news.google.com/rss/headlines/section/topic/BUSINESS',
            'general'  => 'https://news.google.com/rss',
        ];

        $url = $rssMap[$category] ?? $rssMap['general'];
        $cacheKey = "news_google_rss_{$category}";

        $articles = Cache::remember($cacheKey, 300, function () use ($url, $category, $limit) {
            try {
                $r = Http::withOptions($this->getHttpOptions(10))->retry(3, 200)->get($url);
                if ($r && $r->successful()) {
                    $xml = simplexml_load_string($r->body());
                    if ($xml && isset($xml->channel->item)) {
                        $articles = [];
                        foreach ($xml->channel->item as $item) {
                            if (count($articles) >= $limit) break;
                            
                            $title = (string) $item->title;
                            $link = (string) $item->link;
                            $pubDate = strtotime((string) $item->pubDate);
                            $source = (string) $item->source ?? 'Google News';
                            
                            // Google News RSS description sometimes has an img tag, though often not
                            $image = '';
                            $desc = (string) $item->description;
                            if (preg_match('/src="([^"]+)"/i', $desc, $matches)) {
                                $image = $matches[1];
                            }

                            // The title often contains the source at the end " - Source"
                            if (preg_match('/^(.*?)\s*-\s*([^-]+)$/', $title, $m)) {
                                $title = trim($m[1]);
                                if (empty((string)$item->source)) {
                                    $source = trim($m[2]);
                                }
                            }

                            $articles[] = [
                                'id'       => md5($link),
                                'headline' => $title,
                                'summary'  => strip_tags(explode('&lt;font size="-1"&gt;', $desc)[1] ?? $desc),
                                'source'   => $source,
                                'url'      => $link,
                                'image'    => $image,
                                'category' => $category,
                                'datetime' => $pubDate ?: time(),
                                'related'  => '',
                            ];
                        }
                        
                        Log::info("GlobalMarketService: Google News RSS OK for category '$category' — " . count($articles) . " articles.");
                        return $articles;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("GlobalMarketService: Google News RSS exception for category '$category' — " . $e->getMessage());
            }
            return [];
        }) ?? [];

        // Enrich articles missing proper thumbnails outside the cache closure
        return $this->enrichArticleImages($articles);
    }

    /**
     * Get market news from Finnhub.
     * Categories: general, forex, crypto, merger
     * Returns real articles with headline, image, source, url, summary, datetime.
     *
     * @param string $category  News category (general|forex|crypto|merger)
     * @param int    $limit     Max articles to return
     */
    public function getMarketNews(string $category = 'general', int $limit = 50): array
    {
        $allowed = ['general', 'forex', 'crypto', 'merger'];
        if (!in_array($category, $allowed)) {
            $category = 'general';
        }

        $cacheKey = "market_news_{$category}_v2";
        $news = Cache::remember($cacheKey, 600, function () use ($category, $limit) {
            try {
                $r = $this->finnhubGet('/news', ['category' => $category]);
                if ($r && $r->successful()) {
                    $news = $r->json();
                    if (is_array($news) && !empty($news)) {
                        $articles = array_slice($news, 0, $limit);
                        Log::info("GlobalMarketService: Finnhub market news OK for category '$category' — " . count($articles) . " articles.");
                        
                        return array_map(function ($item) {
                            return [
                                'id'        => $item['id'] ?? 0,
                                'headline'  => $item['headline'] ?? '',
                                'summary'   => $item['summary'] ?? '',
                                'source'    => $item['source'] ?? '',
                                'url'       => $item['url'] ?? '',
                                'image'     => $item['image'] ?? '',
                                'category'  => $item['category'] ?? 'general',
                                'datetime'  => $item['datetime'] ?? 0,
                                'related'   => $item['related'] ?? '',
                            ];
                        }, $articles);
                    }
                }
            } catch (\Exception $e) {
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    throw $e;
                }
                Log::warning("GlobalMarketService: Finnhub market news exception for category '$category' — " . $e->getMessage());
            }

            Log::warning("GlobalMarketService: Market news failed for category '$category'.");
            return [];
        }) ?? [];

        // Enrich articles missing proper thumbnails outside the cache closure
        return $this->enrichArticleImages($news);
    }

    /**
     * Get company-specific news from Finnhub.
     * Returns recent news articles related to a specific stock symbol.
     *
     * @param string $symbol  Stock symbol (e.g. AAPL)
     * @param int    $days    Number of past days to fetch news for
     */
    public function getCompanyNews(string $symbol, int $days = 7): array
    {
        $symbol   = strtoupper($symbol);
        $cacheKey = "company_news_{$symbol}_{$days}_v2";

        $news = Cache::remember($cacheKey, 900, function () use ($symbol, $days) {
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $to   = date('Y-m-d');

            try {
                $r = $this->finnhubGet('/company-news', [
                    'symbol' => $symbol,
                    'from'   => $from,
                    'to'     => $to,
                ]);
                if ($r && $r->successful()) {
                    $news = $r->json();
                    if (is_array($news) && !empty($news)) {
                        $articles = array_slice($news, 0, 30);
                        Log::info("GlobalMarketService: Finnhub company news OK for $symbol — " . count($articles) . " articles.");
                        return array_map(function ($item) use ($symbol) {
                            return [
                                'id'        => $item['id'] ?? 0,
                                'headline'  => $item['headline'] ?? '',
                                'summary'   => $item['summary'] ?? '',
                                'source'    => $item['source'] ?? '',
                                'url'       => $item['url'] ?? '',
                                'image'     => $item['image'] ?? '',
                                'symbol'    => $symbol,
                                'datetime'  => $item['datetime'] ?? 0,
                                'related'   => $item['related'] ?? '',
                            ];
                        }, $articles);
                    }
                }
            } catch (\Exception $e) {
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    throw $e;
                }
                Log::warning("GlobalMarketService: Finnhub company news exception for $symbol — " . $e->getMessage());
            }

            Log::warning("GlobalMarketService: Company news failed for $symbol.");
            return [];
        }) ?? [];

        // Enrich articles missing proper thumbnails outside the cache closure
        return $this->enrichArticleImages($news);
    }

    /**
     * Enrich articles that are missing proper thumbnail images.
     * Scrapes the og:image meta tag from the original article URL.
     * This provides the REAL thumbnail from the publisher — no fake images.
     * Uses curl_multi for parallel fetching to avoid timeouts.
     *
     * @param array $articles
     * @return array
     */
    private function enrichArticleImages(array $articles): array
    {
        // Patterns that indicate a generic logo rather than a real article thumbnail
        $logoPatterns = [
            'static2.finnhub.io/file/publicdatany',
            'logo',
            'favicon',
            'brand',
            'default',
        ];

        // Domains that are un-scrapable or too slow (Google News redirects, etc.)
        $skipDomains = [
            'news.google.com',
            'consent.google.com',
            'google.com/rss',
        ];

        $toEnrich = [];
        foreach ($articles as $idx => &$article) {
            $img = $article['image'] ?? '';
            $needsEnrich = empty($img) || strlen($img) < 10;

            if (!$needsEnrich) {
                foreach ($logoPatterns as $pattern) {
                    if (stripos($img, $pattern) !== false) {
                        $needsEnrich = true;
                        break;
                    }
                }
            }

            if ($needsEnrich && !empty($article['url'])) {
                $url = $article['url'];
                
                // Skip un-scrapable domains
                $skip = false;
                foreach ($skipDomains as $domain) {
                    if (stripos($url, $domain) !== false) {
                        $skip = true;
                        break;
                    }
                }
                
                if (!$skip) {
                    // Check cache first
                    $cacheKey = 'og_img_' . md5($url);
                    $cached = Cache::get($cacheKey);
                    if ($cached !== null) {
                        if ($cached !== '') {
                            $article['image'] = $cached;
                        }
                    } else {
                        $toEnrich[] = $url;
                    }
                }
            }
        }
        unset($article);

        if (!empty($toEnrich)) {
            // Dispatch background job to scrape missing images (non-blocking)
            \App\Jobs\EnrichNewsImages::dispatch(array_slice($toEnrich, 0, 10)); // Limit to 10 per request
            Log::info("NewsMarketService: Dispatched background enrichment for " . count($toEnrich) . " articles.");
        }

        return $articles;
    }
}
