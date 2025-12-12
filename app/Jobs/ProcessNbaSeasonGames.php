<?php

namespace App\Jobs;

use App\Models\Game;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ProcessNbaSeasonGames implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param array $games Array of game events to process
     */
    public function __construct(public array $games)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->games as $event) {
            $this->storeGame($event);
        }
    }

    /**
     * Store a single game in the database.
     */
    protected function storeGame(array $event): void
    {
        $gameDate = Carbon::parse($event['startTime'])->setTimezone(config('app.timezone'))->toDateString() ?? Carbon::now()->toDateString();

        Game::query()->updateOrCreate(
            [
                'league' => $event['league'],
                'external_id' => $event['id'],
                'game_date' => $gameDate,
            ],
            [
                'status' => $event['status'] ?? 'scheduled',
                'start_time_utc' => $event['startTime'],
                'venue' => $event['venue'] ?? null,
                'venue_timezone' => $event['venueTimezone'] ?? null,
                'home_team' => $event['homeTeam'] ?? '',
                'away_team' => $event['awayTeam'] ?? '',
                'home_score' => $event['homeScore'] ?? 0,
                'away_score' => $event['awayScore'] ?? 0,
                'link' => $event['link'] ?? null,
            ]
        );
    }
}
