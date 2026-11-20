<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

function grantDeleteContentPermission(User $user): void
{
    $permission = Permission::firstOrCreate(
        ['slug' => 'delete-content'],
        ['name' => 'Delete Content']
    );

    $role = Role::factory()->create();
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user->roles()->attach($role);
}

it('restores a file to imported records when its parent folder remains trashed', function (): void {
    $user = User::factory()->create();
    grantDeleteContentPermission($user);

    $parentFolder = Folder::factory()->create(['created_by' => $user->id]);
    $file = File::factory()->create([
        'folder_id' => $parentFolder->id,
        'uploaded_by' => $user->id,
    ]);

    $parentFolder->delete();
    $file->delete();

    $response = $this->actingAs($user)->postJson(route('api.files.restore', ['file' => $file->id]));

    $response->assertOk();

    $file->refresh();

    expect($file->folder_id)->toBeNull();
    expect($file->trashed())->toBeFalse();
});

it('restores a file back to its folder when the parent folder is active', function (): void {
    $user = User::factory()->create();
    grantDeleteContentPermission($user);

    $parentFolder = Folder::factory()->create(['created_by' => $user->id]);
    $file = File::factory()->create([
        'folder_id' => $parentFolder->id,
        'uploaded_by' => $user->id,
    ]);

    $file->delete();

    $response = $this->actingAs($user)->postJson(route('api.files.restore', ['file' => $file->id]));

    $response->assertOk();

    $file->refresh();

    expect($file->folder_id)->toBe($parentFolder->id);
    expect($file->trashed())->toBeFalse();
});

it('restores a nested folder to the root when its parent remains trashed', function (): void {
    $user = User::factory()->create();
    grantDeleteContentPermission($user);

    $parentFolder = Folder::factory()->create(['created_by' => $user->id]);
    $childFolder = Folder::factory()->create([
        'parent_id' => $parentFolder->id,
        'created_by' => $user->id,
    ]);

    $parentFolder->delete();
    $childFolder->delete();

    $response = $this->actingAs($user)->postJson(route('api.folders.restore', ['folder' => $childFolder->id]));

    $response->assertOk();

    $childFolder->refresh();

    expect($childFolder->parent_id)->toBeNull();
    expect($childFolder->trashed())->toBeFalse();
});

it('soft deletes child folders and files when a parent folder is trashed', function (): void {
    $user = User::factory()->create();

    $parentFolder = Folder::factory()->create(['created_by' => $user->id]);
    $childFolder = Folder::factory()->create([
        'parent_id' => $parentFolder->id,
        'created_by' => $user->id,
    ]);

    $fileInParent = File::factory()->create([
        'folder_id' => $parentFolder->id,
        'uploaded_by' => $user->id,
    ]);

    $fileInChild = File::factory()->create([
        'folder_id' => $childFolder->id,
        'uploaded_by' => $user->id,
    ]);

    $parentFolder->delete();

    expect($parentFolder->trashed())->toBeTrue();
    expect($childFolder->fresh()->trashed())->toBeTrue();
    expect($fileInParent->fresh()->trashed())->toBeTrue();
    expect($fileInChild->fresh()->trashed())->toBeTrue();
});

it('restores child folders and files when restoring the parent folder', function (): void {
    $user = User::factory()->create();
    grantDeleteContentPermission($user);

    $parentFolder = Folder::factory()->create(['created_by' => $user->id]);
    $childFolder = Folder::factory()->create([
        'parent_id' => $parentFolder->id,
        'created_by' => $user->id,
    ]);

    $fileInParent = File::factory()->create([
        'folder_id' => $parentFolder->id,
        'uploaded_by' => $user->id,
    ]);

    $fileInChild = File::factory()->create([
        'folder_id' => $childFolder->id,
        'uploaded_by' => $user->id,
    ]);

    $parentFolder->delete();

    expect($parentFolder->fresh()->trashed())->toBeTrue();
    expect($childFolder->fresh()->trashed())->toBeTrue();
    expect($fileInParent->fresh()->trashed())->toBeTrue();
    expect($fileInChild->fresh()->trashed())->toBeTrue();

    $response = $this->actingAs($user)->postJson(route('api.folders.restore', ['folder' => $parentFolder->id]));

    $response->assertOk();

    expect($parentFolder->fresh()->trashed())->toBeFalse();
    expect($childFolder->fresh()->trashed())->toBeFalse();
    expect($fileInParent->fresh()->trashed())->toBeFalse();
    expect($fileInChild->fresh()->trashed())->toBeFalse();
});

it('blocks restoring when a duplicate file name exists in the target folder', function (): void {
    $user = User::factory()->create();
    grantDeleteContentPermission($user);

    $folder = Folder::factory()->create();

    $existing = File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'report',
    ]);

    $trashed = File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'report',
    ]);

    $trashed->delete();

    $response = $this->actingAs($user)->postJson(route('api.files.restore', ['file' => $trashed->id]));

    $response
        ->assertUnprocessable()
        ->assertJson([
            'code' => 'duplicate_filename',
            'duplicate_id' => $existing->id,
        ]);

    expect(File::withTrashed()->find($trashed->id)?->trashed())->toBeTrue();
});

it('restores when the unorganized duplicate is a pending scan record', function (): void {
    $user = User::factory()->create();
    grantDeleteContentPermission($user);

    File::factory()->create([
        'folder_id' => null,
        'file_name' => 'CAO-09-2026',
        'record_id' => 'SCAN-EDGE-001',
        'status' => 'pending',
    ]);

    $parentFolder = Folder::factory()->create(['created_by' => $user->id]);
    $trashed = File::factory()->create([
        'folder_id' => $parentFolder->id,
        'file_name' => 'CAO-09-2026',
        'uploaded_by' => $user->id,
    ]);

    $parentFolder->delete();
    $trashed->delete();

    $response = $this->actingAs($user)->postJson(route('api.files.restore', ['file' => $trashed->id]));

    $response->assertOk();

    $restored = File::find($trashed->id);

    expect($restored)->not->toBeNull();
    expect($restored?->folder_id)->toBeNull();
    expect($restored?->file_name)->toBe('CAO-09-2026');
    expect($restored?->trashed())->toBeFalse();
});

it('still blocks restoring when an unorganized non-pending duplicate exists', function (): void {
    $user = User::factory()->create();
    grantDeleteContentPermission($user);

    $existing = File::factory()->create([
        'folder_id' => null,
        'file_name' => 'CAO-10-2026',
        'record_id' => 'REC-'.strtoupper(uniqid()),
        'status' => 'approved',
    ]);

    $parentFolder = Folder::factory()->create(['created_by' => $user->id]);
    $trashed = File::factory()->create([
        'folder_id' => $parentFolder->id,
        'file_name' => 'CAO-10-2026',
        'uploaded_by' => $user->id,
    ]);

    $parentFolder->delete();
    $trashed->delete();

    $response = $this->actingAs($user)->postJson(route('api.files.restore', ['file' => $trashed->id]));

    $response
        ->assertUnprocessable()
        ->assertJson([
            'code' => 'duplicate_filename',
            'duplicate_id' => $existing->id,
            'conflict_scope' => 'unorganized_records',
        ]);

    expect(File::withTrashed()->find($trashed->id)?->trashed())->toBeTrue();
});

it('renames the restored file when rename strategy is requested', function (): void {
    $user = User::factory()->create();
    grantDeleteContentPermission($user);

    $folder = Folder::factory()->create();

    File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'report',
    ]);

    File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'report (1)',
    ]);

    $trashed = File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'report',
    ]);

    $trashed->delete();

    $response = $this->actingAs($user)->postJson(route('api.files.restore', ['file' => $trashed->id]), [
        'strategy' => 'rename',
    ]);

    $response->assertOk();

    $restored = File::find($trashed->id);

    expect($restored)->not->toBeNull();
    expect($restored?->file_name)->toBe('report (2)');
});

it('replaces the existing file when replace strategy is requested', function (): void {
    $user = User::factory()->create();
    grantDeleteContentPermission($user);

    $folder = Folder::factory()->create();

    $existing = File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'report',
    ]);

    $trashed = File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'report',
    ]);

    $trashed->delete();

    $response = $this->actingAs($user)->postJson(route('api.files.restore', ['file' => $trashed->id]), [
        'strategy' => 'replace',
    ]);

    $response->assertOk();

    $refreshedExisting = File::find($existing->id);
    $restoredSource = File::withTrashed()->find($trashed->id);

    expect($refreshedExisting)->not->toBeNull();
    expect($refreshedExisting?->trashed())->toBeFalse();
    expect($restoredSource?->trashed())->toBeTrue();
    expect($refreshedExisting?->latest_version_number)->toBe(1);
});

it('blocks restoring when file_name collides even if original filenames differ', function (): void {
    $user = User::factory()->create();
    grantDeleteContentPermission($user);

    $folder = Folder::factory()->create();

    $existing = File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'shared',
    ]);

    $trashed = File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'shared',
    ]);

    $trashed->delete();

    $response = $this->actingAs($user)->postJson(route('api.files.restore', ['file' => $trashed->id]));

    $response
        ->assertUnprocessable()
        ->assertJson([
            'code' => 'duplicate_filename',
            'duplicate_id' => $existing->id,
        ]);

    expect(File::withTrashed()->find($trashed->id)?->trashed())->toBeTrue();
});
