<?php

use App\Models\DisposalAudit;
use App\Models\DisposalBatch;
use App\Models\DisposalBatchFile;
use App\Models\File;
use App\Models\Tag;
use App\Models\User;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('schedules files into a disposal batch', function (): void {
    Storage::fake('local');

    $user = User::factory()->create(['email_verified_at' => now()]);
    $files = File::factory()->count(2)->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->post('/disposal-management/schedule', [
            'file_ids' => $files->pluck('id')->all(),
            'mode_of_disposal' => DisposalBatch::MODE_SALE,
        ])
        ->assertRedirect('/disposal-management');

    $batch = DisposalBatch::query()->firstOrFail();

    expect($batch->status)->toBe(DisposalBatch::STATUS_SCHEDULED)
        ->and($batch->file_count)->toBe(2)
        ->and($batch->nap_report_path)->not->toBeNull();

    Storage::disk('local')->assertExists($batch->nap_report_path);

    expect(DisposalBatchFile::count())->toBe(2)
        ->and(DisposalAudit::count())->toBe(2);

    // Files should remain READY_FOR_DISPOSAL until disposal is manually completed
    $files->each(fn (File $file) => expect($file->fresh()->retention_status)->toBe(RetentionStatus::READY_FOR_DISPOSAL));
});

it('prevents scheduling perpetual retention records', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_PERPETUAL,
    ]);

    $response = $this->actingAs($user)
        ->from('/disposal-management')
        ->post('/disposal-management/schedule', [
            'file_ids' => [$file->id],
            'mode_of_disposal' => DisposalBatch::MODE_SALE,
            'note' => 'Attempt to schedule perpetual record',
        ]);

    $response->assertSessionHasErrors('file_ids');
});

it('prevents scheduling files that are not records', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'is_record' => false,
    ]);

    $response = $this->actingAs($user)
        ->from('/disposal-management')
        ->post('/disposal-management/schedule', [
            'file_ids' => [$file->id],
            'mode_of_disposal' => DisposalBatch::MODE_SALE,
        ]);

    $response->assertSessionHasErrors('file_ids');
});

it('completes batch and keeps file retention state before approval', function (): void {
    Storage::fake('records');

    $user = User::factory()->create(['email_verified_at' => now()]);
    $tag = Tag::factory()->physicalLocation()->create();

    $filePath = 'documents/test-file.pdf';
    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'physical_location' => 'Vault A',
        'file_path' => $filePath,
    ]);

    // Create actual file in storage
    Storage::disk('records')->put($filePath, 'test content');
    Storage::disk('records')->assertExists($filePath);

    $file->physicalLocationTags()->attach($tag->id);

    $this->actingAs($user)
        ->post('/disposal-management/complete', [
            'file_ids' => [$file->id],
        ])
        ->assertRedirect('/disposal-management');

    $batch = DisposalBatch::query()->firstOrFail();

    expect($batch->status)->toBe(DisposalBatch::STATUS_COMPLETED)
        ->and($batch->completed_at)->not->toBeNull();

    Storage::disk('local')->assertExists($batch->nap_report_path);

    // Verify file remains pre-disposal state until approval
    $completedFile = File::find($file->id);
    expect($completedFile)->not->toBeNull()
        ->and($completedFile->trashed())->toBeFalse()
        ->and($completedFile->retention_status)->toBe(RetentionStatus::READY_FOR_DISPOSAL)
        ->and($completedFile->physical_location)->toBe('Vault A')
        ->and($completedFile->physicalLocationTags)->toHaveCount(1);

    // Verify electronic file remains in storage
    Storage::disk('records')->assertExists($filePath);
});

it('completes disposal and keeps all file versions in storage', function (): void {
    Storage::fake('records');

    $user = User::factory()->create(['email_verified_at' => now()]);

    $mainFilePath = 'documents/main-file.pdf';
    $version1Path = 'documents/version-1.pdf';
    $version2Path = 'documents/version-2.pdf';

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'file_path' => $mainFilePath,
    ]);

    // Create main file and versions in storage
    Storage::disk('records')->put($mainFilePath, 'main content');
    Storage::disk('records')->put($version1Path, 'version 1 content');
    Storage::disk('records')->put($version2Path, 'version 2 content');

    // Create file versions
    $file->versions()->createMany([
        [
            'version_number' => 1,
            'file_name' => 'version-1.pdf',
            'file_path' => $version1Path,
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_by' => $user->id,
        ],
        [
            'version_number' => 2,
            'file_name' => 'version-2.pdf',
            'file_path' => $version2Path,
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'uploaded_by' => $user->id,
        ],
    ]);

    // Verify files exist before disposal
    Storage::disk('records')->assertExists($mainFilePath);
    Storage::disk('records')->assertExists($version1Path);
    Storage::disk('records')->assertExists($version2Path);

    $this->actingAs($user)
        ->post('/disposal-management/complete', [
            'file_ids' => [$file->id],
        ])
        ->assertRedirect('/disposal-management');

    // Verify file remains pre-disposal state until approval
    $completedFile = File::find($file->id);
    expect($completedFile)->not->toBeNull()
        ->and($completedFile->trashed())->toBeFalse()
        ->and($completedFile->retention_status)->toBe(RetentionStatus::READY_FOR_DISPOSAL);

    // Verify all files remain in storage
    Storage::disk('records')->assertExists($mainFilePath);
    Storage::disk('records')->assertExists($version1Path);
    Storage::disk('records')->assertExists($version2Path);
});

it('keeps completed files out of trash before approval', function (): void {
    Storage::fake('records');

    $user = User::factory()->create(['email_verified_at' => now()]);

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'file_path' => 'documents/file.pdf',
    ]);

    Storage::disk('records')->put('documents/file.pdf', 'content');

    $fileId = $file->id;
    $originalPhysicalLocation = $file->physical_location;

    // File should be visible in normal queries
    expect(File::find($fileId))->not->toBeNull()
        ->and(File::count())->toBe(1);

    $this->actingAs($user)
        ->post('/disposal-management/complete', [
            'file_ids' => [$fileId],
        ]);

    // File should STILL be visible in normal queries (not soft-deleted)
    // It's filtered from "All Records" by retention_status, not by deleted_at
    expect(File::find($fileId))->not->toBeNull()
        ->and(File::count())->toBe(1);

    // File should NOT appear in trash (no deleted_at)
    expect(File::onlyTrashed()->count())->toBe(0);

    $completedFile = File::find($fileId);
    expect($completedFile->deleted_at)->toBeNull()
        ->and($completedFile->trashed())->toBeFalse()
        ->and($completedFile->retention_status)->toBe(RetentionStatus::READY_FOR_DISPOSAL)
        ->and($completedFile->physical_location)->toBe($originalPhysicalLocation);

    // Verify file remains in storage
    Storage::disk('records')->assertExists('documents/file.pdf');
});

it('keeps completed records searchable before approval', function (): void {
    Storage::fake('records');

    $user = User::factory()->create(['email_verified_at' => now()]);

    // Create a file and complete disposal (which archives and soft-deletes it)
    $file = File::factory()->create([
        'file_name' => 'Test Archived Document.pdf',
        'status' => 'approved',
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'file_path' => 'documents/test-file.pdf',
    ]);

    Storage::disk('records')->put($file->file_path, 'test content');

    $this->actingAs($user)
        ->post('/disposal-management/complete', [
            'file_ids' => [$file->id],
        ]);

    // Verify the file remains in pre-disposal state but searchable
    $completedFile = File::find($file->id);
    expect($completedFile->retention_status)->toBe(RetentionStatus::READY_FOR_DISPOSAL)
        ->and($completedFile->trashed())->toBeFalse();

    // Disposed record remains visible in Search
    $response = $this->actingAs($user)
        ->get('/search?search=Test+Archived+Document');

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Search')
            ->where('results.data.0.id', $file->id)
            ->where('results.data.0.retention_status', RetentionStatus::READY_FOR_DISPOSAL)
        );
});

it('marks batch as complete and keeps file retention until approval', function (): void {
    Storage::fake('records');

    $user = User::factory()->create(['email_verified_at' => now()]);

    // Create files ready for disposal
    $files = File::factory()->count(3)->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'physical_location' => 'Vault A',
        'file_path' => 'documents/test-file.pdf',
    ]);

    // Create actual files in storage
    foreach ($files as $file) {
        Storage::disk('records')->put($file->file_path, 'test content');
    }

    // Schedule files into a disposal batch
    $this->actingAs($user)
        ->post('/disposal-management/schedule', [
            'file_ids' => $files->pluck('id')->all(),
            'mode_of_disposal' => DisposalBatch::MODE_SALE,
        ]);

    $batch = DisposalBatch::query()->firstOrFail();

    expect($batch->status)->toBe(DisposalBatch::STATUS_SCHEDULED);

    // Now mark the batch as complete using the batch detail page button
    $response = $this->actingAs($user)
        ->post("/disposal-batches/{$batch->id}/complete");

    $response->assertRedirect("/disposal-batches/{$batch->id}");

    // Verify batch is completed
    $batch->refresh();
    expect($batch->status)->toBe(DisposalBatch::STATUS_COMPLETED)
        ->and($batch->completed_at)->not->toBeNull()
        ->and($batch->completed_by)->toBe($user->id);

    // Verify all files remain pre-disposal until approval
    foreach ($files as $file) {
        $completedFile = File::find($file->id);

        expect($completedFile)->not->toBeNull()
            ->and($completedFile->trashed())->toBeFalse()
            ->and($completedFile->retention_status)->toBe(RetentionStatus::READY_FOR_DISPOSAL)
            ->and($completedFile->physical_location)->toBe('Vault A');

        // Verify electronic file remains in storage
        Storage::disk('records')->assertExists($file->file_path);
    }

    // Verify batch items are updated to completed
    $batch->items->each(function ($item) use ($user) {
        expect($item->status)->toBe(DisposalBatch::STATUS_COMPLETED)
            ->and($item->completed_at)->not->toBeNull()
            ->and($item->completed_by)->toBe($user->id);
    });

    // Verify audit logs were created for each file
    expect(DisposalAudit::where('batch_id', $batch->id)->count())->toBeGreaterThanOrEqual(3);
});

it('schedules active files approaching retention expiration', function (): void {
    Storage::fake('local');

    $user = User::factory()->create(['email_verified_at' => now()]);

    // Create active files with upcoming expiration (15 days from now)
    $files = File::factory()->count(2)->create([
        'retention_status' => RetentionStatus::ACTIVE,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->addDays(15)->toDateString(),
    ]);

    $this->actingAs($user)
        ->post('/disposal-management/schedule', [
            'file_ids' => $files->pluck('id')->all(),
            'mode_of_disposal' => DisposalBatch::MODE_SALE,
            'note' => 'Scheduling active records approaching retention expiration',
        ])
        ->assertRedirect('/disposal-management');

    $batch = DisposalBatch::query()->firstOrFail();

    expect($batch->status)->toBe(DisposalBatch::STATUS_SCHEDULED)
        ->and($batch->file_count)->toBe(2)
        ->and($batch->nap_report_path)->not->toBeNull();

    Storage::disk('local')->assertExists($batch->nap_report_path);

    expect(DisposalBatchFile::count())->toBe(2)
        ->and(DisposalAudit::count())->toBe(2);

    // Files retain their ACTIVE status until disposal is manually completed
    $files->each(fn (File $file) => expect($file->fresh()->retention_status)->toBe(RetentionStatus::ACTIVE));
});

it('completes disposal for active files', function (): void {
    Storage::fake('records');

    $user = User::factory()->create(['email_verified_at' => now()]);

    // Create active file with upcoming expiration
    $filePath = 'documents/active-file.pdf';
    $file = File::factory()->create([
        'retention_status' => RetentionStatus::ACTIVE,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->addDays(5)->toDateString(),
        'file_path' => $filePath,
    ]);

    // Create actual file in storage
    Storage::disk('records')->put($filePath, 'test content');
    Storage::disk('records')->assertExists($filePath);

    $this->actingAs($user)
        ->post('/disposal-management/complete', [
            'file_ids' => [$file->id],
        ])
        ->assertRedirect('/disposal-management');

    $batch = DisposalBatch::query()->firstOrFail();

    expect($batch->status)->toBe(DisposalBatch::STATUS_COMPLETED)
        ->and($batch->completed_at)->not->toBeNull();

    Storage::disk('local')->assertExists($batch->nap_report_path);

    // Verify file stays active until approval stage disposes it
    $completedFile = File::find($file->id);
    expect($completedFile)->not->toBeNull()
        ->and($completedFile->trashed())->toBeFalse()
        ->and($completedFile->retention_status)->toBe(RetentionStatus::ACTIVE);
});
