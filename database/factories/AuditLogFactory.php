<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'user_name' => fake()->name(),
            'action' => fake()->randomElement([
                'file_viewed',
                'file_downloaded',
                'file_uploaded',
                'file_updated',
                'file_deleted',
                'folder_created',
                'folder_updated',
                'folder_deleted',
                'request_created',
                'request_updated',
            ]),
            'description' => fake()->sentence(),
            'auditable_type' => null,
            'auditable_id' => null,
            'auditable_name' => fake()->words(3, true),
            'properties' => [],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
