<?php

/** @var TestCase $this */

use App\Models\Folder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

function grantUploadContentPermissionForFolderApi(User $user): void
{
    $permission = Permission::firstOrCreate(
        ['slug' => 'organize-content'],
        ['name' => 'Organize Content']
    );

    $role = Role::factory()->create();
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user->roles()->attach($role);
}

it('creates a folder for the authenticated user', function () {
    $user = User::factory()->create();
    grantUploadContentPermissionForFolderApi($user);

    $response = $this->actingAs($user)->postJson('/api/folders', [
        'name' => 'Records',
    ]);

    $response->assertCreated()->assertJsonPath('name', 'Records');

    $folder = Folder::first();

    expect($folder)
        ->not->toBeNull()
        ->name->toBe('Records')
        ->created_by->toBe($user->id);
});

it('updates folder details', function () {
    $user = User::factory()->create();
    grantUploadContentPermissionForFolderApi($user);
    $folder = Folder::factory()->create([
        'created_by' => $user->id,
        'name' => 'Initial',
    ]);

    $response = $this->actingAs($user)->patchJson("/api/folders/{$folder->id}", [
        'name' => 'Updated',
        'is_locked' => true,
    ]);

    $response->assertOk()->assertJsonPath('name', 'Updated');

    expect($folder->refresh())
        ->name->toBe('Updated')
        ->is_locked->toBeTrue();
});
