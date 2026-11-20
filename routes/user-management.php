<?php

use App\Http\Controllers\UserManagement\DirectoryController;
use App\Http\Controllers\UserManagement\RoleController;
use App\Http\Controllers\UserManagement\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-users'])
    ->prefix('user-management')
    ->name('user-management.')
    ->group(function (): void {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');

        Route::get('directory', [DirectoryController::class, 'index'])->name('directory');
        Route::post('directory/{user}', [DirectoryController::class, 'updateUserRoles'])->name('directory.store');

        Route::get('users', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('users', [UserManagementController::class, 'storeUser'])->name('users.store');
        Route::put('users/{user}', [DirectoryController::class, 'updateUser'])->name('users.update');
        Route::put('users/{user}/roles', [DirectoryController::class, 'updateUserRoles'])->name('users.roles.update');
        Route::put('users/{user}/status', [DirectoryController::class, 'updateUserStatus'])->name('users.status.update');
        Route::delete('users/{user}', [DirectoryController::class, 'destroyUser'])->name('users.destroy');
        Route::post('users/{user}/restore', [DirectoryController::class, 'restoreUser'])->name('users.restore');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'storeRole'])->name('roles.store');
        Route::put('roles/{role}', [RoleController::class, 'updateRole'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroyRole'])->name('roles.destroy');
        Route::post('roles/{role}/restore', [RoleController::class, 'restoreRole'])->name('roles.restore');
    });
