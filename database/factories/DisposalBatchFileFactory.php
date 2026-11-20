<?php

namespace Database\Factories;

use App\Models\DisposalBatch;
use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DisposalBatchFile>
 */
class DisposalBatchFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => DisposalBatch::factory(),
            'file_id' => File::factory(),
            'status' => DisposalBatch::STATUS_SCHEDULED,
            'scheduled_by' => User::factory(),
            'scheduled_at' => now(),
            'physical_snapshot' => [
                'location' => null,
                'tags' => [],
            ],
        ];
    }
}
