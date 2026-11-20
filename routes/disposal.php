<?php

use App\Http\Controllers\DisposalCertificateLibraryController;
use App\Http\Controllers\DisposalManagementController;
use App\Http\Controllers\DisposalWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:disposal-management'])
    ->group(function (): void {
        Route::get('disposal-management', DisposalManagementController::class)
            ->name('disposal-management');

        Route::get('disposal-management/filter-options', [DisposalManagementController::class, 'filterOptions'])
            ->name('disposal-management.filter-options');

        Route::get('disposal-management/certificates', DisposalCertificateLibraryController::class)
            ->name('disposal-management.certificates');

        Route::post('disposal-management/schedule', [DisposalWorkflowController::class, 'schedule'])
            ->name('disposal-management.schedule');

        Route::post('disposal-management/complete', [DisposalWorkflowController::class, 'complete'])
            ->name('disposal-management.complete');
    });
