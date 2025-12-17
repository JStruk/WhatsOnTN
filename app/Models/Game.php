<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'league',
        'external_id',
        'game_date',
        'start_time_utc',
        'status',
        'venue',
        'venue_timezone',
        'home_team',
        'away_team',
        'home_score',
        'away_score',
        'link',
    ];

    protected function casts(): array
    {
        return [
            'game_date' => 'date',
            'start_time_utc' => 'datetime',
        ];
    }

    /**
     * Get the home team for this game.
     */
    public function homeTeam()
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * Get the away team for this game.
     */
    public function awayTeam()
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}


