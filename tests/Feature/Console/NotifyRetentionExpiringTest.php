<?php

use App\Models\File;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RetentionDurationReached;
use App\Support\RetentionStatus;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $permission = Permission::where('slug', 'disposal-management')->first();

    $role = Role::factory()->create();
    $role->permissions()->attach($permission);

    $this->retentionUser = User::factory()->create(['status' => 'active']);
    $this->retentionUser->roles()->attach($role);
});

it('sends notifications for records expiring today', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16');

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-16',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-retention-expiring')->assertExitCode(0);

    Notification::assertSentTo($this->retentionUser, RetentionDurationReached::class);

    Carbon::setTestNow();
});

it('does not notify for records expiring tomorrow', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16');

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-17',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-retention-expiring')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('does not notify for records that expired yesterday', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16');

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-15',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-retention-expiring')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('does not notify for non-record files', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16');

    File::factory()->create([
        'is_record' => false,
        'retention_expires_at' => '2026-03-16',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-retention-expiring')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('does not notify for records not in active status', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16');

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-16',
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    artisan('notifications:notify-retention-expiring')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('skips records that were recently notified to prevent duplicates', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16 08:00:00');

    $file = File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-16',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    // Simulate a notification already sent for this file within the last 23 hours.
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => RetentionDurationReached::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->retentionUser->id,
        'data' => json_encode(['file_id' => $file->id]),
        'created_at' => now()->subHours(1),
        'updated_at' => now()->subHours(1),
    ]);

    artisan('notifications:notify-retention-expiring')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('only notifies users with disposal-management permission', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16');

    $userWithoutPermission = User::factory()->create(['status' => 'active']);

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-16',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-retention-expiring')->assertExitCode(0);

    Notification::assertSentTo($this->retentionUser, RetentionDurationReached::class);
    Notification::assertNotSentTo($userWithoutPermission, RetentionDurationReached::class);

    Carbon::setTestNow();
});
