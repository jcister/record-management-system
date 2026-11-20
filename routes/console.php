<?php

use App\Models\AuditLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cleanup:chunks')->dailyAt('02:00');
Schedule::command('model:prune', [
    '--model' => AuditLog::class,
])->dailyAt('03:00');
Schedule::command('retention:recalculate-status')->dailyAt('01:00');
Schedule::command('notifications:notify-approaching-disposals')->dailyAt('08:00');
Schedule::command('notifications:notify-retention-expiring')->dailyAt('08:00');
Schedule::command('notifications:notify-disposal-batch-schedule')->dailyAt('08:00');
