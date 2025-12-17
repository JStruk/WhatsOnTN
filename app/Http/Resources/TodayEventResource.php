<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TodayEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'] ?? '',
            'sport' => $this->resource['league'] ?? null,
            'league' => $this->resource['league'] ?? null,
            'status' => $this->resource['status'] ?? 'scheduled',
            'homeTeam' => $this->resource['homeTeam'] ?? null,
            'awayTeam' => $this->resource['awayTeam'] ?? null,
            'homeScore' => $this->resource['homeScore'] ?? 0,
            'awayScore' => $this->resource['awayScore'] ?? 0,
            'homeTeamLogo' => $this->resource['homeTeamLogo'] ?? null,
            'awayTeamLogo' => $this->resource['awayTeamLogo'] ?? null,
            'venue' => $this->resource['venue'] ?? null,
            'venueTimezone' => $this->resource['venueTimezone'] ?? null,
            'city' => $this->extractCity($this->resource['venue'] ?? null),
            'startTime' => $this->resource['startTime'] ?? null,
            'startTimeUTC' => $this->resource['startTimeUTC'] ?? null,
            'link' => $this->resource['link'] ?? null,
            'isLive' => ($this->resource['status'] ?? 'scheduled') === 'live',
            'broadcast' => $this->resource['link'] ?? null,
            'statusText' => $this->resource['status'] ?? 'scheduled',
        ];
    }

    /**
     * Extract city from venue string if possible.
     * This is a simple implementation - can be enhanced based on venue format.
     */
    private function extractCity(?string $venue): ?string
    {
        if (!$venue) {
            return null;
        }

        // Try to extract city from common venue formats
        // Format: "Venue Name, City" or "City Venue Name"
        // For now, return null as we don't have city data in the database
        return null;
    }
}


