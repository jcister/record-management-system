<?php

use App\Models\DisposalAudit;
use App\Models\DisposalBatch;
use App\Models\DisposalBatchFile;
use App\Models\File;
use App\Models\User;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can remove a file from a scheduled disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'file_count' => 2,
    ]);

    $file1 = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $file2 = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    DisposalBatchFile::factory()->create([
        'batch_id' => $batch->id,
        'file_id' => $file1->id,
    ]);

    DisposalBatchFile::factory()->create([
        'batch_id' => $batch->id,
        'file_id' => $file2->id,
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}/remove-file/{$file1->id}");

    $response->assertRedirect(route('disposal-batches.show', $batch));

    $batch->refresh();

    expect($batch->file_count)->toBe(1)
        ->and($batch->items)->toHaveCount(1)
        ->and($batch->items->first()->file_id)->toBe($file2->id)
        ->and(DisposalAudit::where('action', 'removed_from_batch')->count())->toBe(1);

    $auditLog = DisposalAudit::where('action', 'removed_from_batch')->first();
    expect($auditLog->file_id)->toBe($file1->id)
        ->and($auditLog->user_id)->toBe($user->id)
        ->and($auditLog->batch_id)->toBe($batch->id);
});

it('cannot remove a file from a completed disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
    ]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::ARCHIVED,
    ]);

    DisposalBatchFile::factory()->create([
        'batch_id' => $batch->id,
        'file_id' => $file->id,
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}/remove-file/{$file->id}");

    $response->assertRedirect(route('disposal-batches.show', $batch));

    $batch->refresh();

    expect($batch->file_count)->toBe(1)
        ->and($batch->items)->toHaveCount(1);
});

it('returns error when trying to remove a file that is not in the batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'file_count' => 0,
    ]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}/remove-file/{$file->id}");

    $response->assertRedirect(route('disposal-batches.show', $batch));

    expect($batch->items)->toHaveCount(0);
});

it('updates file count correctly when removing the last file from a batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'file_count' => 1,
    ]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    DisposalBatchFile::factory()->create([
        'batch_id' => $batch->id,
        'file_id' => $file->id,
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}/remove-file/{$file->id}");

    $response->assertRedirect(route('disposal-batches.show', $batch));

    $batch->refresh();

    expect($batch->file_count)->toBe(0)
        ->and($batch->items)->toHaveCount(0);
});

it('cannot remove a file from a rejected disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_REJECTED,
        'file_count' => 1,
    ]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::ARCHIVED,
    ]);

    DisposalBatchFile::factory()->create([
        'batch_id' => $batch->id,
        'file_id' => $file->id,
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}/remove-file/{$file->id}");

    $response->assertRedirect(route('disposal-batches.show', $batch))
        ->assertSessionHas('error', 'Cannot remove files from a batch that has been completed, inspected, approved, or rejected.');

    $batch->refresh();
    expect($batch->file_count)->toBe(1)
        ->and($batch->items)->toHaveCount(1);
});

it('cannot remove a file from an inspected disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_INSPECTED,
        'file_count' => 1,
    ]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::ARCHIVED,
    ]);

    DisposalBatchFile::factory()->create([
        'batch_id' => $batch->id,
        'file_id' => $file->id,
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}/remove-file/{$file->id}");

    $response->assertRedirect(route('disposal-batches.show', $batch))
        ->assertSessionHas('error', 'Cannot remove files from a batch that has been completed, inspected, approved, or rejected.');

    $batch->refresh();
    expect($batch->file_count)->toBe(1)
        ->and($batch->items)->toHaveCount(1);
});

it('cannot remove a file from an approved disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'file_count' => 1,
    ]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::ARCHIVED,
    ]);

    DisposalBatchFile::factory()->create([
        'batch_id' => $batch->id,
        'file_id' => $file->id,
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}/remove-file/{$file->id}");

    $response->assertRedirect(route('disposal-batches.show', $batch))
        ->assertSessionHas('error', 'Cannot remove files from a batch that has been completed, inspected, approved, or rejected.');

    $batch->refresh();
    expect($batch->file_count)->toBe(1)
        ->and($batch->items)->toHaveCount(1);
});

it('creates proper audit trail when removing a file', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'file_count' => 1,
        'reference' => 'DB-000001',
    ]);

    $file = File::factory()->create([
        'record_id' => 'TEST-001',
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    DisposalBatchFile::factory()->create([
        'batch_id' => $batch->id,
        'file_id' => $file->id,
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}/remove-file/{$file->id}");

    $response->assertRedirect(route('disposal-batches.show', $batch));

    $auditLog = DisposalAudit::where('action', 'removed_from_batch')->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->description)->toContain('TEST-001')
        ->and($auditLog->description)->toContain('DB-000001')
        ->and($auditLog->meta)->toHaveKey('batch_reference')
        ->and($auditLog->meta['batch_reference'])->toBe('DB-000001')
        ->and($auditLog->meta)->toHaveKey('retention_status');
});
