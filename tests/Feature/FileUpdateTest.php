<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

function grantUploadContentPermission(User $user): void
{
    $permission = Permission::firstOrCreate(
        ['slug' => 'upload-submit-reports'],
        ['name' => 'Upload Content']
    );

    $role = Role::factory()->create();
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

it('blocks renaming when file_name would collide with another file in the same folder', function (): void {
    $user = User::factory()->create();
    grantUploadContentPermission($user);

    $folder = Folder::factory()->create();

    $existing = File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'Log 1',
    ]);

    $file = File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'Log 1 (1)',
    ]);

    $response = $this->actingAs($user)->patchJson(route('api.files.update', $file->id), [
        'file_name' => 'Log 1',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJson([
            'code' => 'duplicate_filename',
            'duplicate_id' => $existing->id,
        ]);
});
