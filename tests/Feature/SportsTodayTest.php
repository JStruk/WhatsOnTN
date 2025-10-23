<?php

use Illuminate\Support\Facades\Http;

it('correctly handles NBA UTC-formatted Eastern Time', function () {
    // Test with NBA's UTC format that actually represents Eastern Time
    $nbaTime = '2024-01-15T19:30:00Z'; // NBA says 7:30 PM but it's actually Eastern Time
    
    Http::fake([
        'cdn.nba.com/*' => Http::response([
            'scoreboard' => [
                'games' => [[
                    'gameId' => 'test-game',
                    'gameStatusText' => '7:30 PM ET',
                    'gameEt' => $nbaTime,
                    'arenaName' => 'Test Arena',
                    'homeTeam' => ['teamName' => 'Test Home', 'score' => '0'],
                    'awayTeam' => ['teamName' => 'Test Away', 'score' => '0'],
                ]],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/sports/today');
    $response->assertOk();

    $events = $response->json('events');
    $nbaEvent = collect($events)->firstWhere('league', 'NBA');
    
    expect($nbaEvent)->not->toBeNull();
    
    // The startTime should be converted from Eastern Time to UTC
    $startTime = new \DateTime($nbaEvent['startTime']);
    $expectedHour = $startTime->format('H');
    
    // Should be 00 (midnight) or 01 (1 AM) since 7:30 PM ET = 12:30 AM UTC or 1:30 AM UTC depending on DST
    expect($expectedHour)->toBeIn(['00', '01']);
});