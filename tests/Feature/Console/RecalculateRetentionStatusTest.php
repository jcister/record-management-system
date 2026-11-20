<?php

use App\Models\File;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

it('recalculates retention statuses based on expiration date', function (): void {
    Carbon::setTestNow('2026-02-18 12:00:00');

    $expired = File::factory()->create([
        'retention_expires_at' => Carbon::now()->subDay()->toDateString(),
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    $future = File::factory()->create([
        'retention_expires_at' => Carbon::now()->addDays(10)->toDateString(),
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    artisan('retention:recalculate-status')->assertExitCode(0);

    expect($expired->fresh()->retention_status)->toBe(RetentionStatus::READY_FOR_DISPOSAL)
        ->and($future->fresh()->retention_status)->toBe(RetentionStatus::ACTIVE);

    Carbon::setTestNow();
});
