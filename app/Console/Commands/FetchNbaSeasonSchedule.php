<?php

namespace App\Console\Commands;

use App\Jobs\ProcessNbaSeasonGames;
use App\Services\Sports\TodaySportsService;
use Illuminate\Console\Command;

class FetchNbaSeasonSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sports:fetch-nba-season {--chunk-size=75 : Number of games to process per job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch the entire NBA season schedule and store in database (run annually in early August)';

    /**
     * Execute the console command.
     */
    public function handle(TodaySportsService $service): int
    {
        $this->info('Fetching NBA season schedule...');

        $games = $service->fetchNbaSeasonSchedule();

        if (empty($games)) {
            $this->error('Failed to fetch NBA season schedule or no games found.');
            return self::FAILURE;
        }

        $totalGames = count($games);
        $chunkSize = (int) $this->option('chunk-size');

        $this->info("Found {$totalGames} games in the season schedule.");
        $this->info("Processing in chunks of {$chunkSize} games...");

        // Chunk the games array and dispatch jobs
        $chunks = array_chunk($games, $chunkSize);
        $totalChunks = count($chunks);

        $this->info("Dispatching {$totalChunks} jobs to the queue...");

        $progressBar = $this->output->createProgressBar($totalChunks);
        $progressBar->start();

        foreach ($chunks as $chunk) {
            ProcessNbaSeasonGames::dispatch($chunk);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Successfully dispatched {$totalChunks} jobs to process {$totalGames} games.");
        $this->info('Jobs are being processed in the background. Monitor your queue workers for progress.');

        return self::SUCCESS;
    }
}
