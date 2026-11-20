<?php

use App\Models\DisposalBatch;
use App\Models\DisposalBatchFile;
use App\Models\File;
use App\Models\User;
use App\Support\RetentionStatus;

use function Pest\Laravel\actingAs;

test('it shows file information for completed batches even when files are soft deleted', function () {
    $user = User::factory()->create();

    $file = File::factory()->create([
        'record_id' => 'TEST-001',
        'file_name' => 'Test Document.pdf',
        'author' => 'Test Author',
        'physical_location' => 'Cabinet A',
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'file_count' => 1,
    ]);

    DisposalBatchFile::create([
        'batch_id' => $batch->id,
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now(),
    ]);

    // Complete the batch (files remain in ready_for_disposal until approval)
    actingAs($user)
        ->post(route('disposal-batches.complete', $batch))
        ->assertRedirect();

    // Verify file remains in ready_for_disposal state and not trashed
    $file->refresh();
    expect($file->trashed())->toBeFalse();
    expect($file->retention_status)->toBe(RetentionStatus::READY_FOR_DISPOSAL);

    // View the completed batch
    $response = actingAs($user)
        ->get(route('disposal-batches.show', $batch));

    $response->assertSuccessful();

    // Verify file information is still displayed
    $items = $response->viewData('page')['props']['batch']['items'];
    expect($items)->toHaveCount(1);
    expect($items[0]['file'])->not->toBeNull();
    expect($items[0]['file']['record_id'])->toBe('TEST-001');
    expect($items[0]['file']['file_name'])->toBe('Test Document.pdf');
    expect($items[0]['file']['author'])->toBe('Test Author');
});
