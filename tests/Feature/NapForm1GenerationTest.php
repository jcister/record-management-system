<?php

use App\Models\DisposalBatch;
use App\Models\File;
use App\Models\Role;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\NapForm1Service;
use App\Support\RetentionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('previews nap form 1 for batches with workflow files', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $role = Role::factory()->create(['name' => 'Records Officer']);
    $user->roles()->sync([$role->id]);

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
        'physical_snapshot' => [
            'location' => 'Records Storage',
            'tags' => [
                ['id' => 1, 'name' => 'Shelf A'],
            ],
        ],
    ]);

    actingAs($user)
        ->get(route('disposal-batches.nap-form-1.preview', $batch))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('NapForm1Preview')
            ->where('batch.id', $batch->id)
        );
});

it('prepares nap form 1 data from workflow files', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Records Custodian']);
    $user->roles()->sync([$role->id]);

    $file = File::factory()->create([
        'record_id' => 'REC-2026-001',
        'file_name' => 'Board Resolutions',
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
        'physical_snapshot' => [
            'location' => 'Storage Area',
            'tags' => [
                ['id' => 9, 'name' => 'Cabinet 2'],
            ],
        ],
    ]);

    $data = app(NapForm1Service::class)->prepareDefaultData($batch, $user);

    expect($data['items'])->toHaveCount(1)
        ->and($data['items'][0]['title'])->toBe('Board Resolutions')
        ->and($data['items'][0]['location'])->toBe('Cabinet 2');
});

it('generates nap form 1 pdf and stores batch metadata', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Records Custodian']);
    $user->roles()->sync([$role->id]);

    $file = File::factory()->create([
        'file_name' => 'Disposition Logbook',
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
        'physical_snapshot' => [
            'location' => 'Records Area',
            'tags' => [
                ['id' => 2, 'name' => 'Shelf B'],
            ],
        ],
    ]);

    actingAs($user)
        ->post(route('disposal-batches.nap-form-1.generate', $batch), [
            'agency' => 'Commission on Higher Education Regional Office XI',
            'organizational_unit' => 'Records Unit',
            'telephone' => '082-123-4567',
            'address' => 'Loyola Street, Barrio Obrero, Davao City',
            'person_in_charge' => 'Records Custodian',
            'date_prepared' => now()->format('F j, Y'),
            'prepared_by_user_id' => $user->id,
            'prepared_by_name' => $user->name,
            'prepared_by_position' => 'Records Officer',
            'prepared_by_role' => 'Records Custodian',
            'assisted_by_name' => '',
            'assisted_by_designation' => '',
            'assisted_by_role' => '',
            'approved_by_name' => '',
            'approved_by_designation' => '',
            'approved_by_role' => '',
            'items' => [
                [
                    'title' => 'Disposition Logbook',
                    'period' => '2020 - 2022',
                    'volume' => '10',
                    'location' => 'Shelf B',
                    'frequency' => 'Monthly',
                    'duplication' => '',
                    'time_value' => 'T',
                    'utility_value' => 'Adm',
                    'retention_active' => '1 year',
                    'retention_storage' => '1 year',
                    'retention_total' => '2 years',
                    'provision' => 'Dispose after review',
                ],
            ],
        ])
        ->assertRedirect(route('disposal-batches.show', $batch));

    $batch->refresh();

    expect($batch->nap_form_1_path)->not->toBeNull()
        ->and($batch->nap_form_1_stage)->toBe('generated')
        ->and($batch->nap_form_1_generated_by)->toBe($user->id)
        ->and($batch->nap_form_1_generated_at)->not->toBeNull()
        ->and(data_get($batch->metadata, 'nap_form_1.organizational_unit'))->toBe('Records Unit');
});

it('downloads nap form 1 and sets downloaded timestamp', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $relativePath = 'public/nap-form-1s/test-nap-form-1.pdf';
    $absolutePath = storage_path('app/'.$relativePath);
    $directory = dirname($absolutePath);

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($absolutePath, '%PDF-1.4 test nap form 1 content');

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'nap_form_1_path' => $relativePath,
        'nap_form_1_generated_at' => now(),
        'nap_form_1_downloaded_at' => null,
    ]);

    actingAs($user)
        ->get(route('disposal-batches.nap-form-1.download', $batch))
        ->assertOk();

    $batch->refresh();
    expect($batch->nap_form_1_downloaded_at)->not->toBeNull();
});

it('uses latest system information for nap form 1 preview defaults', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Records Officer']);
    $user->roles()->sync([$role->id]);

    SystemConfiguration::query()->updateOrCreate(
        ['id' => 1],
        [
            'agency_name' => 'Updated Agency Name',
            'address' => 'Updated Agency Address',
            'telephone' => '123-4567',
        ]
    );

    $file = File::factory()->create([
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'file_count' => 1,
        'metadata' => [
            'nap_form_1' => [
                'agency' => 'Old Agency',
                'address' => 'Old Address',
                'telephone' => '000',
            ],
        ],
    ]);

    $batch->items()->create([
        'file_id' => $file->id,
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_by' => $user->id,
        'scheduled_at' => now(),
    ]);

    actingAs($user)
        ->get(route('disposal-batches.nap-form-1.preview', $batch))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('NapForm1Preview')
            ->where('defaultData.agency', 'Updated Agency Name')
            ->where('defaultData.address', 'Updated Agency Address')
            ->where('defaultData.telephone', '123-4567')
        );
});
