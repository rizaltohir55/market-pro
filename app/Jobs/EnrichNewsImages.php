<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnrichNewsImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $urls;

    /**
     * Create a new job instance.
     *
     * @param array $urls List of article URLs to scrape for og:image
     */
    public function __construct(array $urls)
    {
        $this->urls = array_unique($urls);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->urls)) {
            return;
        }

        $mh = curl_multi_init();
        $handles = [];

        foreach ($this->urls as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 2,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => 1,
                CURLOPT_CAINFO         => storage_path('cacert.pem'),
                CURLOPT_HTTPHEADER     => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36',
                    'Accept: text/html',
                ],
                CURLOPT_RANGE => '0-32768', // Only first 32KB
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$url] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running > 0) {
                curl_multi_select($mh, 0.5);
            }
        } while ($running > 0);

        $enriched = 0;
        foreach ($handles as $url => $ch) {
            $html = curl_multi_getcontent($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cacheKey = 'og_img_' . md5($url);
            $ogImage = null;

            if ($status == 200 && $html) {
                // Try og:image
                if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\'](https?:\/\/[^"\']+)["\']/i', $html, $m)) {
                    $ogImage = $m[1];
                }
                // Fallback: twitter:image
                elseif (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\'](https?:\/\/[^"\']+)["\']/i', $html, $m)) {
                    $ogImage = $m[1];
                }
                // Reversed attribute order
                elseif (preg_match('/<meta[^>]+content=["\'](https?:\/\/[^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
                    $ogImage = $m[1];
                }
            }

            // Cache result for 2 hours (BUG-019 fix)
            Cache::put($cacheKey, $ogImage ?? '', 7200);
            if ($ogImage) $enriched++;

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        if ($enriched > 0) {
            Log::info("EnrichNewsImages Job: Enriched $enriched articles with thumbnails.");
        }
    }
}
