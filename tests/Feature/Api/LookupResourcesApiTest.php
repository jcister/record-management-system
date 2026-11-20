<?php

use App\Models\Department;
use App\Models\Permission;
use App\Models\Positions;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

function grantManageUsersPermissionForLookupApi(User $user): void
{
    $permission = Permission::firstOrCreate(
        ['slug' => 'user-management'],
        ['name' => 'User Management']
    );

    $role = Role::factory()->create();
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user->roles()->attach($role);
}

dataset('lookup-resources', [
    'departments' => [Department::class, 'departments'],
    'units' => [Unit::class, 'units'],
    'positions' => [Positions::class, 'positions'],
]);

it('updates lookup resources for authorized users', function (string $modelClass, string $resource): void {
    $user = User::factory()->create();
    grantManageUsersPermissionForLookupApi($user);

    $item = $modelClass::query()->create(['name' => 'Original '.Str::random(5)]);
    $newName = 'Updated '.Str::random(6);

    $this->actingAs($user)
        ->patchJson("/api/{$resource}/{$item->id}", ['name' => $newName])
        ->assertOk()
        ->assertJsonPath('data.id', $item->id)
        ->assertJsonPath('data.name', $newName);

    expect($item->fresh()?->name)->toBe($newName);
})->with('lookup-resources');

it('rejects lookup resource updates for unauthorized users', function (string $modelClass, string $resource): void {
    $user = User::factory()->create();
    $item = $modelClass::query()->create(['name' => 'Original '.Str::random(5)]);

    $this->actingAs($user)
        ->patchJson("/api/{$resource}/{$item->id}", ['name' => 'Blocked'])
        ->assertForbidden();
})->with('lookup-resources');

it('validates required name on lookup resource updates', function (string $modelClass, string $resource): void {
    $user = User::factory()->create();
    grantManageUsersPermissionForLookupApi($user);

    $item = $modelClass::query()->create(['name' => 'Original '.Str::random(5)]);

    $this->actingAs($user)
        ->patchJson("/api/{$resource}/{$item->id}", ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
})->with('lookup-resources');

it('validates unique names on lookup resource updates', function (string $modelClass, string $resource): void {
    $user = User::factory()->create();
    grantManageUsersPermissionForLookupApi($user);

    $existingName = 'Existing '.Str::random(5);
    $modelClass::query()->create(['name' => $existingName]);
    $item = $modelClass::query()->create(['name' => 'Current '.Str::random(5)]);

    $this->actingAs($user)
        ->patchJson("/api/{$resource}/{$item->id}", ['name' => $existingName])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
})->with('lookup-resources');
