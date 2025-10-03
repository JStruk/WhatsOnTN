<?php

namespace App\Services\Sports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TodaySportsService
{
    /**
     * Fetch and normalize today's events across major leagues.
     *
     * @param string|null $date Y-m-d in app timezone, defaults to today
     * @param string $timezone Timezone for date filtering (defaults to America/New_York for North American sports)
     * @return array<int, array<string, mixed>>
     */
    public function getTodayEvents(?string $date = null, string $timezone = 'America/New_York'): array
    {
        $dateLocal = $date ? Carbon::parse($date, config('app.timezone')) : Carbon::now(config('app.timezone'));
        $dateStr = $dateLocal->format('Y-m-d');

        $cacheKey = "sports:today:{$dateStr}:{$timezone}";

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($dateStr, $timezone) {
            $events = [];

            // Fetch in sequence to keep it simple; endpoints are fast and cached
            $events = array_merge(
                $events,
                $this->fetchNhl($dateStr, $timezone),
                $this->fetchNba($dateStr),
                $this->fetchMlb($dateStr),
                $this->fetchNfl($dateStr)
            );

            // Sort by start time
            usort($events, function ($a, $b) {
                return strcmp($a['startTime'], $b['startTime']);
            });

            return $events;
        });
    }

    /**
     * NHL: https://api-web.nhle.com/v1/schedule/YYYY-MM-DD
     */
    protected function fetchNhl(string $date, string $timezone = 'America/New_York'): array
    {
        $url = "https://api-web.nhle.com/v1/schedule/{$date}";
        $response = Http::timeout(10)->retry(1, 200)->get($url);

        if (!$response->ok()) {
            return [];
        }

        $json = $response->json();
        $gameWeeks = $json['gameWeek'] ?? [];
        $events = [];

        foreach ($gameWeeks as $day) {
            foreach ($day['games'] ?? [] as $game) {
                // Only include games that occur on the requested $date in the specified timezone
                // This ensures games are grouped by their "local" date for the user's timezone
                $gameDateLocal = null;
                if (!empty($game['startTimeUTC'])) {
                    try {
                        $gameDateLocal = Carbon::parse($game['startTimeUTC'])
                            ->timezone($timezone)
                            ->toDateString();
                    } catch (\Throwable $e) {
                        $gameDateLocal = null;
                    }
                }

                if ($gameDateLocal !== $date) {
                    continue;
                }

                $homeTeam = $game['homeTeam'] ?? [];
                $awayTeam = $game['awayTeam'] ?? [];

                $homeName = trim(implode(' ', array_filter([
                    $homeTeam['placeName']['default'] ?? null,
                    $homeTeam['commonName']['default'] ?? null,
                ])));
                $awayName = trim(implode(' ', array_filter([
                    $awayTeam['placeName']['default'] ?? null,
                    $awayTeam['commonName']['default'] ?? null,
                ])));

                $events[] = [
                    'id' => (string)($game['id'] ?? ''),
                    'league' => 'NHL',
                    'status' => $this->normalizeStatusNhl($game['gameState'] ?? ''),
                    'startTime' => $this->toIso($game['startTimeUTC'] ?? null),
                    'startTimeUTC' => $game['startTimeUTC'] ?? null,
                    'venue' => $game['venue']['default'] ?? null,
                    'venueTimezone' => $game['venueTimezone'] ?? null,
                    'homeTeam' => $homeName !== '' ? $homeName : ($homeTeam['abbrev'] ?? ''),
                    'awayTeam' => $awayName !== '' ? $awayName : ($awayTeam['abbrev'] ?? ''),
                    'homeScore' => 0, // Scores are not included in schedule payload
                    'awayScore' => 0,
                    'link' => isset($game['gameCenterLink']) ? ('https://www.nhl.com' . $game['gameCenterLink']) : null,
                ];
            }
        }

        return $events;
    }

    protected function normalizeStatusNhl(string $state): string
    {
        $stateUpper = strtoupper($state);
        if (str_contains($stateUpper, 'FINAL')) {
            return 'final';
        }
        if (in_array($stateUpper, ['LIVE', 'IN_PROGRESS', 'IN PROGRESS'])) {
            return 'live';
        }
        return 'scheduled';
    }

    /**
     * NBA: https://cdn.nba.com/static/json/liveData/scoreboard/todaysScoreboard_00.json
     * This endpoint ignores date parameter and always returns today (ET). We'll still pass along for consistency.
     */
    protected function fetchNba(string $date): array
    {
        $url = 'https://cdn.nba.com/static/json/liveData/scoreboard/todaysScoreboard_00.json';
        $response = Http::timeout(10)->retry(1, 200)->get($url);
        if (!$response->ok()) {
            return [];
        }
        $json = $response->json();
        $games = $json['scoreboard']['games'] ?? [];
        $events = [];
        foreach ($games as $game) {
            $events[] = [
                'id' => (string)($game['gameId'] ?? ''),
                'league' => 'NBA',
                'status' => $this->normalizeStatusNba($game['gameStatusText'] ?? ''),
                'startTime' => $this->toIso($game['gameEt'] ?? null),
                'venue' => $game['arenaName'] ?? null,
                'homeTeam' => $game['homeTeam']['teamName'] ?? '',
                'awayTeam' => $game['awayTeam']['teamName'] ?? '',
                'homeScore' => (int)($game['homeTeam']['score'] ?? 0),
                'awayScore' => (int)($game['awayTeam']['score'] ?? 0),
                'link' => null,
            ];
        }
        return $events;
    }

    protected function normalizeStatusNba(string $text): string
    {
        $t = strtoupper($text);
        if (str_contains($t, 'FINAL')) {
            return 'final';
        }
        if (preg_match('/\d{1,2}:\d{2} [1-4][A-Z]?|Q[1-4]|OT/i', $text)) {
            return 'live';
        }
        return 'scheduled';
    }

    /**
     * MLB: https://statsapi.mlb.com/api/v1/schedule?sportId=1&date=YYYY-MM-DD&hydrate=team,linescore
     */
    protected function fetchMlb(string $date): array
    {
        $url = 'https://statsapi.mlb.com/api/v1/schedule';
        $response = Http::timeout(10)->retry(1, 200)->get($url, [
            'sportId' => 1,
            'date' => $date,
            'hydrate' => 'team,linescore',
        ]);
        if (!$response->ok()) {
            return [];
        }
        $json = $response->json();
        $dates = $json['dates'] ?? [];
        $events = [];
        foreach ($dates as $d) {
            foreach (($d['games'] ?? []) as $game) {
                $home = $game['teams']['home'] ?? [];
                $away = $game['teams']['away'] ?? [];
                $events[] = [
                    'id' => (string)($game['gamePk'] ?? ''),
                    'league' => 'MLB',
                    'status' => $this->normalizeStatusMlb($game['status']['detailedState'] ?? ''),
                    'startTime' => $this->toIso($game['gameDate'] ?? null),
                    'venue' => $game['venue']['name'] ?? null,
                    'homeTeam' => $home['team']['name'] ?? '',
                    'awayTeam' => $away['team']['name'] ?? '',
                    'homeScore' => (int)($home['score'] ?? 0),
                    'awayScore' => (int)($away['score'] ?? 0),
                    'link' => isset($game['link']) ? ('https://www.mlb.com' . $game['link']) : null,
                ];
            }
        }
        return $events;
    }

    protected function normalizeStatusMlb(string $state): string
    {
        $u = strtoupper($state);
        if (str_contains($u, 'FINAL')) {
            return 'final';
        }
        if (in_array($u, ['IN PROGRESS', 'LIVE'])) {
            return 'live';
        }
        return 'scheduled';
    }

    /**
     * NFL: Use ESPN public scoreboard for a given date
     * https://site.api.espn.com/apis/v2/sports/football/nfl/scoreboard?dates=YYYYMMDD
     */
    protected function fetchNfl(string $date): array
    {
        $ymd = Carbon::parse($date, config('app.timezone'))->format('Ymd');
        $url = 'https://site.api.espn.com/apis/v2/sports/football/nfl/scoreboard';
        $response = Http::timeout(10)->retry(1, 200)->get($url, [
            'dates' => $ymd,
        ]);
        if (!$response->ok()) {
            return [];
        }
        $json = $response->json();
        $events = [];

        foreach ($json['events'] ?? [] as $event) {
            $competitions = $event['competitions'][0] ?? [];
            $competitors = $competitions['competitors'] ?? [];
            $home = collect($competitors)->firstWhere('homeAway', 'home') ?? [];
            $away = collect($competitors)->firstWhere('homeAway', 'away') ?? [];
            $events[] = [
                'id' => (string)($event['id'] ?? ''),
                'league' => 'NFL',
                'status' => $this->normalizeStatusEspn($competitions['status']['type']['name'] ?? ''),
                'startTime' => $this->toIso($event['date'] ?? null),
                'venue' => $competitions['venue']['fullName'] ?? null,
                'homeTeam' => $home['team']['displayName'] ?? '',
                'awayTeam' => $away['team']['displayName'] ?? '',
                'homeScore' => isset($home['score']) ? (int)$home['score'] : 0,
                'awayScore' => isset($away['score']) ? (int)$away['score'] : 0,
                'link' => $event['links'][0]['href'] ?? null,
            ];
        }

        return $events;
    }

    protected function normalizeStatusEspn(string $name): string
    {
        $u = strtoupper($name);
        if (in_array($u, ['STATUS_FINAL', 'FINAL'])) {
            return 'final';
        }
        if (in_array($u, ['STATUS_IN_PROGRESS', 'IN_PROGRESS', 'LIVE'])) {
            return 'live';
        }
        return 'scheduled';
    }

    protected function toIso(?string $dateTime): string
    {
        if (!$dateTime) {
            return Carbon::now()->toIso8601String();
        }
        try {
            return Carbon::parse($dateTime)->toIso8601String();
        } catch (\Throwable $e) {
            return Carbon::now()->toIso8601String();
        }
    }
}


