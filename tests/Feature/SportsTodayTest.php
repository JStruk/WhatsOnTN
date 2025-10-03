<?php

use Illuminate\Support\Facades\Http;

it('returns normalized events for today', function () {
    // Fake external HTTP calls for NHL/NBA/MLB/NFL
    Http::fake([
        'statsapi.web.nhl.com/*' => Http::response([
            'dates' => [[
                'games' => [[
                    'gamePk' => 1,
                    'status' => ['detailedState' => 'Scheduled'],
                    'gameDate' => now()->toIso8601String(),
                    'venue' => ['name' => 'Test Arena'],
                    'teams' => [
                        'home' => ['team' => ['name' => 'Home NHL'], 'score' => 0],
                        'away' => ['team' => ['name' => 'Away NHL'], 'score' => 0],
                    ],
                    'link' => '/game/1',
                ]],
            ]], 200),
        'cdn.nba.com/*' => Http::response([
            'scoreboard' => [
                'games' => [[
                    'gameId' => '2',
                    'gameStatusText' => '7:00 PM ET',
                    'gameEt' => now()->toIso8601String(),
                    'arenaName' => 'NBA Arena',
                    'homeTeam' => ['teamName' => 'Home NBA', 'score' => '0'],
                    'awayTeam' => ['teamName' => 'Away NBA', 'score' => '0'],
                ]],
            ],
        ], 200),
        'statsapi.mlb.com/*' => Http::response([
            'dates' => [[
                'games' => [[
                    'gamePk' => 3,
                    'status' => ['detailedState' => 'Final'],
                    'gameDate' => now()->toIso8601String(),
                    'venue' => ['name' => 'MLB Park'],
                    'teams' => [
                        'home' => ['team' => ['name' => 'Home MLB'], 'score' => 3],
                        'away' => ['team' => ['name' => 'Away MLB'], 'score' => 2],
                    ],
                    'link' => '/game/3',
                ]],
            ]], 200),
        'site.api.espn.com/*' => Http::response([
            'events' => [[
                'id' => '4',
                'date' => now()->toIso8601String(),
                'links' => [['href' => 'https://espn.com/game/4']],
                'competitions' => [[
                    'status' => ['type' => ['name' => 'STATUS_IN_PROGRESS']],
                    'venue' => ['fullName' => 'NFL Stadium'],
                    'competitors' => [
                        ['homeAway' => 'home', 'team' => ['displayName' => 'Home NFL'], 'score' => 10],
                        ['homeAway' => 'away', 'team' => ['displayName' => 'Away NFL'], 'score' => 7],
                    ],
                ]],
            ]],
        ], 200),
    ]);

    $response = $this->getJson('/api/sports/today');
    $response->assertOk();

    $response->assertJsonStructure([
        'date',
        'events' => [
            ['id','league','status','startTime','homeTeam','awayTeam','homeScore','awayScore']
        ],
    ]);

    $leagues = collect($response->json('events'))->pluck('league');
    expect($leagues)->toContain('NHL');
    expect($leagues)->toContain('NBA');
    expect($leagues)->toContain('MLB');
    expect($leagues)->toContain('NFL');
});


