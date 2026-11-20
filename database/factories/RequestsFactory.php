<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Requests;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requests>
 */
class RequestsFactory extends Factory
{
    protected $model = Requests::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'request_id' => fake()->uuid(),
            'requested_by' => User::factory(),
            'document_type' => fake()->randomElement(['Correspondence', 'Financial Records', 'Personnel', 'Legal', 'Administrative']),
            'file_number' => fake()->bothify('FN-####'),
            'start_date' => fake()->optional()->date(),
            'end_date' => fake()->optional()->date(),
            'request_title' => fake()->sentence(4),
            'purpose' => fake()->sentence(6),
            'priority' => fake()->randomElement(['standard', 'urgent']),
            'status' => 'Pending',
            'notes' => fake()->optional()->sentence(),
            'required_format' => fake()->randomElement(['Retrieve Original Copy', 'Reproduce Hardcopy', 'Electronic Copy Only']),
            'requires_scan' => fake()->boolean(30),
            'scan_review_status' => null,
            'scan_review_notes' => null,
        ];
    }

    /**
     * Set the status to Processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'In Review',
        ]);
    }

    /**
     * Set the status to Completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'Complete',
        ]);
    }

    /**
     * Set the status to Rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'Rejected',
        ]);
    }
}
