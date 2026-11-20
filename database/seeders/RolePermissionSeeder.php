<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionSlugs = Permission::query()->pluck('id', 'slug');

        $rolePermissions = [
            'super-admin' => $permissionSlugs->keys()->all(),
            // 'admin' => [
            //     'user-management',
            //     'retention-logs',
            //     'review-content',
            //     'upload-submit-reports',
            //     'organize-content',
            //     'delete-content',
            //     'view-download',
            //     'manage-requests',
            //     'manage-reports',
            // ],
            // 'records-officer' => [
            //     'retention-logs',
            //     'review-content',
            //     'upload-submit-reports',
            //     'organize-content',
            //     'view-download',
            //     'manage-requests',
            // ],
            // 'regional-director' => [
            //     'review-content',
            //     'upload-submit-reports',
            //     'organize-content',
            //     'view-download',
            //     'manage-requests',
            // ],
            // 'reviewer' => [
            //     'review-content',
            //     'upload-submit-reports',
            //     'organize-content',
            //     'view-download',
            // ],
            // 'contributor' => [
            //     'upload-submit-reports',
            //     'organize-content',
            //     'view-download',
            // ],
            // 'viewer' => [
            //     'view-download',
            // ],
        ];

        foreach ($rolePermissions as $roleSlug => $permissions) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $permissionIds = collect($permissions)
                ->map(fn (string $slug) => $permissionSlugs->get($slug))
                ->filter()
                ->all();

            if ($roleSlug === 'super-admin') {
                $permissionIds = $permissionSlugs->values()->all();
            }

            $role->permissions()->sync($permissionIds);
        }
    }
}
