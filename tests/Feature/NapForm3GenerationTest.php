<?php

use App\Models\DisposalBatch;
use App\Models\File;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\NapForm3Service;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function finalizeNapForm1(DisposalBatch $batch, User $preparedBy): void
{
    $metadata = is_array($batch->metadata) ? $batch->metadata : [];

    $metadata['nap_form_1'] = array_merge($metadata['nap_form_1'] ?? [], [
        'prepared_by_user_id' => $preparedBy->id,
        'prepared_by_name' => $preparedBy->name,
        'prepared_by_position' => 'Records Officer',
    ]);

    $batch->forceFill([
        'nap_form_1_generated_at' => now(),
        'metadata' => $metadata,
    ])->save();
}

it('previews nap form 3 for batches with disposed files', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::DISPOSED,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now(),
    ]);

    finalizeNapForm1($batch, $user);

    actingAs($user)
        ->get(route('disposal-batches.nap-form-3.preview', $batch))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('NapForm3Preview')
            ->where('batch.id', $batch->id)
            ->where('defaultData.prepared_by_name', $user->name)
        );
});

it('allows nap form 3 preview when batch has workflow files', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    /** @var User $preparedBy */
    $preparedBy = User::factory()->create();

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now(),
    ]);

    finalizeNapForm1($batch, $preparedBy);

    actingAs($user)
        ->get(route('disposal-batches.nap-form-3.preview', $batch))
        ->assertSuccessful();
});

it('requires nap form 1 to be finalized before previewing nap form 3', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now(),
    ]);

    actingAs($user)
        ->get(route('disposal-batches.nap-form-3.preview', $batch))
        ->assertForbidden();
});

it('prepares nap form 3 data from workflow files', function (): void {
    /** @var User $scheduledByOne */
    $scheduledByOne = User::factory()->create();
    /** @var User $scheduledByTwo */
    $scheduledByTwo = User::factory()->create();

    $disposed = File::factory()->create([
        'record_id' => 'DISP-2026-001',
        'retention_status' => RetentionStatus::DISPOSED,
    ]);

    $active = File::factory()->create([
        'record_id' => 'ACTIVE-2026-001',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 2,
    ]);

    $batch->items()->createMany([
        [
            'file_id' => $disposed->id,
            'status' => DisposalBatch::STATUS_COMPLETED,
            'scheduled_by' => $scheduledByOne->id,
            'scheduled_at' => now(),
        ],
        [
            'file_id' => $active->id,
            'status' => DisposalBatch::STATUS_COMPLETED,
            'scheduled_by' => $scheduledByTwo->id,
            'scheduled_at' => now(),
        ],
    ]);

    $data = app(NapForm3Service::class)->prepareDefaultData($batch);

    expect($data['items'])->toHaveCount(2)
        ->and($data['items'][0]['grds_rds_item_no'])->toBe('DISP-2026-001');
});

it('uses latest system information for nap form 3 preview defaults', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    SystemConfiguration::query()->updateOrCreate(
        ['id' => 1],
        [
            'agency_name' => 'Updated Agency Name',
            'address' => 'Updated Agency Address',
            'telephone' => '123-4567',
            'regional_director_name' => 'Updated Director',
            'regional_director_designation' => 'Updated Designation',
        ]
    );

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
        'metadata' => [
            'nap_certificate' => [
                'agency_name' => 'Old Agency',
                'address' => 'Old Address',
                'telephone_number' => '000',
                'certified_by_name' => 'Old Director',
                'certified_by_designation' => 'Old Designation',
            ],
        ],
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now(),
    ]);

    finalizeNapForm1($batch, $user);

    actingAs($user)
        ->get(route('disposal-batches.nap-form-3.preview', $batch))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('NapForm3Preview')
            ->where('defaultData.agency_name', 'Updated Agency Name')
            ->where('defaultData.address', 'Updated Agency Address')
            ->where('defaultData.telephone_number', '123-4567')
            ->where('defaultData.certified_by_name', 'Updated Director')
            ->where('defaultData.certified_by_designation', 'Updated Designation')
        );
});
