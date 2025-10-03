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
        $events = $service->getTodayEvents($date);

        return response()->json([
            'date' => $date ?? now()->timezone(config('app.timezone'))->toDateString(),
            'events' => $events,
        ]);
    }
}


