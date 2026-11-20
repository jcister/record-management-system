<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Institution>
 */
class InstitutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uii' => fake()->unique()->bothify('UII-####'),
            'name' => fake()->company(),
            'type' => fake()->randomElement(['School', 'Agency', 'Company']),
            'region' => fake()->state(),
            'contact_info' => [
                'email' => fake()->companyEmail(),
                'phone' => fake()->phoneNumber(),
            ],
        ];
    }
}
