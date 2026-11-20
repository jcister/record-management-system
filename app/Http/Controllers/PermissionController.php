<?php

namespace App\Http\Controllers;

use App\Http\Requests\PermissionRequest;
use App\Models\Permission;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PermissionController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate(15);

        return response()->json($permissions);
    }

    public function store(PermissionRequest $request): JsonResponse
    {
        $permission = $this->savePermission(new Permission, $request->validated());

        return response()->json($permission->load('roles'), 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json($permission->load('roles'));
    }

    public function update(PermissionRequest $request, Permission $permission): JsonResponse
    {
        $permission = $this->savePermission($permission, $request->validated());

        return response()->json($permission->load('roles'));
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $roleIds = $permission->roles()->pluck('roles.id')->toArray();

        $permission->roles()->detach();

        if (! empty($roleIds)) {
            $this->audit->log(
                'permission_roles_detached',
                'Detached all roles from permission "'.$permission->name.'" before deletion.',
                $permission,
                ['role_ids' => $roleIds]
            );
        }

        $permission->delete();

        return response()->json(null, 204);
    }

    protected function savePermission(Permission $permission, array $attributes): Permission
    {
        $shouldSyncRoles = array_key_exists('role_ids', $attributes);
        $roleIds = $attributes['role_ids'] ?? [];
        unset($attributes['role_ids']);

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

        $fillable['guard_name'] = $attributes['guard_name'] ?? $permission->guard_name ?? 'web';

        $permission->fill($fillable)->save();

        if ($shouldSyncRoles) {
            $currentRoleIds = $permission->roles()->pluck('roles.id')->toArray();
            $permission->roles()->sync($roleIds);
            $newRoleIds = $permission->roles()->pluck('roles.id')->toArray();

            $added = array_values(array_diff($newRoleIds, $currentRoleIds));
            $removed = array_values(array_diff($currentRoleIds, $newRoleIds));

            if (! empty($added) || ! empty($removed)) {
                $this->audit->log(
                    'permission_roles_synced',
                    'Synced roles for permission "'.$permission->name.'".',
                    $permission,
                    [
                        'added_role_ids' => $added,
                        'removed_role_ids' => $removed,
                        'total_roles' => count($newRoleIds),
                    ]
                );
            }
        }

        return $permission->refresh();
    }
}
