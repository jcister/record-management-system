<?php

use App\Http\Controllers\Auth\ForcePasswordResetController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Home redirect
Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    $user = Auth::user();

    if ($user->can('organize-content')) {
        return redirect()->route('dashboard');
    }

    return redirect('/requests/myrequests');
})->name('home');

// Registration is disabled; redirect to login
Route::get('/register', fn () => redirect()->route('login'))->name('register');
Route::post('/register', fn () => redirect()->route('login'))->name('register.store');

// Serve public storage files (e.g. avatars) when symlink is missing or server blocks direct access
Route::get('/storage/{path}', function (string $path) {
    $path = str_replace(['..', '\\'], ['', '/'], $path);
    if (! Storage::disk('public')->exists($path)) {
        abort(404);
    }

    return Storage::disk('public')->response($path);
})->where('path', '.*')->name('storage.serve');

// Google OAuth routes
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

// Force password reset routes
Route::middleware(['auth'])->group(function (): void {
    Route::get('/auth/force-password-reset', [ForcePasswordResetController::class, 'show'])->name('force-password-reset.show');
    Route::post('/auth/force-password-reset', [ForcePasswordResetController::class, 'update'])->name('force-password-reset.update');
});
