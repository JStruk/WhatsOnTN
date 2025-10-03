<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Sports API
Route::get('/api/sports/today', [\App\Http\Controllers\SportsController::class, 'today'])->name('api.sports.today');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Sports page route
Route::get('/sports/today', function () {
    return Inertia::render('TodaySports');
})->name('sports.today');
