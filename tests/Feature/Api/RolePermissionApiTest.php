<?php

/** @var \Tests\TestCase $this */

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

function grantManageUsersPermission(User $user): void
{
    $permission = Permission::firstOrCreate(
        ['slug' => 'user-management'],
        ['name' => 'User Management']
    );

    $role = Role::factory()->create();
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user->roles()->attach($role);
}

it('creates roles with permissions and can sync assignments', function () {
    $user = User::factory()->create();
    grantManageUsersPermission($user);

    $permissionResponse = $this->actingAs($user)->postJson('/api/permissions', [
        'name' => 'manage-records',
    ]);

    $permissionResponse->assertCreated();
    $permissionId = $permissionResponse->json('id');

    $roleResponse = $this->actingAs($user)->postJson('/api/roles', [
        'name' => 'records-admin',
        'permission_ids' => [$permissionId],
    ]);

    $roleResponse->assertCreated()->assertJsonCount(1, 'permissions');

    $roleId = $roleResponse->json('id');

    $this->actingAs($user)->patchJson("/api/roles/{$roleId}", [
        'permission_ids' => [],
    ])->assertOk()->assertJsonCount(0, 'permissions');

    expect(Role::find($roleId)?->permissions)->toHaveCount(0);
});
