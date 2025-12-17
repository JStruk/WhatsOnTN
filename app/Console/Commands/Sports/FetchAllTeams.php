<?php

namespace App\Console\Commands\Sports;

use Illuminate\Console\Command;

class FetchAllTeams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sports:fetch-all-teams';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all teams from NHL, NBA, MLB, and NFL APIs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching all sports teams...');
        $this->newLine();

        // Fetch NHL teams
        $this->call('sports:fetch-nhl-teams');
        $this->newLine();

        // Fetch NBA teams
        $this->call('sports:fetch-nba-teams');
        $this->newLine();

        // Fetch MLB teams
        $this->call('sports:fetch-mlb-teams');
        $this->newLine();

        // Fetch NFL teams
        $this->call('sports:fetch-nfl-teams');
        $this->newLine();

        $this->info('✓ All teams fetched successfully!');

        return 0;
    }
}
