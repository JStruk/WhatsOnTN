<?php

namespace App\Console\Commands\Sports;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchNbaTeams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sports:fetch-nba-teams';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch NBA teams from the NBA API and store in database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching NBA teams...');

        try {
            // ESPN NBA teams endpoint (more reliable than NBA CDN)
            $response = Http::timeout(15)->get('https://site.api.espn.com/apis/site/v2/sports/basketball/nba/teams');

            if (!$response->ok()) {
                $this->error('Failed to fetch NBA teams from API');
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
                        'league' => 'NBA',
                        'name' => $teamName,
                    ],
                    [
                        'abbreviation' => $teamAbbrev,
                        'logo_url' => $logoUrl,
                    ]
                );

                $teamsProcessed++;
            }

            $this->info("Successfully processed {$teamsProcessed} NBA teams");
            return 0;

        } catch (\Exception $e) {
            $this->error('Error fetching NBA teams: ' . $e->getMessage());
            return 1;
        }
    }
}
