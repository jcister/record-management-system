<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('accountsettings', '/accountsettings/profile');

    Route::get('accountsettings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('accountsettings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('accountsettings/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('accountsettings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('accountsettings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('accountsettings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('accountsettings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::patch('accountsettings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');

    Route::get('accountsettings/notifications', [NotificationController::class, 'settingsPage'])->name('user-notifications.index');
});
