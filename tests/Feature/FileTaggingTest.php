<?php

use App\Models\Department;
use App\Models\File;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;

use function Pest\Laravel\actingAs;

function grantUploadAbility(User $user): void
{
    $permission = Permission::firstOrCreate(
        ['slug' => 'upload-submit-reports'],
        ['name' => 'Upload Submit Reports']
    );

    $role = Role::factory()->create();
    $role->permissions()->attach($permission);
    $user->roles()->attach($role);
}

it('persists metadata and physical tags when creating a file', function () {
    $user = User::factory()->create();
    grantUploadAbility($user);

    $department = Department::factory()->create();
    $metadataTag = Tag::factory()->create(['type' => 'metadata']);
    $physicalTag = Tag::factory()->create(['type' => 'physical_location']);

    $recordId = 'REC-'.fake()->unique()->numerify('####');

    $response = actingAs($user)->postJson(route('api.files.store'), [
        'author' => 'Archivist',
        'department_id' => (string) $department->id,
        'record_id' => $recordId,
        'file_name' => 'Quarterly Summary',
        'visibility' => 'public',
        'status' => 'pending',
        'retention_duration' => 'one_year',
        'metadata_tag_ids' => [$metadataTag->id],
        'physical_location_tag_ids' => [$physicalTag->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.metadata_tag_ids', [$metadataTag->id])
        ->assertJsonPath('data.physical_location_tag_ids', [$physicalTag->id]);

    $file = File::query()->firstOrFail();

    expect($file->metadata_tag_ids)->toMatchArray([$metadataTag->id]);
    expect($file->physical_location_tag_ids)->toMatchArray([$physicalTag->id]);

    $this->assertDatabaseHas('file_tag_pivot', [
        'file_id' => $file->id,
        'tag_id' => $physicalTag->id,
    ]);
});

it('retains existing physical tags when updating only metadata tags', function () {
    $user = User::factory()->create();
    grantUploadAbility($user);

    $department = Department::factory()->create();
    $metadataTag = Tag::factory()->create(['type' => 'metadata']);
    $physicalTag = Tag::factory()->create(['type' => 'physical_location']);
    $recordId = 'REC-'.fake()->unique()->numerify('####');

    $createResponse = actingAs($user)->postJson(route('api.files.store'), [
        'author' => 'Archivist',
        'department_id' => (string) $department->id,
        'record_id' => $recordId,
        'file_name' => 'Baseline Report',
        'visibility' => 'public',
        'status' => 'pending',
        'retention_duration' => 'one_year',
        'metadata_tag_ids' => [$metadataTag->id],
        'physical_location_tag_ids' => [$physicalTag->id],
    ])->assertCreated();

    $fileId = $createResponse->json('data.id');
    $replacementMetadata = Tag::factory()->create(['type' => 'metadata']);

    $updateResponse = actingAs($user)->postJson(route('api.files.update', ['file' => $fileId]), [
        '_method' => 'patch',
        'author' => 'Archivist',
        'department_id' => (string) $department->id,
        'record_id' => $recordId,
        'file_name' => 'Baseline Report',
        'visibility' => 'public',
        'status' => 'pending',
        'metadata_tag_ids' => [$replacementMetadata->id],
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('metadata_tag_ids', [$replacementMetadata->id])
        ->assertJsonPath('physical_location_tag_ids', [$physicalTag->id]);

    $file = File::query()->findOrFail($fileId);

    expect($file->metadata_tag_ids)->toMatchArray([$replacementMetadata->id]);
    expect($file->physical_location_tag_ids)->toMatchArray([$physicalTag->id]);
});
