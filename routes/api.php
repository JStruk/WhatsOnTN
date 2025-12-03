<?php

use App\Http\Controllers\Api\Events\TodayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', static fn(Request $request) => $request->user())
    ->middleware('auth:sanctum');

Route::get('/todays-events', TodayController::class);
