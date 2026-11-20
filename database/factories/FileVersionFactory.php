<?php

namespace Database\Factories;

use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FileVersion>
 */
class FileVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $file = File::factory()->create();

        return [
            'file_id' => $file->id,
            'version_number' => 1,
            'file_name' => $file->file_name,
            'file_path' => 'private/uploads/'.$this->faker->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(10_000, 5_000_000),
            'uploaded_by' => $file->uploaded_by,
            'checksum' => $this->faker->sha256(),
            'version_label' => '1.0',
            'change_summary' => $this->faker->sentence(),
            'ocr_status' => 'completed',
            'ocr_text' => $this->faker->paragraph(),
            'ocr_language' => 'eng',
            'ocr_error' => null,
            'assembly_status' => 'completed',
        ];
    }
}
