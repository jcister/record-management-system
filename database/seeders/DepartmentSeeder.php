<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $departments = [
            ['name' => 'Technical Division'],
            ['name' => 'Administrative Division'],
        ];

        foreach ($departments as $dept) {
            $exists = DB::table('departments')
                ->where('name', $dept['name'])
                ->exists();

            if (! $exists) {
                DB::table('departments')->insert([
                    'name' => $dept['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
