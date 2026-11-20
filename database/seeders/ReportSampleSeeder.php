<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\File;
use App\Models\Folder;
use App\Models\Institution;
use App\Models\Requests;
use App\Models\Tag;
use App\Models\User;
use App\Support\RetentionSchedule;
use App\Support\RetentionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class ReportSampleSeeder extends Seeder
{
    private const REQUESTS_TOTAL = 70;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = $this->ensureDepartments();
        $folders = $this->ensureFolders();
        $users = $this->ensureUsers();
        $institution = Institution::query()->first() ?? Institution::factory()->create();

        $metadataTags = Tag::query()->where('type', 'metadata')->take(10)->get();
        if ($metadataTags->count() < 10) {
            Tag::factory()->metadata()->count(10 - $metadataTags->count())->create();
            $metadataTags = Tag::query()->where('type', 'metadata')->take(10)->get();
        }

        $physicalLocationTags = Tag::query()->where('type', 'physical_location')->take(8)->get();
        if ($physicalLocationTags->count() < 8) {
            Tag::factory()->physicalLocation()->count(8 - $physicalLocationTags->count())->create();
            $physicalLocationTags = Tag::query()->where('type', 'physical_location')->take(8)->get();
        }

        $statusDistribution = [
            RetentionStatus::ACTIVE => 60,
            RetentionStatus::READY_FOR_DISPOSAL => 25,
            RetentionStatus::DISPOSED => 55,
        ];

        foreach ($statusDistribution as $retentionStatus => $count) {
            File::factory()
                ->count($count)
                ->state(fn (): array => [
                    'uploaded_by' => $users->random()->id,
                    'department_id' => $departments->random()->id,
                    'folder_id' => $folders->random()->id,
                    'institution_id' => $institution->id,
                    'is_record' => fake()->boolean(70),
                    'visibility' => fake()->randomElement(['public', 'restricted', 'private']),
                    'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
                    'retention_status' => $retentionStatus,
                    'download_count' => fake()->numberBetween(0, 300),
                    'created_at' => fake()->dateTimeBetween('-12 months', 'now'),
                    'updated_at' => fake()->dateTimeBetween('-6 months', 'now'),
                ])
                ->create()
                ->each(function (File $file) use ($metadataTags, $physicalLocationTags): void {
                    $this->applyReportDateWindows($file);

                    $tagIds = collect()
                        ->merge($metadataTags->random(fake()->numberBetween(1, 3))->pluck('id'))
                        ->merge($physicalLocationTags->random(fake()->numberBetween(1, 2))->pluck('id'))
                        ->unique()
                        ->values()
                        ->all();

                    $file->tags()->sync($tagIds);
                });
        }

        $requestStatuses = [
            'pending',
            'approved',
            'approved',
            'rejected',
            'in_review',
            'completed',
        ];

        Requests::factory()
            ->count(self::REQUESTS_TOTAL)
            ->state(fn (): array => [
                'department_id' => $departments->random()->id,
                'requested_by' => $users->random()->id,
                'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
            ])
            ->create()
            ->each(function (Requests $request) use ($requestStatuses, $metadataTags): void {
                $request->forceFill([
                    'status' => fake()->randomElement($requestStatuses),
                    'updated_at' => fake()->dateTimeBetween($request->created_at, 'now'),
                ])->save();

                $request->metadataTags()->sync(
                    $metadataTags->random(fake()->numberBetween(1, 2))->pluck('id')->all()
                );
            });
    }

    /**
     * @return Collection<int, Department>
     */
    private function ensureDepartments(): Collection
    {
        $departments = Department::query()->get();

        if ($departments->isNotEmpty()) {
            return $departments;
        }

        return Department::factory()->count(3)->create();
    }

    /**
     * @return Collection<int, Folder>
     */
    private function ensureFolders(): Collection
    {
        $folders = Folder::query()->get();

        if ($folders->isNotEmpty()) {
            return $folders;
        }

        return Folder::factory()->count(6)->create();
    }

    /**
     * @return Collection<int, User>
     */
    private function ensureUsers(): Collection
    {
        $users = User::query()->get();

        if ($users->isNotEmpty()) {
            return $users;
        }

        return User::factory()->count(5)->create();
    }

    private function applyReportDateWindows(File $file): void
    {
        $today = CarbonImmutable::today();
        $retentionDuration = fake()->randomElement([
            RetentionSchedule::DURATION_ONE_YEAR,
            RetentionSchedule::DURATION_TEN_YEARS,
            RetentionSchedule::DURATION_PERPETUAL,
        ]);

        $releaseDate = CarbonImmutable::instance(fake()->dateTimeBetween('-5 years', '-2 months'))->toDateString();
        $retentionExpiresAt = RetentionSchedule::expiresAt($releaseDate, $retentionDuration);

        if ($file->retention_status === RetentionStatus::DISPOSED) {
            $retentionExpiresAt = $today->subDays(fake()->numberBetween(20, 240))->toDateString();
            $file->updated_at = $today->subDays(fake()->numberBetween(1, 120));
        }

        if ($file->retention_status === RetentionStatus::READY_FOR_DISPOSAL) {
            $retentionExpiresAt = $today->subDays(fake()->numberBetween(5, 180))->toDateString();
        }

        if ($file->retention_status === RetentionStatus::ACTIVE && fake()->boolean(45)) {
            $retentionExpiresAt = $today->addDays(fake()->numberBetween(5, 85))->toDateString();
        }

        $expirationDate = fake()->boolean(40)
            ? CarbonImmutable::parse($retentionExpiresAt ?? $today->addDays(365)->toDateString())->addDays(fake()->numberBetween(-7, 30))
            : null;

        $file->forceFill([
            'date_released' => $releaseDate,
            'retention_duration' => $retentionDuration,
            'retention_expires_at' => $retentionExpiresAt,
            'expiration_date' => $expirationDate,
        ])->save();
    }
}
