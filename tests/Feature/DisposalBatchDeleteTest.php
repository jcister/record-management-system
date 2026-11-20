<?php

use App\Models\DisposalAudit;
use App\Models\DisposalBatch;
use App\Models\DisposalBatchFile;
use App\Models\File;
use App\Models\User;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can delete a scheduled disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    grantDisposalManagementPermission($user);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'reference' => 'DB-000001',
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
        ->delete("/disposal-batches/{$batch->id}");

    $response->assertRedirect(route('disposal-batches.index'));

    expect(DisposalBatch::find($batch->id))->toBeNull()
        ->and(DisposalBatchFile::where('batch_id', $batch->id)->count())->toBe(0)
        ->and(DisposalAudit::where('action', 'batch_deleted')->count())->toBe(1);

    // Verify files still exist and are available for other batches
    expect(File::find($file1->id))->not->toBeNull()
        ->and(File::find($file2->id))->not->toBeNull();
});

it('cannot delete a completed disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    grantDisposalManagementPermission($user);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'reference' => 'DB-000002',
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
        ->delete("/disposal-batches/{$batch->id}");

    $response->assertRedirect(route('disposal-batches.index'));

    // Batch should still exist
    expect(DisposalBatch::find($batch->id))->not->toBeNull()
        ->and(DisposalBatchFile::where('batch_id', $batch->id)->count())->toBe(1);
});

it('cannot delete an inspected disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    grantDisposalManagementPermission($user);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_INSPECTED,
        'reference' => 'DB-000010',
        'file_count' => 1,
        'inspected_by' => $user->id,
        'inspected_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}");

    $response->assertRedirect(route('disposal-batches.index'));

    // Batch should still exist
    expect(DisposalBatch::find($batch->id))->not->toBeNull();
});

it('cannot delete an approved disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    grantDisposalManagementPermission($user);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_APPROVED,
        'reference' => 'DB-000011',
        'file_count' => 1,
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}");

    $response->assertRedirect(route('disposal-batches.index'));

    // Batch should still exist
    expect(DisposalBatch::find($batch->id))->not->toBeNull();
});

it('cannot delete a rejected disposal batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    grantDisposalManagementPermission($user);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_REJECTED,
        'reference' => 'DB-000012',
        'file_count' => 1,
        'rejected_by' => $user->id,
        'rejected_at' => now(),
        'rejection_reason' => 'Incorrect files included',
    ]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    DisposalBatchFile::factory()->create([
        'batch_id' => $batch->id,
        'file_id' => $file->id,
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}");

    $response->assertRedirect(route('disposal-batches.index'));

    // Batch and its metadata should still exist
    expect(DisposalBatch::find($batch->id))->not->toBeNull()
        ->and(DisposalBatchFile::where('batch_id', $batch->id)->count())->toBe(1);
});

it('creates proper audit trail when deleting a batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    grantDisposalManagementPermission($user);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'reference' => 'DB-000003',
        'file_count' => 3,
    ]);

    $files = File::factory()->count(3)->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    foreach ($files as $file) {
        DisposalBatchFile::factory()->create([
            'batch_id' => $batch->id,
            'file_id' => $file->id,
        ]);
    }

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}");

    $response->assertRedirect(route('disposal-batches.index'));

    $auditLog = DisposalAudit::where('action', 'batch_deleted')->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->batch_id)->toBeNull()
        ->and($auditLog->user_id)->toBe($user->id)
        ->and($auditLog->description)->toContain('DB-000003')
        ->and($auditLog->description)->toContain('3 file(s)')
        ->and($auditLog->meta)->toHaveKey('batch_id')
        ->and($auditLog->meta)->toHaveKey('batch_reference')
        ->and($auditLog->meta['batch_reference'])->toBe('DB-000003')
        ->and($auditLog->meta)->toHaveKey('file_count')
        ->and($auditLog->meta['file_count'])->toBe(3)
        ->and($auditLog->meta)->toHaveKey('status')
        ->and($auditLog->meta['status'])->toBe(DisposalBatch::STATUS_SCHEDULED);
});

it('deletes batch with no files', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    grantDisposalManagementPermission($user);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'reference' => 'DB-000004',
        'file_count' => 0,
    ]);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}");

    $response->assertRedirect(route('disposal-batches.index'));

    expect(DisposalBatch::find($batch->id))->toBeNull()
        ->and(DisposalAudit::where('action', 'batch_deleted')->count())->toBe(1);
});

it('removes all batch files when deleting batch', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    grantDisposalManagementPermission($user);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'file_count' => 5,
    ]);

    $files = File::factory()->count(5)->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    foreach ($files as $file) {
        DisposalBatchFile::factory()->create([
            'batch_id' => $batch->id,
            'file_id' => $file->id,
        ]);
    }

    expect(DisposalBatchFile::where('batch_id', $batch->id)->count())->toBe(5);

    $response = $this->actingAs($user)
        ->delete("/disposal-batches/{$batch->id}");

    $response->assertRedirect(route('disposal-batches.index'));

    expect(DisposalBatchFile::where('batch_id', $batch->id)->count())->toBe(0);
});
