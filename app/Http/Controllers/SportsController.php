<?php

namespace App\Http\Controllers;

use App\Services\Sports\TodaySportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SportsController extends Controller
{
    public function today(Request $request, TodaySportsService $service): JsonResponse
    {
        $date = $request->query('date');
        $timezone = $request->query('timezone', 'America/New_York'); // Default to Eastern for North American sports
        $events = $service->getTodayEvents($date, $timezone);

        return response()->json([
            'date' => $date ?? now()->timezone(config('app.timezone'))->toDateString(),
            'timezone' => $timezone,
            'events' => $events,
        ]);
    }
}


