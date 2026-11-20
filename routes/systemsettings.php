<?php

use App\Http\Controllers\SystemConfigurationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-agency-information'])->group(function () {
    Route::get('systemsettings/system-information', [SystemConfigurationController::class, 'index'])
        ->name('systemsettings.information');

    Route::get('systemsettings/lookup-management', [SystemConfigurationController::class, 'lookupManagement'])
        ->name('systemsettings.lookup-management');

    Route::post('systemsettings/system-information', [SystemConfigurationController::class, 'update'])
        ->name('systemsettings.information.update');
});
