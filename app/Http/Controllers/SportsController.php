<?php

namespace App\Http\Controllers;

use App\Http\Resources\TodayEventResource;
use App\Services\Sports\TodaySportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SportsController extends Controller
{
    public function index(Request $request, TodaySportsService $service): Response
    {
        $timezone = $request->query('timezone', 'America/New_York');
        $events = $service->getTodayEvents($timezone);

        return Inertia::render('TodaySports', [
            'initialEvents' => $events,
            'initialTimezone' => $timezone,
        ]);
    }

    public function today(Request $request, TodaySportsService $service): JsonResponse
    {
        $date = $request->query('date');
        $timezone = $request->query('timezone', 'America/New_York'); // Default to Eastern for North American sports
        $events = $service->getTodayEvents($timezone);

        return response()->json([
            'date' => $date ?? now()->timezone(config('app.timezone'))->toDateString(),
            'timezone' => $timezone,
            'events' => $events,
        ]);
    }
}


