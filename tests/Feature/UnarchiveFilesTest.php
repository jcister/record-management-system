<?php

use App\Models\File;
use App\Models\User;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not expose the unarchive endpoint anymore', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post('/disposal-management/unarchive', [
            'file_ids' => ['00000000-0000-0000-0000-000000000000'],
        ])
        ->assertNotFound();
});

it('marks completed disposal records as disposed', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'status' => 'approved',
    ]);

    $this->actingAs($user)
        ->post('/disposal-management/complete', [
            'file_ids' => [$file->id],
        ])
        ->assertRedirect('/disposal-management');

    // Files remain in ready_for_disposal state until approval
    expect($file->fresh()->retention_status)->toBe(RetentionStatus::READY_FOR_DISPOSAL);
});
