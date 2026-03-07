<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\EconomicCalendarService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class EconomicCalendarTest extends TestCase
{
    public function test_get_economic_calendar_returns_formatted_data()
    {
        // Mock ForexFactory XML Response
        $xmlResponse = '<?xml version="1.0" encoding="UTF-8"?>
<weeklyevents>
    <event>
        <title>Non-Farm Payrolls</title>
        <country>USD</country>
        <date>03-07-2026</date>
        <time>8:30am</time>
        <impact>High</impact>
        <forecast>200K</forecast>
        <previous>150K</previous>
    </event>
    <event>
        <title>CPI m/m</title>
        <country>EUR</country>
        <date>03-08-2026</date>
        <time>10:00am</time>
        <impact>Medium</impact>
        <forecast>0.2%</forecast>
        <previous>0.1%</previous>
    </event>
</weeklyevents>';

        Http::fake([
            'https://nfs.faireconomy.media/ff_calendar_thisweek.xml' => Http::response($xmlResponse, 200, ['Content-Type' => 'application/xml'])
        ]);

        Cache::flush();

        $service = app(EconomicCalendarService::class);
        $events = $service->getEconomicCalendar();

        $this->assertCount(2, $events);
        $this->assertEquals('Non-Farm Payrolls', $events[0]['event']);
        $this->assertEquals('high', $events[0]['importance']);
        $this->assertEquals('USD', $events[0]['country']);
        
        $this->assertEquals('EUR', $events[1]['currency']);
        $this->assertEquals('0.1%', $events[1]['previous']); // Check previous since actual is empty in FF initially
        $this->assertEquals('0.2%', $events[1]['forecast']);
    }
}
