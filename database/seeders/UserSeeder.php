<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Support\PersonNameFormatter;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'slug');

        $departments = Department::query()->pluck('id')->toArray();
        $units = Unit::query()->pluck('id')->toArray();

        if (empty($departments)) {
            $departments[] = Department::create([
                'name' => 'Default Department',
            ])->id;
        }

        if (empty($units)) {
            $units[] = Unit::create([
                'name' => 'Default Unit',
            ])->id;
        }

        $getDepartment = fn () => $departments[array_rand($departments)];
        $getUnit = fn () => $units[array_rand($units)];
        $userNameParts = function (string $fullName): array {
            $parts = PersonNameFormatter::split(null, null, null, null, $fullName);

            return [
                'name' => $fullName,
                'prefix' => null,
                'first_name' => $parts['first'] !== '' ? $parts['first'] : null,
                'middle_name' => $parts['middle'] !== '' ? $parts['middle'] : null,
                'last_name' => $parts['last'] !== '' ? $parts['last'] : null,
                'extension' => $parts['extension'] !== '' ? $parts['extension'] : null,
            ];
        };

        // Super Admin - 1 user
        User::factory()->create([
            ...$userNameParts('Juan Luna'),
            'email' => 'superadmin@example.com',
            'contact_number' => fake()->numerify('09#########'),
            'department_id' => $getDepartment(),
            'unit_id' => $getUnit(),
        ])->roles()->attach($roles->get('super-admin'));

        // // Admin - 2 users
        // User::factory()->create([
        //     'name' => 'Admin One',
        //     'email' => 'admin1@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('admin'));

        // User::factory()->create([
        //     'name' => 'Admin Two',
        //     'email' => 'admin2@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('admin'));

        // // Records Officers - 3 users
        // User::factory()->create([
        //     'name' => 'Records Officer One',
        //     'email' => 'records1@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('records-officer'));

        // User::factory()->create([
        //     'name' => 'Records Officer Two',
        //     'email' => 'records2@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('records-officer'));

        // User::factory()->create([
        //     'name' => 'Records Officer Three',
        //     'email' => 'records3@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('records-officer'));

        // // Regional Directors - 3 users
        // User::factory()->create([
        //     'name' => 'Regional Director North',
        //     'email' => 'rd.north@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('regional-director'));

        // User::factory()->create([
        //     'name' => 'Regional Director Central',
        //     'email' => 'rd.central@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('regional-director'));

        // User::factory()->create([
        //     'name' => 'Regional Director South',
        //     'email' => 'rd.south@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('regional-director'));

        // // Reviewers - 3 users
        // User::factory()->create([
        //     'name' => 'Reviewer One',
        //     'email' => 'reviewer1@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('reviewer'));

        // User::factory()->create([
        //     'name' => 'Reviewer Two',
        //     'email' => 'reviewer2@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('reviewer'));

        // User::factory()->create([
        //     'name' => 'Reviewer Three',
        //     'email' => 'reviewer3@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('reviewer'));

        // // Contributors - 4 users
        // User::factory()->create([
        //     'name' => 'Contributor One',
        //     'email' => 'contributor1@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('contributor'));

        // User::factory()->create([
        //     'name' => 'Contributor Two',
        //     'email' => 'contributor2@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('contributor'));

        // User::factory()->create([
        //     'name' => 'Contributor Three',
        //     'email' => 'contributor3@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('contributor'));

        // User::factory()->create([
        //     'name' => 'Contributor Four',
        //     'email' => 'contributor4@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('contributor'));

        // // Viewers - 4 users
        // User::factory()->create([
        //     'name' => 'Viewer One',
        //     'email' => 'viewer1@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('viewer'));

        User::factory()->create([
            'name' => 'Viewer Two',
            'email' => 'viewer2@example.com',
            'contact_number' => fake()->numerify('09#########'),
            'department_id' => $getDepartment(),
            'unit_id' => $getUnit(),
        ])->roles()->attach($roles->get('viewer'));

        // User::factory()->create([
        //     'name' => 'Viewer Three',
        //     'email' => 'viewer3@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('viewer'));

        // User::factory()->create([
        //     'name' => 'Viewer Four',
        //     'email' => 'viewer4@example.com',
        //     'contact_number' => fake()->numerify('09#########'),
        //     'department_id' => $getDepartment(),
        //     'unit_id' => $getUnit(),
        // ])->roles()->attach($roles->get('viewer'));
    }
}
