<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'Organize Content',
                'slug' => 'organize-content',
                'description' => 'Arrange and categorize content within the system for better accessibility and management.',
            ],
            [
                'name' => 'Delete Content',
                'slug' => 'delete-content',
                'description' => 'Remove content from the system, including files and folders.',
            ],
            [
                'name' => 'Disposal Management',
                'slug' => 'disposal-management',
                'description' => 'Manage retention schedules, audit trails, and physical location tracking.',
            ],
            [
                'name' => 'User Management',
                'slug' => 'user-management',
                'description' => 'Administer users, roles, and access scopes across CHED ONSE KMIS.',
            ],
            [
                'name' => 'Manage Requests',
                'slug' => 'manage-requests',
                'description' => 'Handle user requests and related workflows within the system.',
            ],
            [
                'name' => 'Manage Reports',
                'slug' => 'manage-reports',
                'description' => 'Manage system reports, including generating, viewing, and exporting various reports related to content, user activity, and system performance.',
            ],
            [
                'name' => 'Review Content',
                'slug' => 'review-content',
                'description' => 'Access and manage content that is pending review, including approving or rejecting submissions and providing feedback to users.',
            ],
            [
                'name' => 'Agency Information Management',
                'slug' => 'manage-agency-information',
                'description' => 'Manage agency details and regional director information used in generated documents.',
            ],
            [
                'name' => 'View Audit Trail',
                'slug' => 'view-audit-trail',
                'description' => 'View audit trail for system activities and changes.',
            ],

        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'is_system' => true,
                ]
            );
        }
    }
}
