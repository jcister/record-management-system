<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'UNIFAST'],
            ['name' => 'SIKAP/LGSO'],
            ['name' => 'STUFAPS'],
            ['name' => 'ACEF'],
            ['name' => 'COSCHO'],
            ['name' => 'Chief Education Program Specialist'],
            ['name' => 'Supervising Education Program Specialist'],
            ['name' => 'Education Supervisor II'],
            ['name' => 'Education Program Specialist'],
            ['name' => 'Technical Secretary'],
            ['name' => 'HEMIS/STAT'],
            ['name' => 'Sports'],

            ['name' => 'Chief Administrative Officer'],
            ['name' => 'Supervising Administrative Officer'],
            ['name' => 'Records'],
            ['name' => 'Accounting'],
            ['name' => 'Budget'],
            ['name' => 'Cash Unit'],
            ['name' => 'COA'],
            ['name' => 'General Inquiries'],
            ['name' => 'Procurement'],
            ['name' => 'HR'],
        ];

        $now = now();

        foreach ($units as $unit) {
            $exists = DB::table('units')
                ->where('name', $unit['name'])
                ->exists();

            if (! $exists) {
                DB::table('units')->insert([
                    'name' => $unit['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
