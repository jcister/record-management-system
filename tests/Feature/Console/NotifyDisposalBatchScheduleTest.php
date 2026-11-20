<?php

use App\Models\DisposalBatch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DisposalBatchScheduleReminder;
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

it('sends D-day notifications for batches scheduled today', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16 08:00:00');

    DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_at' => '2026-03-16 09:00:00',
    ]);

    artisan('notifications:notify-disposal-batch-schedule')->assertExitCode(0);

    Notification::assertSentTo(
        $this->retentionUser,
        DisposalBatchScheduleReminder::class,
        fn (DisposalBatchScheduleReminder $notification): bool => $notification->daysUntilScheduled === 0
    );

    Carbon::setTestNow();
});

it('sends D-3 notifications for batches scheduled in 3 days', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16 08:00:00');

    DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_at' => '2026-03-19 09:00:00',
    ]);

    artisan('notifications:notify-disposal-batch-schedule')->assertExitCode(0);

    Notification::assertSentTo(
        $this->retentionUser,
        DisposalBatchScheduleReminder::class,
        fn (DisposalBatchScheduleReminder $notification): bool => $notification->daysUntilScheduled === 3
    );

    Carbon::setTestNow();
});

it('does not notify for non-scheduled batches', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16 08:00:00');

    DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_COMPLETED,
        'scheduled_at' => '2026-03-16 09:00:00',
    ]);

    artisan('notifications:notify-disposal-batch-schedule')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('does not notify for batches scheduled in 2 days', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16 08:00:00');

    DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_at' => '2026-03-18 09:00:00',
    ]);

    artisan('notifications:notify-disposal-batch-schedule')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('sends both D-3 and D-day notifications when applicable', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16 08:00:00');

    DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_at' => '2026-03-16 09:00:00',
    ]);

    DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_at' => '2026-03-19 09:00:00',
    ]);

    artisan('notifications:notify-disposal-batch-schedule')->assertExitCode(0);

    Notification::assertSentToTimes($this->retentionUser, DisposalBatchScheduleReminder::class, 2);

    Carbon::setTestNow();
});

it('skips batches that were recently notified to prevent duplicates', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16 08:00:00');

    $batch = DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_at' => '2026-03-16 09:00:00',
    ]);

    // Simulate a notification already sent for this batch within the last 23 hours.
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => DisposalBatchScheduleReminder::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->retentionUser->id,
        'data' => json_encode(['batch_id' => $batch->id, 'days_until_scheduled' => 0]),
        'created_at' => now()->subHours(1),
        'updated_at' => now()->subHours(1),
    ]);

    artisan('notifications:notify-disposal-batch-schedule')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('only notifies users with disposal-management permission', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-16 08:00:00');

    $userWithoutPermission = User::factory()->create(['status' => 'active']);

    DisposalBatch::factory()->create([
        'status' => DisposalBatch::STATUS_SCHEDULED,
        'scheduled_at' => '2026-03-16 09:00:00',
    ]);

    artisan('notifications:notify-disposal-batch-schedule')->assertExitCode(0);

    Notification::assertSentTo($this->retentionUser, DisposalBatchScheduleReminder::class);
    Notification::assertNotSentTo($userWithoutPermission, DisposalBatchScheduleReminder::class);

    Carbon::setTestNow();
});
