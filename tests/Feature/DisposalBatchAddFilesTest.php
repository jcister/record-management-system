<?php

use App\Models\DisposalAudit;
use App\Models\DisposalBatch;
use App\Models\DisposalBatchFile;
use App\Models\File;
use App\Models\User;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can fetch available files that are ready for disposal and not in a batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $readyFile = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    $archivedFile = File::factory()->create([
        'retention_status' => RetentionStatus::ARCHIVED,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
    ]);

    $batch = DisposalBatch::factory()->create(['status' => DisposalBatch::STATUS_SCHEDULED]);
    $fileInBatch = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);
    DisposalBatchFile::factory()->create([
        'batch_id' => $batch->id,
        'file_id' => $fileInBatch->id,
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->getJson("/disposal-batches/{$batch->id}/available-files");

    $response->assertOk()
        ->assertJsonCount(1, 'files')
        ->assertJsonPath('files.0.id', $readyFile->id);
});

it('omits non-record uploads from available files', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $record = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'is_record' => true,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'is_record' => false,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    $batch = DisposalBatch::factory()->create(['status' => DisposalBatch::STATUS_SCHEDULED]);

    $response = $this->actingAs($user)
        ->getJson("/disposal-batches/{$batch->id}/available-files");

    $response->assertOk()
        ->assertJsonCount(1, 'files')
        ->assertJsonPath('files.0.id', $record->id);
});

it('omits scan-prefixed files from available files', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $eligible = File::factory()->create([
        'record_id' => 'REC-ELIGIBLE',
        'status' => 'approved',
        'is_record' => true,
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
    ]);

    File::factory()->create([
        'record_id' => 'SCAN-EXCLUDED-1',
        'status' => 'pending',
        'is_record' => true,
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
    ]);

    File::factory()->create([
        'record_id' => 'SCAN-EXCLUDED-2',
        'status' => 'approved',
        'is_record' => true,
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
    ]);

    $batch = DisposalBatch::factory()->create(['status' => DisposalBatch::STATUS_SCHEDULED]);

    $response = $this->actingAs($user)
        ->getJson("/disposal-batches/{$batch->id}/available-files");

    $response->assertOk()
        ->assertJsonCount(1, 'files')
        ->assertJsonPath('files.0.id', $eligible->id);
});

it('can add files to a scheduled disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'file_count' => 0,
    ]);

    $file1 = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    $file2 = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    $response = $this->actingAs($user)
        ->post("/disposal-batches/{$batch->id}/add-files", [
            'file_ids' => [$file1->id, $file2->id],
        ]);

    $response->assertRedirect(route('disposal-batches.show', $batch));

    $batch->refresh();

    expect($batch->file_count)->toBe(2)
        ->and($batch->items)->toHaveCount(2)
        ->and(DisposalAudit::where('action', 'added_to_batch')->count())->toBe(2);
});

it('cannot add files that are not records', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'file_count' => 0,
    ]);

    $attachment = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'is_record' => false,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    $response = $this->actingAs($user)
        ->post("/disposal-batches/{$batch->id}/add-files", [
            'file_ids' => [$attachment->id],
        ]);

    $response->assertRedirect(route('disposal-batches.show', $batch));

    $batch->refresh();

    expect($batch->file_count)->toBe(0)
        ->and($batch->items)->toHaveCount(0);
});

it('cannot add files to a completed disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
    ]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    $response = $this->actingAs($user)
        ->post("/disposal-batches/{$batch->id}/add-files", [
            'file_ids' => [$file->id],
        ]);

    $response->assertRedirect(route('disposal-batches.show', $batch));

    $batch->refresh();

    expect($batch->file_count)->toBe(1)
        ->and($batch->items)->toHaveCount(0);
});

it('cannot add files that are already in a batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch1 = DisposalBatch::factory()->create(['status' => DisposalBatch::STATUS_SCHEDULED]);
    $batch2 = DisposalBatch::factory()->create(['status' => DisposalBatch::STATUS_SCHEDULED]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    DisposalBatchFile::factory()->create([
        'batch_id' => $batch1->id,
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->post("/disposal-batches/{$batch2->id}/add-files", [
            'file_ids' => [$file->id],
        ]);

    $response->assertRedirect(route('disposal-batches.show', $batch2));

    expect($batch2->fresh()->items)->toHaveCount(0);
});

it('can add files with active retention status', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create(['status' => DisposalBatch::STATUS_SCHEDULED]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::ACTIVE,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    $response = $this->actingAs($user)
        ->post("/disposal-batches/{$batch->id}/add-files", [
            'file_ids' => [$file->id],
        ]);

    $response->assertRedirect(route('disposal-batches.show', $batch));

    expect($batch->fresh()->items)->toHaveCount(1);
});

it('cannot add files that are archived', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create(['status' => DisposalBatch::STATUS_SCHEDULED]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::ARCHIVED,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    $response = $this->actingAs($user)
        ->post("/disposal-batches/{$batch->id}/add-files", [
            'file_ids' => [$file->id],
        ]);

    $response->assertRedirect(route('disposal-batches.show', $batch));

    expect($batch->fresh()->items)->toHaveCount(0);
});

it('does not expose files from rejected batches to a new batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    // Create a rejected batch with a file
    $rejectedBatch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_REJECTED,
        'rejected_by' => $user->id,
        'rejected_at' => now(),
        'rejection_reason' => 'Need to redo disposal',
    ]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subWeek()->toDateString(),
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    // File was in the rejected batch
    DisposalBatchFile::factory()->create([
        'batch_id' => $rejectedBatch->id,
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now()->subDays(2),
    ]);

    // Create a new batch
    $newBatch = DisposalBatch::factory()->create(['status' => DisposalBatch::STATUS_SCHEDULED]);

    // File should not be available while tied to a rejected batch
    $response = $this->actingAs($user)
        ->getJson("/disposal-batches/{$newBatch->id}/available-files");

    $response->assertOk()
        ->assertJsonMissing(['id' => $file->id]);

    // Should not be able to add it to the new batch
    $addResponse = $this->actingAs($user)
        ->post("/disposal-batches/{$newBatch->id}/add-files", [
            'file_ids' => [$file->id],
        ]);

    $addResponse->assertRedirect(route('disposal-batches.show', $newBatch));

    expect($newBatch->fresh()->items)->toHaveCount(0);
});

it('does not expose files from completed batches even when manually restored', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    // Create a completed disposal batch
    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_expires_at' => now()->subDays(10)->toDateString(),
        'physical_location' => 'Cabinet A',
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'status' => 'approved',
    ]);

    $completedBatch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'completed_by' => $user->id,
        'completed_at' => now()->subWeek(),
    ]);

    DisposalBatchFile::factory()->create([
        'batch_id' => $completedBatch->id,
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now()->subWeek(),
    ]);

    // File was archived and soft-deleted during disposal
    $file->forceFill(['retention_status' => RetentionStatus::ARCHIVED])->save();
    $file->delete();

    // Verify file is NOT available while still archived and deleted
    $checkBatch = DisposalBatch::factory()->create(['status' => DisposalBatch::STATUS_SCHEDULED]);
    $notAvailableResponse = $this->actingAs($user)
        ->getJson("/disposal-batches/{$checkBatch->id}/available-files");

    $notAvailableResponse->assertOk()
        ->assertJsonMissing(['id' => $file->id]);

    // Now unarchive the file (manually or through workflow)
    $file->restore();
    $file->forceFill([
        'retention_status' => \App\Support\RetentionStatusCalculator::determine($file->retention_expires_at),
    ])->save();

    // Create a new batch
    $newBatch = DisposalBatch::factory()->create(['status' => DisposalBatch::STATUS_SCHEDULED]);

    // File should remain unavailable while tied to a completed batch
    $response = $this->actingAs($user)
        ->getJson("/disposal-batches/{$newBatch->id}/available-files");

    $response->assertOk()
        ->assertJsonMissing(['id' => $file->id]);

    // Should not be able to add it to the new batch
    $addResponse = $this->actingAs($user)
        ->post("/disposal-batches/{$newBatch->id}/add-files", [
            'file_ids' => [$file->id],
        ]);

    $addResponse->assertRedirect(route('disposal-batches.show', $newBatch));

    expect($newBatch->fresh()->items)->toHaveCount(0);
});
