<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();

            $table->string('name'); // Full team name (e.g., "Edmonton Oilers")
            $table->string('league', 10); // NHL, NBA, MLB, NFL
            $table->string('abbreviation', 10); // Team abbreviation (e.g., "EDM")
            $table->string('logo_url')->nullable(); // URL to team logo
            $table->string('primary_color', 7)->nullable(); // Hex color code
            $table->string('secondary_color', 7)->nullable(); // Hex color code

            $table->timestamps();

            // Indexes
            $table->unique(['league', 'name']);
            $table->index('league');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
