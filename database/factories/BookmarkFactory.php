<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Bookmark>
 */
class BookmarkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bookmarkableClass = fake()->randomElement([
            Folder::class,
            File::class,
        ]);

        return [
            'user_id' => User::factory(),
            'bookmarkable_type' => $bookmarkableClass,
            'bookmarkable_id' => $bookmarkableClass === Folder::class
                ? Folder::factory()
                : File::factory(),
        ];
    }
}
