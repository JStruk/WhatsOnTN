<?php

use App\Http\Controllers\SportsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

Route::get('/', [SportsController::class, 'index'])->name('home');

// Sports API
Route::get('/api/sports/today', [SportsController::class, 'today'])->name('api.sports.today');
Route::post('/sports/refresh', [SportsController::class, 'refresh'])->name('sports.refresh');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Sports page route
Route::get('/sports/today', [SportsController::class, 'index'])->name('sports.today');

// V2 Events Dashboard
Route::get('/v2', [SportsController::class, 'indexV2'])->name('events.v2');
Route::post('/v2/refresh', [SportsController::class, 'refreshV2'])->name('events.v2.refresh');
