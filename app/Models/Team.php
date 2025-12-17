<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'league',
        'abbreviation',
        'logo_url',
        'primary_color',
        'secondary_color',
    ];

    /**
     * Get all games where this team is the home team.
     */
    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'home_team_id');
    }

    /**
     * Get all games where this team is the away team.
     */
    public function awayGames(): HasMany
    {
        return $this->hasMany(Game::class, 'away_team_id');
    }

    /**
     * Get the logo URL or return a placeholder.
     */
    public function getLogoUrlAttribute($value): ?string
    {
        return $value;
    }
}
