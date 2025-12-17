<?php

namespace App\Console\Commands\Sports;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchMlbTeams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sports:fetch-mlb-teams';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch MLB teams from the ESPN API and store in database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching MLB teams...');

        try {
            // ESPN MLB teams endpoint (includes logos)
            $response = Http::timeout(15)->get('https://site.api.espn.com/apis/site/v2/sports/baseball/mlb/teams');

            if (!$response->ok()) {
                $this->error('Failed to fetch MLB teams from API');
                return 1;
            }

            $data = $response->json();
            $teams = $data['sports'][0]['leagues'][0]['teams'] ?? [];

            $teamsProcessed = 0;

            foreach ($teams as $teamWrapper) {
                $team = $teamWrapper['team'] ?? null;

                if (!$team) {
                    continue;
                }

                $teamName = $team['displayName'] ?? null;
                $teamAbbrev = $team['abbreviation'] ?? null;
                $logoUrl = $team['logos'][0]['href'] ?? null;

                if (!$teamName || !$teamAbbrev) {
                    continue;
                }

                Team::updateOrCreate(
                    [
                        'league' => 'MLB',
                        'name' => $teamName,
                    ],
                    [
                        'abbreviation' => $teamAbbrev,
                        'logo_url' => $logoUrl,
                    ]
                );

                $teamsProcessed++;
            }

            $this->info("Successfully processed {$teamsProcessed} MLB teams");
            return 0;

        } catch (\Exception $e) {
            $this->error('Error fetching MLB teams: ' . $e->getMessage());
            return 1;
        }
    }
}
