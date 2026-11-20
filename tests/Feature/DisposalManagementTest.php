<?php

use App\Models\Department;
use App\Models\DisposalBatch;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows ready-for-disposal files scoped by root folder', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $rootA = Folder::factory()->create(['parent_id' => null, 'name' => 'Permits']);
    $childA = Folder::factory()->create(['parent_id' => $rootA->id, 'name' => 'Permits - Closed']);
    $rootB = Folder::factory()->create(['parent_id' => null, 'name' => 'Finance']);

    $match = File::factory()->create([
        'folder_id' => $childA->id,
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_ONE_YEAR,
        'retention_expires_at' => now()->subWeek()->toDateString(),
        'status' => 'approved',
    ]);

    File::factory()->create([
        'folder_id' => $rootB->id,
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subWeek()->toDateString(),
        'status' => 'approved',
    ]);

    File::factory()->create([
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    $this->actingAs($user);

    $response = $this->get('/disposal-management?root_folder_id='.$rootA->id);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Disposal/DisposalManagement')
        ->where('filters.root_folder_id', $rootA->id)
        ->where('queue.data', function ($records) use ($match): bool {
            $collection = collect($records);

            if ($collection->count() !== 1) {
                return false;
            }

            return $collection->first()['id'] === $match->id;
        })
    );
});

it('excludes files that are not records from the queue', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $includedRecord = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'is_record' => true,
        'status' => 'approved',
    ]);

    File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'is_record' => false,
        'status' => 'approved',
    ]);

    $this->actingAs($user);

    $response = $this->get('/disposal-management');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Disposal/DisposalManagement')
        ->where('queue.data', function ($records) use ($includedRecord): bool {
            $ids = collect($records)->pluck('id');

            return $ids->count() === 1 && $ids->first() === $includedRecord->id;
        })
    );
});

it('filters by retention duration', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $matching = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'status' => 'approved',
    ]);

    File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_ONE_YEAR,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'status' => 'approved',
    ]);

    $this->actingAs($user);

    $response = $this->get('/disposal-management?retention_duration='.RetentionSchedule::DURATION_TEN_YEARS);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Disposal/DisposalManagement')
        ->where('filters.retention_duration', RetentionSchedule::DURATION_TEN_YEARS)
        ->where('queue.data', function ($records) use ($matching): bool {
            $collection = collect($records);

            return $collection->count() === 1 && $collection->first()['id'] === $matching->id;
        })
    );
});

it('excludes files already scheduled in a disposal batch from the queue', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $scheduledFile = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'status' => 'approved',
    ]);

    $unscheduledFile = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'status' => 'approved',
    ]);

    // Schedule one file for disposal
    $this->actingAs($user)
        ->post('/disposal-management/schedule', [
            'file_ids' => [$scheduledFile->id],
            'mode_of_disposal' => DisposalBatch::MODE_SALE,
        ]);

    // Visit disposal management page
    $response = $this->get('/disposal-management');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Disposal/DisposalManagement')
        ->where('queue.data', function ($records) use ($scheduledFile, $unscheduledFile): bool {
            $collection = collect($records);

            // Only the unscheduled file should appear
            if ($collection->count() !== 1) {
                return false;
            }

            $firstRecord = $collection->first();

            return $firstRecord['id'] === $unscheduledFile->id
                && ! $collection->contains('id', $scheduledFile->id);
        })
        ->where('metrics.totals.ready_for_disposal', 1)
        ->where('metrics.totals.scheduled_batches', 1)
    );
});

it('counts every batch that has not completed submission to COA', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $incompleteStatuses = [
        DisposalBatch::STATUS_SCHEDULED,
        DisposalBatch::STATUS_REQUEST_LETTER_READY,
        DisposalBatch::STATUS_INSPECTED,
        DisposalBatch::STATUS_APPROVED,
        DisposalBatch::STATUS_COMPLETED,
        DisposalBatch::STATUS_REJECTED,
    ];

    foreach ($incompleteStatuses as $status) {
        DisposalBatch::factory()->create([
            'status' => $status,
            'scheduled_by' => $user->id,
        ]);
    }

    DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETE,
        'scheduled_by' => $user->id,
    ]);

    $this->actingAs($user);

    $response = $this->get('/disposal-management');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Disposal/DisposalManagement')
        ->where('metrics.totals.scheduled_batches', count($incompleteStatuses))
    );
});

it('excludes files tied to completed disposal batches from the queue', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $completedBatchFile = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'status' => 'approved',
    ]);

    $availableFile = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'status' => 'approved',
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'completed_by' => $user->id,
        'completed_at' => now(),
    ]);

    $batch->items()->create([
        'file_id' => $completedBatchFile->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now()->subDay(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/disposal-management');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Disposal/DisposalManagement')
        ->where('queue.data', function ($records) use ($completedBatchFile, $availableFile): bool {
            $collection = collect($records);

            if ($collection->count() !== 1) {
                return false;
            }

            return $collection->first()['id'] === $availableFile->id
                && ! $collection->contains('id', $completedBatchFile->id);
        })
        ->where('metrics.totals.ready_for_disposal', 1)
    );
});

it('paginates the disposal queue', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    File::factory()->count(16)->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
        'retention_duration' => RetentionSchedule::DURATION_TEN_YEARS,
        'retention_expires_at' => now()->subDay()->toDateString(),
        'status' => 'approved',
    ]);

    $this->actingAs($user);

    $response = $this->get('/disposal-management?per_page=15&page=2');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Disposal/DisposalManagement')
        ->where('queue.current_page', 2)
        ->where('queue.total', 16)
        ->where('queue.data', fn ($records): bool => count($records) === 1)
    );
});

it('returns filter options for disposal queue filters', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $folder = Folder::factory()->create(['parent_id' => null, 'name' => 'Permits']);
    $department = Department::factory()->create(['name' => 'Operations']);

    $this->actingAs($user);

    $response = $this->getJson('/disposal-management/filter-options');

    $response->assertOk();
    $response->assertJsonStructure([
        'retention_durations',
        'root_folders',
        'departments',
    ]);

    $response
        ->assertJsonFragment([
            'id' => (string) $folder->id,
            'name' => $folder->name,
        ])
        ->assertJsonFragment([
            'id' => (string) $department->id,
            'name' => $department->name,
        ]);
});
