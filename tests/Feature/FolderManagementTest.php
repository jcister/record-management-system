<?php

use App\Models\Folder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

function grantUploadContentPermissionForFolderManagement(User $user): void
{
    $permission = Permission::firstOrCreate(
        ['slug' => 'organize-content'],
        ['name' => 'Organize Content']
    );

    $role = Role::factory()->create();
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user->roles()->attach($role);
}

test('authenticated users can create folders', function () {
    $user = User::factory()->create();
    grantUploadContentPermissionForFolderManagement($user);
    $this->actingAs($user);

    $response = $this->postJson(route('api.folders.store'), [
        'name' => 'Test Folder',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('folders', [
        'name' => 'Test Folder',
        'created_by' => $user->id,
    ]);
});

test('folder name is required when creating', function () {
    $user = User::factory()->create();
    grantUploadContentPermissionForFolderManagement($user);
    $this->actingAs($user);

    $response = $this->postJson(route('api.folders.store'), [
        'name' => '',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('name');
});

test('authenticated users can view folders', function () {
    $user = User::factory()->create();
    grantUploadContentPermissionForFolderManagement($user);
    $folder = Folder::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user);

    $response = $this->getJson(route('api.folders.index'));

    $response->assertOk();
    $response->assertJsonFragment(['name' => $folder->name]);
});

test('users without folder access permissions cannot view folders', function () {
    $user = User::factory()->create();
    Folder::factory()->create(['name' => 'Restricted Folder']);

    $response = $this->actingAs($user)->getJson(route('api.folders.index'));

    $response->assertForbidden();
});

test('root-level folders default to the All Records bucket', function () {
    $user = User::factory()->create();
    grantUploadContentPermissionForFolderManagement($user);
    $this->actingAs($user);

    $response = $this->postJson(route('api.folders.store'), [
        'name' => 'All Records Child',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('folders', [
        'name' => 'All Records Child',
        'parent_id' => null,
        'is_basic' => true,
    ]);
});

test('child folders inherit the bucket flag from the parent when unspecified', function () {
    $user = User::factory()->create();
    grantUploadContentPermissionForFolderManagement($user);
    $this->actingAs($user);

    $importRoot = Folder::factory()->create([
        'parent_id' => null,
        'is_basic' => false,
    ]);

    $response = $this->postJson(route('api.folders.store'), [
        'name' => 'Nested Under Import',
        'parent_id' => $importRoot->id,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('folders', [
        'name' => 'Nested Under Import',
        'parent_id' => $importRoot->id,
        'is_basic' => false,
    ]);
});
