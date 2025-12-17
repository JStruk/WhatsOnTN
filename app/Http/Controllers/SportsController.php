<?php

namespace App\Http\Controllers;

use App\Http\Resources\TodayEventResource;
use App\Services\Sports\TodaySportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

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

    public function indexV2(Request $request, TodaySportsService $service): Response
    {
        $timezone = $request->query('timezone', 'America/New_York');
        $events = $service->getTodayEvents($timezone);

        return Inertia::render('EventsDashboardV2', [
            'initialEvents' => TodayEventResource::collection($events)->resolve(),
            'initialTimezone' => $timezone,
        ]);
    }

    public function refresh(Request $request): RedirectResponse
    {
        // Clear all sports cache entries for today
        $timezone = $request->query('timezone', 'America/New_York');
        $todayInTimezone = now($timezone)->toDateString();
        $cacheKey = "sports:today:{$todayInTimezone}:{$timezone}";

        Cache::forget($cacheKey);

        // Redirect back to the sports page with the timezone parameter
        return redirect()->route('sports.today', ['timezone' => $timezone]);
    }

    public function refreshV2(Request $request): RedirectResponse
    {
        // Clear all sports cache entries for today
        $timezone = $request->query('timezone', 'America/New_York');
        $todayInTimezone = now($timezone)->toDateString();
        $cacheKey = "sports:today:{$todayInTimezone}:{$timezone}";

        Cache::forget($cacheKey);

        // Redirect back to the V2 events page with the timezone parameter
        return redirect()->route('events.v2', ['timezone' => $timezone]);
    }
}


