<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(['metadata', 'physical_location']),
        ];
    }

    /**
     * Indicate that the tag is a metadata tag.
     */
    public function metadata(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'metadata',
        ]);
    }

    /**
     * Indicate that the tag is a physical location tag.
     */
    public function physicalLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'physical_location',
        ]);
    }
}
