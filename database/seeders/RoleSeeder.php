<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full platform control across explicit and tacit knowledge domains.',
                'is_system' => true,
                'metadata' => ['scope' => 'Platform governance'],
            ],
            // [
            //     'name' => 'Admin',
            //     'slug' => 'admin',
            //     'description' => 'Day-to-day platform administration without full system privileges.',
            //     'is_system' => true,
            //     'metadata' => ['scope' => 'Operational administration'],
            // ],
            // [
            //     'name' => 'Records Officer',
            //     'slug' => 'records-officer',
            //     'description' => 'Manages retention policies, physical tracking, and compliance queues.',
            //     'is_system' => true,
            //     'metadata' => ['focus' => 'ISO compliance'],
            // ],
            // [
            //     'name' => 'Regional Director',
            //     'slug' => 'regional-director',
            //     'description' => 'Oversees approvals, wisdom capture, and strategic knowledge assets.',
            //     'is_system' => true,
            //     'metadata' => ['focus' => 'Executive insights'],
            // ],
            // [
            //     'name' => 'Reviewer',
            //     'slug' => 'reviewer',
            //     'description' => 'Handles queues for content approvals and quality checks.',
            //     'is_system' => true,
            //     'metadata' => ['focus' => 'Workflow QA'],
            // ],
            // [
            //     'name' => 'Contributor',
            //     'slug' => 'contributor',
            //     'description' => 'Uploads documents, travel reports, mentoring notes, and other knowledge entries.',
            //     'is_system' => true,
            //     'metadata' => ['focus' => 'Content submission'],
            // ],
            // [
            //     'name' => 'Requestor',
            //     'slug' => 'viewer',
            //     'description' => 'Read-only access to approved records and reference libraries.',
            //     'is_system' => true,
            //     'metadata' => ['focus' => 'Knowledge consumption'],
            // ],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
