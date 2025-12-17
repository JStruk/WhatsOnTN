<?php

use App\Http\Controllers\SportsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

Route::get('/', [SportsController::class, 'indexV2'])->name('home');

// Sports API
Route::get('/api/sports/today', [SportsController::class, 'today'])->name('api.sports.today');
Route::post('/sports/refresh', [SportsController::class, 'refresh'])->name('sports.refresh');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Legacy Sports page route
Route::get('/legacy', [SportsController::class, 'index'])->name('sports.legacy');
Route::get('/sports/today', [SportsController::class, 'index'])->name('sports.today');

Route::post('/refresh', [SportsController::class, 'refreshV2'])->name('home.refresh');
