<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->paginate(15);

        return response()->json($roles);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = $this->saveRole(new Role, $request->validated());

        return response()->json($role->load('permissions'), 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json($role->load('permissions'));
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->saveRole($role, $request->validated());

        return response()->json($role->load('permissions'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $permissionIds = $role->permissions()->pluck('permissions.id')->toArray();

        $role->permissions()->detach();

        if (! empty($permissionIds)) {
            $this->audit->log(
                'role_permissions_detached',
                'Detached all permissions from role "'.$role->name.'" before deletion.',
                $role,
                ['permission_ids' => $permissionIds]
            );
        }

        $role->delete();

        return response()->json(null, 204);
    }

    protected function saveRole(Role $role, array $attributes): Role
    {
        $shouldSyncPermissions = array_key_exists('permission_ids', $attributes);
        $permissionIds = $attributes['permission_ids'] ?? [];
        unset($attributes['permission_ids']);

        $fillable = [];

        if (array_key_exists('name', $attributes)) {
            $fillable['name'] = $attributes['name'];
            if (! array_key_exists('slug', $attributes)) {
                $fillable['slug'] = Str::slug($attributes['name']);
            }
        }

        if (array_key_exists('slug', $attributes)) {
            $fillable['slug'] = $attributes['slug'];
        }

        $fillable['guard_name'] = $attributes['guard_name'] ?? $role->guard_name ?? 'web';

        $role->fill($fillable)->save();

        if ($shouldSyncPermissions) {
            $currentPermissionIds = $role->permissions()->pluck('permissions.id')->toArray();
            $role->permissions()->sync($permissionIds);
            $newPermissionIds = $role->permissions()->pluck('permissions.id')->toArray();

            $added = array_values(array_diff($newPermissionIds, $currentPermissionIds));
            $removed = array_values(array_diff($currentPermissionIds, $newPermissionIds));

            if (! empty($added) || ! empty($removed)) {
                $this->audit->log(
                    'role_permissions_synced',
                    'Synced permissions for role "'.$role->name.'".',
                    $role,
                    [
                        'added_permission_ids' => $added,
                        'removed_permission_ids' => $removed,
                        'total_permissions' => count($newPermissionIds),
                    ]
                );
            }
        }

        return $role->refresh();
    }
}
