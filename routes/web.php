<?php

use App\Http\Controllers\SportsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [SportsController::class, 'index'])->name('home');

// Sports API
Route::get('/api/sports/today', [SportsController::class, 'today'])->name('api.sports.today');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Sports page route
Route::get('/sports/today', [SportsController::class, 'index'])->name('sports.today');
