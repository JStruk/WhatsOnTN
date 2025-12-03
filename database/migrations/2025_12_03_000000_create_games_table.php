<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();

            // Basic identifying information
            $table->string('league', 10); // e.g. NHL, NBA, MLB, NFL
            $table->string('external_id')->nullable(); // ID from upstream API

            $table->date('game_date');
            $table->datetime('start_time_utc')->nullable();

            // Status and metadata
            $table->string('status', 20)->default('scheduled');
            $table->string('venue')->nullable();
            $table->string('venue_timezone')->nullable();

            // Teams and scores
            $table->string('home_team');
            $table->string('away_team');
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();

            $table->string('link')->nullable();

            $table->timestamps();

            $table->unique(['league', 'external_id', 'game_date'], 'games_league_external_date_unique');
            $table->index(['league', 'game_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};


