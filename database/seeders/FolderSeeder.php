<?php

namespace Database\Seeders;

use App\Models\Folder;
use Illuminate\Database\Seeder;

class FolderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $createdBy = auth()->id(); // or use auth()->id() if running in context

        // Basic root-level folders
        $basicFolders = [
            'COPC',
            'Special Order',
            'CAV',
            'Memoranda',
            'Outgoing Communications',
            'Government Permits',
            'EL/PR',
            'Closed Schools',
        ];

        foreach ($basicFolders as $name) {
            Folder::create([
                'name' => $name,
                'parent_id' => null,
                'is_basic' => true,
                'is_locked' => true,
                'created_by' => $createdBy,
            ]);
        }
    }
}
