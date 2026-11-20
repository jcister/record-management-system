<?php

use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:organize-content'])
    ->prefix('upload')
    ->name('upload.')
    ->group(function (): void {
        Route::get('/', [FileController::class, 'create'])->name('form');
        Route::post('/', [FileController::class, 'store'])->name('store');
    });
