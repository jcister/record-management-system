<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Department;
use App\Models\Positions;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([PermissionSeeder::class, RoleSeeder::class, RolePermissionSeeder::class]);
});

test('unauthorized users cannot access the dashboard', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('user-management.index'))
        ->assertForbidden();
});

test('admins can view the management page', function () {
    $admin = attachAdmin();

    $this->actingAs($admin)
        ->get(route('user-management.index'))
        ->assertRedirectToRoute('user-management.directory');
});

test('admins can view the directory page', function () {
    $admin = attachAdmin();

    $this->actingAs($admin)
        ->get(route('user-management.directory'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('UserManagement/Directory'));
});

test('admins can assign roles to a user', function () {
    $admin = attachAdmin();
    $target = User::factory()->create();
    $role = Role::query()->where('slug', 'contributor')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('user-management.users.roles.update', $target), [
            'role_ids' => [$role->id],
        ])
        ->assertRedirect();

    expect($target->fresh()->roles->pluck('id'))->toContain($role->id);
});

test('admins can create viewer accounts from the console', function () {
    $admin = attachAdmin();

    $response = $this->actingAs($admin)
        ->post(route('user-management.users.store'), [
            'prefix' => 'Dr.',
            'first_name' => 'Console',
            'middle_name' => 'Invite',
            'last_name' => 'User',
            'extension' => 'Jr.',
            'email' => 'console@example.com',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $user = User::query()->firstWhere('email', 'console@example.com');

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Dr. Console Invite User, Jr.');
    expect($user->prefix)->toBe('Dr.');
    expect($user->first_name)->toBe('Console');
    expect($user->middle_name)->toBe('Invite');
    expect($user->last_name)->toBe('User');
    expect($user->extension)->toBe('Jr.');
    expect($user->roles->first()->slug)->toBe('viewer');
});

test('admins can create accounts with an explicitly selected role from the console', function () {
    $admin = attachAdmin();
    $role = Role::query()->create([
        'name' => 'Records Liaison',
        'slug' => 'records-liaison',
        'description' => 'Coordinates records access requests.',
        'is_system' => false,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('user-management.users.store'), [
            'prefix' => 'Ms.',
            'first_name' => 'Role',
            'middle_name' => 'Selected',
            'last_name' => 'User',
            'extension' => null,
            'email' => 'role-selected@example.com',
            'role_ids' => [$role->id],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $user = User::query()->firstWhere('email', 'role-selected@example.com');

    expect($user)->not->toBeNull();
    expect($user->roles->pluck('id')->all())->toBe([$role->id]);
});

test('admins can create accounts with selected division unit position and multiple roles', function () {
    $admin = attachAdmin();
    $department = Department::factory()->create();
    $unit = Unit::query()->create([
        'name' => 'Records Operations Unit',
    ]);
    $position = Positions::query()->create([
        'name' => 'Records Officer II',
    ]);
    $liaisonRole = Role::query()->create([
        'name' => 'Records Liaison',
        'slug' => 'records-liaison-provisioning',
        'description' => 'Coordinates records access requests.',
        'is_system' => false,
    ]);
    $approverRole = Role::query()->create([
        'name' => 'Records Approver',
        'slug' => 'records-approver-provisioning',
        'description' => 'Approves records lifecycle actions.',
        'is_system' => false,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('user-management.users.store'), [
            'prefix' => 'Mr.',
            'first_name' => 'Provisioned',
            'middle_name' => 'Multi',
            'last_name' => 'Lookup',
            'extension' => null,
            'email' => 'provisioning-lookups@example.com',
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'role_ids' => [$liaisonRole->id, $approverRole->id],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $user = User::query()->firstWhere('email', 'provisioning-lookups@example.com');

    expect($user)->not->toBeNull();
    expect($user?->department_id)->toBe($department->id);
    expect($user?->unit_id)->toBe($unit->id);
    expect($user?->position_id)->toBe($position->id);
    expect($user?->roles->pluck('id')->sort()->values()->all())
        ->toBe([$approverRole->id, $liaisonRole->id]);
});

test('admins can update user details', function () {
    $admin = attachAdmin();
    $user = User::factory()->create([
        'name' => 'Old Name',
        'first_name' => 'Old',
        'last_name' => 'Name',
        'email' => 'old@example.com',
        'status' => 'inactive',
    ]);

    $this->actingAs($admin)
        ->put(route('user-management.users.update', $user), [
            'prefix' => 'Ms.',
            'first_name' => 'New',
            'middle_name' => 'Middle',
            'last_name' => 'Name',
            'extension' => 'III',
            'email' => 'new@example.com',
            'status' => 'active',
        ])
        ->assertRedirect();

    $user->refresh();

    expect($user->name)->toBe('Ms. New Middle Name, III');
    expect($user->prefix)->toBe('Ms.');
    expect($user->first_name)->toBe('New');
    expect($user->middle_name)->toBe('Middle');
    expect($user->last_name)->toBe('Name');
    expect($user->extension)->toBe('III');
    expect($user->email)->toBe('new@example.com');
    expect($user->status)->toBe('active');
});

test('admins can soft delete and restore users', function () {
    $admin = attachAdmin();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('user-management.users.destroy', $user))
        ->assertRedirect();

    $deleted = User::withTrashed()->findOrFail($user->id);
    expect($deleted->trashed())->toBeTrue();
    expect($deleted->status)->toBe('inactive');

    $this->actingAs($admin)
        ->post(route('user-management.users.restore', $user->id))
        ->assertRedirect();

    $restored = User::query()->findOrFail($user->id);
    expect($restored->trashed())->toBeFalse();
    expect($restored->status)->toBe('active');
});

test('admins can craft custom roles with static permissions', function () {
    $admin = attachAdmin();
    $permission = 'view-download';

    $this->actingAs($admin)
        ->post(route('user-management.roles.store'), [
            'name' => 'Records Liaison',
            'description' => 'Bridges divisions for retention reviews.',
            'permission_slugs' => [$permission],
        ])
        ->assertRedirect();

    $role = Role::query()->where('slug', 'records-liaison')->first();

    expect($role)->not->toBeNull();
    expect($role->permissions->pluck('slug'))->toContain($permission);
    expect($role->slug)->toBe(Str::slug('Records Liaison'));
});

test('admins cannot create roles with organize content dependent permissions without organize content', function () {
    $admin = attachAdmin();

    $this->actingAs($admin)
        ->from(route('user-management.roles.index'))
        ->post(route('user-management.roles.store'), [
            'name' => 'Restricted Cleaner',
            'description' => 'Needs content cleanup access.',
            'permission_slugs' => ['delete-content'],
        ])
        ->assertRedirect(route('user-management.roles.index'))
        ->assertSessionHasErrors('permission_slugs');

    expect(Role::query()->where('slug', 'restricted-cleaner')->exists())->toBeFalse();
});

test('admins cannot update roles with organize content dependent permissions without organize content', function () {
    $admin = attachAdmin();
    $role = Role::query()->create([
        'name' => 'Content Coordinator',
        'slug' => 'content-coordinator',
        'description' => 'Coordinates records operations.',
        'is_system' => false,
    ]);

    $this->actingAs($admin)
        ->from(route('user-management.roles.index'))
        ->put(route('user-management.roles.update', $role), [
            'name' => 'Content Coordinator',
            'description' => 'Coordinates records operations.',
            'permission_slugs' => ['review-content'],
        ])
        ->assertRedirect(route('user-management.roles.index'))
        ->assertSessionHasErrors('permission_slugs');

    expect($role->fresh()->permissions)->toHaveCount(0);
});

test('removing organize content from role update removes dependent permissions', function () {
    $admin = attachAdmin();
    $role = Role::query()->create([
        'name' => 'Records Custodian',
        'slug' => 'records-custodian',
        'description' => 'Handles records and related workflows.',
        'is_system' => false,
    ]);

    $role->permissions()->sync(
        Permission::query()
            ->whereIn('slug', ['organize-content', 'review-content', 'delete-content'])
            ->pluck('id')
            ->all()
    );

    $this->actingAs($admin)
        ->put(route('user-management.roles.update', $role), [
            'name' => 'Records Custodian',
            'description' => 'Handles records and related workflows.',
            'permission_slugs' => ['manage-requests'],
        ])
        ->assertRedirect();

    $role->refresh();

    $permissionSlugs = $role->permissions()->pluck('slug')->all();

    expect($permissionSlugs)->toContain('manage-requests');
    expect($permissionSlugs)->not->toContain('organize-content');
    expect($permissionSlugs)->not->toContain('review-content');
    expect($permissionSlugs)->not->toContain('delete-content');
});

test('admins can update role name and description', function () {
    $admin = attachAdmin();
    $role = Role::query()->create([
        'name' => 'Legacy Approver',
        'slug' => 'legacy-approver',
        'description' => 'Old description',
        'is_system' => false,
    ]);

    $this->actingAs($admin)
        ->put(route('user-management.roles.update', $role), [
            'name' => 'Records Approver',
            'description' => 'Updated role summary',
            'permission_slugs' => [],
        ])
        ->assertRedirect();

    $role->refresh();

    expect($role->name)->toBe('Records Approver');
    expect($role->description)->toBe('Updated role summary');
});

test('admins can soft delete and restore roles', function () {
    $admin = attachAdmin();
    $role = Role::query()->create([
        'name' => 'Temporary Reviewer',
        'slug' => 'temporary-reviewer',
        'description' => 'Used for temporary workloads',
        'is_system' => false,
    ]);

    $this->actingAs($admin)
        ->delete(route('user-management.roles.destroy', $role))
        ->assertRedirect();

    $deleted = Role::withTrashed()->findOrFail($role->id);
    expect($deleted->trashed())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('user-management.roles.restore', $role->id))
        ->assertRedirect();

    $restored = Role::query()->findOrFail($role->id);
    expect($restored->trashed())->toBeFalse();
});

test('admins can soft delete system roles', function () {
    $admin = attachAdmin();
    $role = Role::query()->create([
        'name' => 'System Only Role',
        'slug' => 'system-only-role',
        'description' => 'Protected role',
        'is_system' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('user-management.roles.destroy', $role))
        ->assertRedirect();

    $deleted = Role::withTrashed()->findOrFail($role->id);
    expect($deleted->trashed())->toBeTrue();
});

test('admins cannot soft delete roles assigned to users', function () {
    $admin = attachAdmin();
    $role = Role::query()->create([
        'name' => 'Assigned Role',
        'slug' => 'assigned-role',
        'description' => 'Assigned to users',
        'is_system' => false,
    ]);
    $assignee = User::factory()->create();
    $assignee->roles()->sync([$role->id]);

    $this->actingAs($admin)
        ->delete(route('user-management.roles.destroy', $role))
        ->assertRedirect();

    $notDeleted = Role::withTrashed()->findOrFail($role->id);
    expect($notDeleted->trashed())->toBeFalse();
});

function attachAdmin(): User
{
    $user = User::factory()->create();
    $role = Role::query()->where('slug', 'admin')->firstOrFail();

    $user->roles()->sync([$role->id]);

    return $user;
}
