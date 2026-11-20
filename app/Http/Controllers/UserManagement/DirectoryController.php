<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManagedUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Http\Requests\UpdateUserRolesRequest;
use App\Mail\UserCreatedNotification;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Positions;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DirectoryController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(): Response
    {
        $users = User::query()
            ->withTrashed()
            ->with('roles:id,name,slug')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'prefix' => $user->prefix,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'extension' => $user->extension,
                'email' => $user->email,
                'avatar' => $user->avatar ? '/storage/'.$user->avatar : null,
                'status' => $user->status,
                'deleted_at' => $user->deleted_at,
                'department_id' => $user->department_id,
                'unit_id' => $user->unit_id,
                'position_id' => $user->position_id,
                'roles' => $user->roles->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ]),
                'created_at' => $user->created_at,
            ])
            ->values()
            ->all();

        $roles = Role::query()
            ->where('slug', '!=', 'super-admin')
            ->with('permissions:id,name,slug')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                ]),
            ])
            ->values()
            ->all();

        $departments = Department::query()
            ->withTrashed()
            ->orderBy('name')
            ->get(['id', 'name', 'deleted_at']);

        $units = Unit::query()
            ->withTrashed()
            ->orderBy('name')
            ->get(['id', 'name', 'deleted_at']);

        $positions = Positions::query()
            ->withTrashed()
            ->orderBy('name')
            ->get(['id', 'name', 'deleted_at']);

        return Inertia::render('UserManagement/Directory', [
            'users' => $users,
            'roles' => $roles,
            'defaultRoleSlug' => 'viewer',
            'departments' => $departments,
            'units' => $units,
            'positions' => $positions,
        ]);
    }

    public function storeUser(StoreManagedUserRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        // Generate a random temporary password
        $temporaryPassword = Str::random(16);

        $user = User::query()->create([
            'name' => $payload['name'],
            'prefix' => $payload['prefix'] ?? null,
            'first_name' => $payload['first_name'],
            'middle_name' => $payload['middle_name'] ?? null,
            'last_name' => $payload['last_name'],
            'extension' => $payload['extension'] ?? null,
            'email' => $payload['email'],
            'password' => Hash::make($temporaryPassword),
            'email_verified_at' => now(),
            'password_reset_required' => true,
        ]);

        $roleIds = $payload['role_ids'] ?? [];

        $this->syncUserRoles($user, $roleIds);

        // Send email notification with temporary password
        $loginUrl = route('login');
        Mail::to($user->email)->send(new UserCreatedNotification($user, $temporaryPassword, $loginUrl));

        $this->audit->log(
            'user_created',
            $request->user()?->name.' created user "'.$user->name.'" and sent temporary password via email.',
            $user
        );

        return back()->with('flash.banner', 'User account created and email notification sent.');
    }

    public function updateUserRoles(UpdateUserRolesRequest $request, User $user): RedirectResponse
    {
        $payload = $request->validated();

        $this->syncUserRoles($user, $payload['role_ids'] ?? []);

        $this->audit->log(
            'user_roles_updated',
            $request->user()?->name.' updated roles for "'.$user->name.'".',
            $user,
            ['role_ids' => $payload['role_ids'] ?? []]
        );

        return back()->with('flash.banner', 'User roles updated.');
    }

    public function updateUser(UpdateManagedUserRequest $request, User $user): RedirectResponse
    {
        $payload = $request->validated();

        if ($user->trashed()) {
            return back()->with('flash.banner', 'Restore the user before editing details.')->with('flash.bannerStyle', 'danger');
        }

        $currentUser = $request->user();
        $targetIsSuperAdmin = $user->roles()->where('slug', 'super-admin')->exists();

        if ($targetIsSuperAdmin) {
            $currentIsSuperAdmin = $currentUser && $currentUser->roles()->where('slug', 'super-admin')->exists();

            if (! $currentIsSuperAdmin) {
                abort(403, 'Only Super Administrators can update Super Administrator accounts.');
            }
        }

        if (array_key_exists('status', $payload)) {
            if ($currentUser && $currentUser->id === $user->id) {
                return back()->with('flash.banner', 'You cannot change your own status.')->with('flash.bannerStyle', 'danger');
            }

            $user->status = $payload['status'];
        }

        $user->fill([
            'name' => $payload['name'] ?? $user->name,
            'prefix' => $payload['prefix'] ?? $user->prefix,
            'first_name' => $payload['first_name'] ?? $user->first_name,
            'middle_name' => $payload['middle_name'] ?? $user->middle_name,
            'last_name' => $payload['last_name'] ?? $user->last_name,
            'extension' => $payload['extension'] ?? $user->extension,
            'email' => $payload['email'] ?? $user->email,
            'department_id' => $payload['department_id'] ?? $user->department_id,
            'unit_id' => $payload['unit_id'] ?? $user->unit_id,
            'position_id' => $payload['position_id'] ?? $user->position_id,
        ])->save();

        $this->audit->log(
            'user_updated',
            $currentUser?->name.' updated details for "'.$user->name.'".',
            $user,
            $payload
        );

        return back()->with('flash.banner', 'User profile updated.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        $currentUser = request()->user();

        // Only Admin or SuperAdmin can delete users
        if (! $currentUser || ! $currentUser->roles()->whereIn('slug', ['admin', 'super-admin'])->exists()) {
            abort(403, 'Only Administrators or Super Administrators can delete user accounts.');
        }

        // Prevent self-deletion
        if ($currentUser->id === $user->id) {
            return back()->with('flash.banner', 'You cannot delete your own account.')->with('flash.bannerStyle', 'danger');
        }

        // Check if user being deleted is a SuperAdmin
        $isSuperAdmin = $user->roles()->where('slug', 'super-admin')->exists();

        // Only SuperAdmin can delete другого SuperAdmin
        if ($isSuperAdmin) {
            $currentUserIsSuperAdmin = $currentUser && $currentUser->roles()->where('slug', 'super-admin')->exists();

            if (! $currentUserIsSuperAdmin) {
                abort(403, 'Only Super Administrators can delete Super Administrator accounts.');
            }
        }

        // Delete avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->status = 'inactive';
        $user->save();

        // Soft delete the user
        $user->delete();

        $this->audit->log(
            'user_deleted',
            $currentUser?->name.' soft-deleted user "'.$user->name.'".',
            $user
        );

        return back()->with('flash.banner', 'User account deleted.');
    }

    public function restoreUser(Request $request, string|int $user): RedirectResponse
    {
        $currentUser = $request->user();

        if (! $currentUser || ! $currentUser->roles()->whereIn('slug', ['admin', 'super-admin'])->exists()) {
            abort(403, 'Only Administrators or Super Administrators can restore user accounts.');
        }

        $restorable = User::withTrashed()->findOrFail($user);

        if (! $restorable->trashed()) {
            return back()->with('flash.banner', 'User is already active.');
        }

        $isSuperAdmin = $restorable->roles()->where('slug', 'super-admin')->exists();

        if ($isSuperAdmin) {
            $currentUserIsSuperAdmin = $currentUser && $currentUser->roles()->where('slug', 'super-admin')->exists();

            if (! $currentUserIsSuperAdmin) {
                abort(403, 'Only Super Administrators can restore Super Administrator accounts.');
            }
        }

        $restorable->status = 'active';
        $restorable->restore();

        $this->audit->log(
            'user_restored',
            $currentUser?->name.' restored user "'.$restorable->name.'".',
            $restorable
        );

        return back()->with('flash.banner', 'User account restored.');
    }

    public function updateUserStatus(User $user, Request $request): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        $currentUser = $request->user();

        // Prevent changing own status
        if ($currentUser && $currentUser->id === $user->id) {
            return back()->with('flash.banner', 'You cannot change your own status.')->with('flash.bannerStyle', 'danger');
        }

        // Prevent changing SuperAdmin status unless current user is SuperAdmin
        $isSuperAdmin = $user->roles()->where('slug', 'super-admin')->exists();

        if ($isSuperAdmin) {
            $currentUserIsSuperAdmin = $currentUser && $currentUser->roles()->where('slug', 'super-admin')->exists();

            if (! $currentUserIsSuperAdmin) {
                abort(403, 'Only Super Administrators can change Super Administrator status.');
            }
        }

        $user->status = $request->status;
        $user->save();

        $this->audit->log(
            'user_status_updated',
            $currentUser?->name.' changed status of "'.$user->name.'" to '.$request->status.'.',
            $user,
            ['old_status' => $user->getOriginal('status'), 'new_status' => $request->status]
        );

        return back()->with('flash.banner', 'User status updated.');
    }

    public function storeRole(StoreManagedRoleRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        $role = Role::query()->create([
            'name' => $payload['name'],
            'slug' => $payload['slug'],
            'description' => $payload['description'] ?? null,
            'is_system' => $payload['is_system'] ?? false,
            'metadata' => $payload['metadata'] ?? null,
        ]);

        $role->permissions()->sync($this->permissionIdsFromSlugs($payload['permission_slugs'] ?? []));

        return back()->with('flash.banner', 'Role created.');
    }

    public function updateRole(UpdateManagedRoleRequest $request, Role $role): RedirectResponse
    {
        $payload = $request->validated();

        $role->fill([
            'name' => $payload['name'] ?? $role->name,
            'slug' => $payload['slug'] ?? $role->slug,
            'description' => $payload['description'] ?? $role->description,
        ])->save();

        $role->permissions()->sync($this->permissionIdsFromSlugs($payload['permission_slugs'] ?? []));

        return back()->with('flash.banner', 'Role updated.');
    }

    private function syncUserRoles(User $user, array $roleIds): void
    {
        $ids = collect($roleIds)->filter()->all();

        if ($ids === []) {
            $defaultRoleId = Role::query()->where('slug', 'viewer')->value('id');

            if ($defaultRoleId) {
                $ids = [$defaultRoleId];
            }
        }

        $ids = Role::query()
            ->whereIn('id', $ids)
            ->where('slug', '!=', 'super-admin')
            ->pluck('id')
            ->all();

        $superAdminRoleId = Role::query()->where('slug', 'super-admin')->value('id');

        if ($superAdminRoleId && $user->roles()->whereKey($superAdminRoleId)->exists()) {
            $ids[] = $superAdminRoleId;
        }

        $user->roles()->sync(array_values(array_unique($ids)));
    }
}
