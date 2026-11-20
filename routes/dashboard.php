<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SearchDebugController;
use App\Http\Controllers\SearchSuggestionController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'can:organize-content'])
    ->name('dashboard');

Route::get('search/suggestions', SearchSuggestionController::class)
    ->middleware(['auth', 'verified'])
    ->name('search.suggestions');

// Debug route (remove in production)
Route::get('debug/search', SearchDebugController::class)
    ->middleware(['auth', 'verified'])
    ->name('debug.search');
