<?php

namespace Database\Factories;

use App\Support\PersonNameFormatter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefix = fake()->optional(0.2)->randomElement(['Mr.', 'Ms.', 'Mrs.', 'Dr.', 'Prof.', 'Engr.', 'Atty.']);
        $firstName = fake()->firstName();
        $middleName = fake()->optional(0.5)->firstName();
        $lastName = fake()->lastName();
        $extension = fake()->optional(0.15)->randomElement(['Jr.', 'Sr.', 'III']);
        $baseName = PersonNameFormatter::compose($firstName, $middleName, $lastName, $extension);

        return [
            'name' => trim(implode(' ', array_filter([$prefix, $baseName]))),
            'prefix' => $prefix,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'extension' => $extension,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('P@ssw0rd123'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
