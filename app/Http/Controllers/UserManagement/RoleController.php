<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManagedRoleRequest;
use App\Http\Requests\UpdateManagedRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RoleController extends Controller
{
    private const ORGANIZE_CONTENT_PERMISSION = 'organize-content';

    /**
     * @var array<int, string>
     */
    private const ORGANIZE_CONTENT_DEPENDENT_PERMISSIONS = [
        'review-content',
        'delete-content',
    ];

    public function index()
    {
        $roles = Role::query()
            ->withTrashed()
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
                'deleted_at' => $role->deleted_at?->toISOString(),
                'permissions' => $role->permissions->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                ]),
            ])
            ->values()
            ->all();

        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description'])
            ->values()
            ->all();

        return Inertia::render('UserManagement/Roles', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function storeRole(StoreManagedRoleRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $permissionSlugs = $this->normalizePermissionSlugs($payload['permission_slugs'] ?? []);

        $role = Role::query()->create([
            'name' => $payload['name'],
            'slug' => $payload['slug'],
            'description' => $payload['description'] ?? null,
            'is_system' => $payload['is_system'] ?? false,
            'metadata' => $payload['metadata'] ?? null,
        ]);

        $role->permissions()->sync($this->permissionIdsFromSlugs($permissionSlugs));

        return back()->with('flash.banner', 'Role created.');
    }

    public function updateRole(UpdateManagedRoleRequest $request, Role $role): RedirectResponse
    {
        $payload = $request->validated();
        $permissionSlugs = $this->normalizePermissionSlugs($payload['permission_slugs'] ?? []);

        $role->fill([
            'name' => $payload['name'] ?? $role->name,
            'slug' => $payload['slug'] ?? $role->slug,
            'description' => $payload['description'] ?? $role->description,
        ])->save();

        $role->permissions()->sync($this->permissionIdsFromSlugs($permissionSlugs));

        return back()->with('flash.banner', 'Role updated.');
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            return back()->with('flash.banner', 'Roles assigned to users cannot be deleted.')->with('flash.bannerStyle', 'danger');
        }

        if ($role->trashed()) {
            return back()->with('flash.banner', 'Role is already deleted.');
        }

        $role->delete();

        return back()->with('flash.banner', 'Role deleted.');
    }

    public function restoreRole(string|int $role): RedirectResponse
    {
        $restorable = Role::withTrashed()->findOrFail($role);

        if (! $restorable->trashed()) {
            return back()->with('flash.banner', 'Role is already active.');
        }

        $restorable->restore();

        return back()->with('flash.banner', 'Role restored.');
    }

    private function permissionIdsFromSlugs(array $slugs): array
    {
        return Permission::query()
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, mixed>  $slugs
     * @return array<int, string>
     */
    private function normalizePermissionSlugs(array $slugs): array
    {
        $normalizedSlugs = array_values(array_unique(array_filter($slugs, static fn (mixed $slug): bool => is_string($slug))));

        if (in_array(self::ORGANIZE_CONTENT_PERMISSION, $normalizedSlugs, true)) {
            return $normalizedSlugs;
        }

        return array_values(array_filter(
            $normalizedSlugs,
            static fn (string $slug): bool => ! in_array($slug, self::ORGANIZE_CONTENT_DEPENDENT_PERMISSIONS, true)
        ));
    }
}
