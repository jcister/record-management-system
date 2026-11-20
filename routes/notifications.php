<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('notifications', static function () {
        return redirect()->route('user-notifications.index');
    })->name('notifications.index');
});
