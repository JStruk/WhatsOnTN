<?php

namespace App\Services\Sports;

use App\Models\Game;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TodaySportsService
{
    /**
     * Fetch today's events from the database across major leagues.
     *
     * @param string|null $date Y-m-d in app timezone, defaults to today
     * @param string $timezone Timezone for date filtering (defaults to America/New_York for North American sports)
     * @return array<int, array<string, mixed>>
     */
    public function getTodayEvents(string $timezone = 'America/New_York'): array
    {
        // Get the start and end of "today" in the requested timezone
        $startOfDay = Carbon::now($timezone)->startOfDay()->utc();
        $endOfDay = Carbon::now($timezone)->endOfDay()->utc();
        $todayInTimezone = Carbon::now($timezone)->toDateString();
        $cacheKey = "sports:today:{$todayInTimezone}:{$timezone}";

        return Cache::remember($cacheKey, now()->addHour(), static function () use ($startOfDay, $endOfDay, $timezone) {
            // Get all games where start_time_utc falls within "today" in the user's timezone
            $games = Game::query()
                ->where('start_time_utc', '>=', $startOfDay)
                ->where('start_time_utc', '<=', $endOfDay)
                ->orderBy('start_time_utc')
                ->get();

            $events = [];

            foreach ($games as $game) {
                // Format startTime as ISO8601
                $startTime = $game->start_time_utc
                    ? Carbon::parse($game->start_time_utc)->toIso8601String()
                    : Carbon::now()->toIso8601String();

                $events[] = [
                    'id' => $game->external_id ?? '',
                    'league' => $game->league,
                    'status' => $game->status ?? 'scheduled',
                    'startTime' => $startTime,
                    'startTimeUTC' => $game->start_time_utc,
                    'venue' => $game->venue,
                    'venueTimezone' => $game->venue_timezone,
                    'homeTeam' => $game->home_team ?? '',
                    'awayTeam' => $game->away_team ?? '',
                    'homeScore' => $game->home_score ?? 0,
                    'awayScore' => $game->away_score ?? 0,
                    'link' => $game->link,
                ];
            }

            return $events;
        });
    }

    /**
     * NHL: https://api-web.nhle.com/v1/schedule/YYYY-MM-DD
     */
    public function fetchNhl(string $date, string $timezone = 'America/New_York'): array
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
                    'id' => (string) ($game['id'] ?? ''),
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
     * Note: gameEt appears to be in UTC format but represents Eastern Time games.
     */
    public function fetchNba(): array
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
                'id' => (string) ($game['gameId'] ?? ''),
                'league' => 'NBA',
                'status' => $this->normalizeStatusNba($game['gameStatusText'] ?? ''),
                'startTime' => $this->toIsoFromNbaUtc($game['gameEt'] ?? null),
                'startTimeUTC' => $game['gameTimeUTC'],
                'venue' => $game['arenaName'] ?? null,
                'homeTeam' => $game['homeTeam']['teamName'] ?? '',
                'awayTeam' => $game['awayTeam']['teamName'] ?? '',
                'homeScore' => (int) ($game['homeTeam']['score'] ?? 0),
                'awayScore' => (int) ($game['awayTeam']['score'] ?? 0),
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
    public function fetchMlb(string $date): array
    {
        //TODO fix start time UTC
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
                    'id' => (string) ($game['gamePk'] ?? ''),
                    'league' => 'MLB',
                    'status' => $this->normalizeStatusMlb($game['status']['detailedState'] ?? ''),
                    'startTime' => $this->toIso($game['gameDate'] ?? null),
                    'venue' => $game['venue']['name'] ?? null,
                    'homeTeam' => $home['team']['name'] ?? '',
                    'awayTeam' => $away['team']['name'] ?? '',
                    'homeScore' => (int) ($home['score'] ?? 0),
                    'awayScore' => (int) ($away['score'] ?? 0),
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
     * NFL: Use ESPN Partners API for a given date
     * https://partners.api.espn.com/v2/sports/football/nfl/events?dates=YYYYMMDD-YYYYMMDD
     * Note: The API returns games for the specified date
     */
    public function fetchNfl(string $date): array
    {
        // Convert the requested date to the same day for the API call
        // The ESPN Partners API returns games for the specified date
        $apiDate = Carbon::parse($date, config('app.timezone'))->format('Ymd');
        $url = 'https://partners.api.espn.com/v2/sports/football/nfl/events';
        $response = Http::timeout(10)->retry(1, 200)->get($url, [
            'dates' => "{$apiDate}-{$apiDate}",
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
                'id' => (string) ($event['id'] ?? ''),
                'league' => 'NFL',
                'status' => $this->normalizeStatusEspn($competitions['status']['type']['name'] ?? ''),
                'startTime' => $this->toIso($event['date'] ?? null),
                'venue' => $competitions['venue']['fullName'] ?? null,
                'homeTeam' => $home['team']['displayName'] ?? '',
                'awayTeam' => $away['team']['displayName'] ?? '',
                'homeScore' => isset($home['score']['value']) ? (int) $home['score']['value'] : 0,
                'awayScore' => isset($away['score']['value']) ? (int) $away['score']['value'] : 0,
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

    /**
     * Convert NBA's UTC-formatted time to proper Eastern Time ISO format.
     * NBA API returns times like "2025-10-22T19:00:00Z" but these represent Eastern Time games.
     */
    protected function toIsoFromNbaUtc(?string $dateTime): string
    {
        if (!$dateTime) {
            return Carbon::now()->toIso8601String();
        }
        try {
            // Remove the 'Z' suffix and parse as Eastern Time
            $easternTime = str_replace('Z', '', $dateTime);
            return Carbon::parse($easternTime, 'America/New_York')->utc()->toIso8601String();
        } catch (\Throwable $e) {
            return Carbon::now()->toIso8601String();
        }
    }

}


