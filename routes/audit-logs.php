<?php

use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:view-audit-trail'])
    ->prefix('audit-logs')
    ->name('audit-logs.')
    ->group(function (): void {
        Route::get('/', [AuditLogController::class, 'page'])->name('index');
        Route::get('/export', [AuditLogController::class, 'export'])->name('export');
    });
