<?php

namespace App\Console\Commands\Sports;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchNhlTeams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sports:fetch-nhl-teams';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch NHL teams from the NHL API and store in database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching NHL teams...');

        try {
            // NHL API endpoint for teams
            $response = Http::timeout(15)->get('https://api-web.nhle.com/v1/standings/now');

            if (!$response->ok()) {
                $this->error('Failed to fetch NHL teams from API');
                return 1;
            }

            $data = $response->json();
            $standings = $data['standings'] ?? [];

            $teamsProcessed = 0;

            foreach ($standings as $standing) {
                $teamAbbrev = $standing['teamAbbrev']['default'] ?? null;
                $teamName = $standing['teamName']['default'] ?? null;

                if (!$teamAbbrev || !$teamName) {
                    continue;
                }

                // NHL logo URL pattern: https://assets.nhle.com/logos/nhl/svg/{ABBREV}_light.svg
                $logoUrl = "https://assets.nhle.com/logos/nhl/svg/{$teamAbbrev}_light.svg";

                Team::updateOrCreate(
                    [
                        'league' => 'NHL',
                        'name' => $teamName,
                    ],
                    [
                        'abbreviation' => $teamAbbrev,
                        'logo_url' => $logoUrl,
                    ]
                );

                $teamsProcessed++;
            }

            $this->info("Successfully processed {$teamsProcessed} NHL teams");
            return 0;

        } catch (\Exception $e) {
            $this->error('Error fetching NHL teams: ' . $e->getMessage());
            return 1;
        }
    }
}
