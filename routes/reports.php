<?php

use App\Http\Controllers\Reports\CustomReportBuilderController;
use App\Http\Controllers\Reports\DocumentAccessFrequencyReportController;
use App\Http\Controllers\Reports\RecordsInventoryReportController;
use App\Http\Controllers\Reports\ReportExportController;
use App\Http\Controllers\Reports\ReportsDashboardController;
use App\Http\Controllers\Reports\StorageUtilizationReportController;
use App\Http\Controllers\Reports\SummaryReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-reports'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', ReportsDashboardController::class)->name('index');

    Route::get('/records-inventory', RecordsInventoryReportController::class)->name('records-inventory');
    Route::get('/summary', SummaryReportController::class)->name('summary');
    Route::get('/storage-utilization', StorageUtilizationReportController::class)->name('storage-utilization');
    Route::get('/document-access-frequency', DocumentAccessFrequencyReportController::class)->name('document-access-frequency');
    Route::redirect('/retrieval-frequency', '/reports/document-access-frequency', 301)->name('retrieval-frequency');

    Route::get('/custom', [CustomReportBuilderController::class, 'index'])->name('custom');
    Route::post('/custom/preview', [CustomReportBuilderController::class, 'preview'])->name('custom.preview');

    Route::get('/export', ReportExportController::class)->name('export');
});
