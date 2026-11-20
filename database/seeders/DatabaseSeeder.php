<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
            DepartmentSeeder::class,
            UnitSeeder::class,
            PositionSeeder::class,
            UserSeeder::class,
            RequestSeeder::class,
            ReportSampleSeeder::class,
            FolderSeeder::class
        ]);

        // $adminRole = Role::query()->where('slug', 'admin')->first();
        // $superAdminRole = Role::query()->where('slug', 'super-admin')->first();

        // $adminAccounts = [
        //     [
        //         'name' => 'Test User',
        //         'email' => 'test@example.com',
        //         'password' => 'P@ssw0rd123',
        //     ],
        // ];

        // foreach ($adminAccounts as $account) {
        //     $user = User::query()->updateOrCreate(
        //         ['email' => $account['email']],
        //         [
        //             'name' => $account['name'],
        //             'password' => Hash::make($account['password']),
        //             'email_verified_at' => now(),
        //         ]
        //     );

        //     $roleIds = collect([
        //         $superAdminRole?->id,
        //         $adminRole?->id,
        //     ])->filter()->values()->all();

        //     if ($roleIds !== []) {
        //         $user->roles()->syncWithoutDetaching($roleIds);
        //     }
        // }
        // Seed some sample folders for local development and testing
        // $this->call(FolderSeeder::class);

        // Seed report-focused sample data for dashboards and analytics pages
        // $this->call(ReportSampleSeeder::class);
    }
}
