<?php

use App\Jobs\FetchMlbGames;
use App\Jobs\FetchNbaGames;
use App\Jobs\FetchNflGames;
use App\Jobs\FetchNhlGames;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sports:fetch-games {date?}', function (?string $date = null) {
    $date = $date ?: Carbon::now(config('app.timezone'))->format('Y-m-d');

    $this->info("Fetching games for {$date}...");

    FetchNhlGames::dispatch($date);
    FetchMlbGames::dispatch($date);
    FetchNflGames::dispatch($date);

    $this->info("Dispatched jobs for all leagues.");
})
    ->purpose('Fetch games for all leagues for a given date (defaults to today)');

Artisan::command('sports:fetch-nhl {date?}', function (?string $date = null) {
    $date = $date ?: Carbon::now(config('app.timezone'))->format('Y-m-d');

    $this->info("Fetching NHL games for {$date}...");
    FetchNhlGames::dispatch($date);
    $this->info("Dispatched NHL job.");
})->purpose('Fetch NHL games for a given date (defaults to today)');

Artisan::command('sports:fetch-nba {date?}', function (?string $date = null) {
    $date = $date ?: Carbon::now(config('app.timezone'))->format('Y-m-d');

    $this->info("Fetching NBA games for {$date}...");
    FetchNbaGames::dispatch($date);
    $this->info("Dispatched NBA job.");
})->purpose('Fetch NBA games for a given date (defaults to today)');

Artisan::command('sports:fetch-mlb {date?}', function (?string $date = null) {
    $date = $date ?: Carbon::now(config('app.timezone'))->format('Y-m-d');

    $this->info("Fetching MLB games for {$date}...");
    FetchMlbGames::dispatch($date);
    $this->info("Dispatched MLB job.");
})->purpose('Fetch MLB games for a given date (defaults to today)');

Artisan::command('sports:fetch-nfl {date?}', function (?string $date = null) {
    $date = $date ?: Carbon::now(config('app.timezone'))->format('Y-m-d');

    $this->info("Fetching NFL games for {$date}...");
    FetchNflGames::dispatch($date);
    $this->info("Dispatched NFL job.");
})->purpose('Fetch NFL games for a given date (defaults to today)');
