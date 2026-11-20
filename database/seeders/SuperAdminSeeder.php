<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create or update the super-admin account for staging/Docker deployments.
     * Credentials are driven by SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD env vars.
     * Safe to run multiple times — fully idempotent.
     */
    public function run(): void
    {
        // Ensure permissions and role→permission mappings exist before assigning the user.
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $role = Role::query()->where('slug', 'super-admin')->first();

        $email = env('SUPERADMIN_EMAIL', 'superadmin@example.com');
        $password = env('SUPERADMIN_PASSWORD', 'P@ssw0rd123');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->command->info("Superadmin ready → {$email} / {$password}");
    }
}
