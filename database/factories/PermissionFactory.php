<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = ucwords(fake()->unique()->words(2, true));

        return [
            'name' => $label,
            'slug' => Str::slug($label.' '.fake()->unique()->randomDigitNotNull()),
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }
}
