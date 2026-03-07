<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EconomicCalendarService extends BaseMarketService
{
    /**
     * Get economic calendar from ForexFactory (Reliable Free Source).
     */
    public function getEconomicCalendar(?string $from = null, ?string $to = null): array
    {
        // Cache for 1 hour as this doesn't change every minute
        return Cache::remember('economic_calendar_ff_v1', 3600, function () {
            try {
                // ForexFactory Weekly Calendar XML
                $response = Http::withOptions(['verify' => false])
                    ->timeout(15)
                    ->get('https://nfs.faireconomy.media/ff_calendar_thisweek.xml');

                if (!$response->successful()) {
                    Log::error("EconomicCalendarService FF Failure: " . $response->status());
                    return [];
                }

                $xmlText = $response->body();
                $xml = simplexml_load_string($xmlText);
                if (!$xml) return [];

                $events = [];
                foreach ($xml->event as $item) {
                    $country    = isset($item->country) ? (string)$item->country : 'TBD';
                    $currency   = isset($item->country) ? (string)$item->country : 'TBD';
                    $dateObj    = isset($item->date) ? (string)$item->date : '';
                    $timeObj    = isset($item->time) ? (string)$item->time : '';
                    $impact     = isset($item->impact) ? (string)$item->impact : 'low';
                    
                    $events[] = [
                        'event'     => isset($item->title) ? (string)$item->title : 'Unknown Event',
                        'country'   => $country,
                        'currency'  => $currency,
                        'date'      => $dateObj,
                        'time'      => $timeObj,
                        'timestamp' => $this->parseFFTime($dateObj, $timeObj),
                        'importance'=> strtolower($impact),
                        'actual'    => isset($item->actual) && (string)$item->actual !== '' ? (string)$item->actual : null,
                        'forecast'  => isset($item->forecast) ? (string)$item->forecast : '',
                        'previous'  => isset($item->previous) ? (string)$item->previous : '',
                        'unit'      => '',
                    ];
                }

                // Sort by timestamp
                usort($events, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

                Log::info("EconomicCalendarService: Fetched " . count($events) . " events from ForexFactory.");
                return $events;
            } catch (\Exception $e) {
                Log::error("EconomicCalendarService Error: " . $e->getMessage());
                return [];
            }
        });
    }

    private function parseFFTime(string $date, string $time): int
    {
        try {
            // Ensure US MM/DD/YYYY parsing by replacing dashes with slashes
            $dateClean = str_replace('-', '/', $date);
            
            if (empty($time) || strtolower($time) === 'all day' || strtolower($time) === 'tentative') {
                return strtotime("$dateClean America/New_York") ?: 0;
            }
            
            return strtotime("$dateClean $time America/New_York") ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
