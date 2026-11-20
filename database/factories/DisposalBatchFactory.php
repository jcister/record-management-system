<?php

namespace Database\Factories;

use App\Models\DisposalBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DisposalBatch>
 */
class DisposalBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = DisposalBatch::query()->max('sequence') + 1;

        return [
            'sequence' => $sequence,
            'reference' => sprintf('DB-%s-%04d', now()->format('Ymd'), $sequence),
            'status' => DisposalBatch::STATUS_SCHEDULED,
            'file_count' => 0,
            'scheduled_by' => User::factory(),
            'scheduled_at' => now(),
            'scheduled_note' => $this->faker->optional()->sentence(),
            'mode_of_disposal' => DisposalBatch::MODE_SALE,
            'metadata' => [
                'source' => 'test',
            ],
        ];
    }
}
