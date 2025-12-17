<?php

namespace App\Jobs;

use App\Models\Game;
use App\Models\Team;
use App\Services\Sports\TodaySportsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class FetchNhlGames implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $date
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(TodaySportsService $service): void
    {
        $events = $service->fetchNhl($this->date, 'America/New_York');

        foreach ($events as $event) {
            $this->storeGame($event);
        }
    }

    protected function storeGame(array $event): void
    {
        // Lookup teams from the teams table
        $homeTeam = Team::where('league', $event['league'])
            ->where('name', $event['homeTeam'] ?? '')
            ->first();

        $awayTeam = Team::where('league', $event['league'])
            ->where('name', $event['awayTeam'] ?? '')
            ->first();

        Game::query()->updateOrCreate(
            [
                'league' => $event['league'],
                'external_id' => $event['id'],
                'game_date' => Carbon::parse($event['startTimeUTC'])->toDateString(),
            ],
            [
                'status' => $event['status'] ?? 'scheduled',
                'start_time_utc' => $event['startTimeUTC'],
                'venue' => $event['venue'] ?? null,
                'venue_timezone' => $event['venueTimezone'] ?? null,
                'home_team' => $event['homeTeam'] ?? '',
                'away_team' => $event['awayTeam'] ?? '',
                'home_team_id' => $homeTeam?->id,
                'away_team_id' => $awayTeam?->id,
                'home_score' => $event['homeScore'] ?? 0,
                'away_score' => $event['awayScore'] ?? 0,
                'link' => $event['link'] ?? null,
            ]
        );
    }
}

