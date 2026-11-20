<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Folder;
use App\Models\Institution;
use App\Models\User;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = fake()->dateTimeBetween('-5 years', 'now');
        $releasedAt = fake()->dateTimeBetween($createdAt, 'now');
        $retentionDuration = fake()->randomElement([
            RetentionSchedule::DURATION_ONE_YEAR,
            RetentionSchedule::DURATION_TEN_YEARS,
            RetentionSchedule::DURATION_PERPETUAL,
        ]);

        return [
            'author' => fake()->name(),
            'department_id' => Department::factory(),
            'record_id' => fake()->unique()->bothify('REC-#####'),
            'physical_location' => fake()->streetAddress(),
            'date_created' => $createdAt->format('Y-m-d'),
            'date_released' => $releasedAt->format('Y-m-d'),
            'retention_duration' => $retentionDuration,
            'retention_expires_at' => RetentionSchedule::expiresAt(
                $createdAt->format('Y-m-d'),
                $retentionDuration
            ),
            'uploaded_by' => User::factory(),
            'folder_id' => Folder::factory(),
            'institution_id' => Institution::factory(),
            'document_number' => fake()->unique()->bothify('DOC-####'),
            'series' => fake()->randomElement(['A', 'B', 'C']),
            'remarks' => fake()->sentence(),
            'file_name' => fake()->lexify('document-????'),
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1_024, 25_000_000),
            'locked_at' => null,
            'visibility' => fake()->randomElement(['public', 'restricted', 'private']),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'retention_status' => fake()->randomElement(RetentionStatus::options()),
            'approved_by' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'download_count' => fake()->numberBetween(0, 50),
            'expiration_date' => null,
            'ocr_text' => fake()->sentence(8),
            'ocr_status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed']),
            'ocr_language' => 'eng+fil',
            'ocr_error' => null,
        ];
    }
}
