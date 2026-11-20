<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $positions = [
            'Regional Director',
            'Chief Education Program Specialist',
            'Supervising Education Program Specialist',
            'Education Supervisor II',
            'Education Program Specialist',
            'Chief Administrative Officer',
            'Supervising Administrative Officer',
            'Administrative Officer III',
            'Administrative Officer II',
            'Administrative Officer I',
            'Administrative Aide I',
            'Administrative Aide II',
            'Administrative Aide III',
            'Administrative Aide IV',
            'Administrative Aide V',
            'Administrative Aide VI',
            'Accounting III',
            'Accounting II',
            'Accounting I',
            'Project Technical Support III',
            'Project Technical Support II',
            'Project Technical Support I',
            'Project Support Staff V',
        ];

        foreach ($positions as $name) {
            $exists = DB::table('positions')->where('name', $name)->exists();

            if (! $exists) {
                DB::table('positions')->insert([
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
