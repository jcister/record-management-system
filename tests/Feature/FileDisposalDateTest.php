<?php

use App\Models\DisposalBatch;
use App\Models\File;
use App\Models\User;
use App\Support\RetentionStatus;

it('returns disposal date from approved batch', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'retention_status' => RetentionStatus::DISPOSED,
    ]);

    $approvalDate = now()->subDays(5);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'approved_by' => $user->id,
        'approved_at' => $approvalDate,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_APPROVED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now()->subDays(10),
    ]);

    $file->refresh();

    expect($file->disposal_date)->not->toBeNull()
        ->and($file->disposal_date)->toBe($approvalDate->toIso8601String());
});

it('returns null for non-disposed files', function () {
    $file = File::factory()->create([
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    expect($file->disposal_date)->toBeNull();
});

it('returns null for disposed files without approved batch', function () {
    $user = User::factory()->create();
    $file = File::factory()->create([
        'retention_status' => RetentionStatus::DISPOSED,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_by' => $user->id,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now(),
    ]);

    $file->refresh();

    expect($file->disposal_date)->toBeNull();
});
