<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Http\Resources\TodayEventResource;
use App\Services\Sports\TodaySportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodayController extends Controller
{
    public function __invoke(Request $request, TodaySportsService $service): JsonResponse
    {
        $timezone = $request->query('timezone', 'America/New_York');
        $events = $service->getTodayEvents($timezone);

        return TodayEventResource::collection($events)->response();
    }
}
