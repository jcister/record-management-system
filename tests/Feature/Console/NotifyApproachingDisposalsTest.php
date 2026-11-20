<?php

use App\Models\File;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RecordDisposalApproaching;
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

    $this->role = Role::factory()->create();
    $this->role->permissions()->attach($permission);

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->roles()->attach($this->role);
});

it('sends notifications for records expiring within 30 days', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-01');

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-20',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-approaching-disposals')->assertExitCode(0);

    Notification::assertSentTo($this->admin, RecordDisposalApproaching::class);

    Carbon::setTestNow();
});

it('sends a critical urgency notification for records expiring within 7 days', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-01');

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-05',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-approaching-disposals')->assertExitCode(0);

    Notification::assertSentTo($this->admin, RecordDisposalApproaching::class, function (RecordDisposalApproaching $notification): bool {
        return $notification->urgency === 'critical';
    });

    Carbon::setTestNow();
});

it('sends a warning urgency notification for records expiring between 8 and 30 days', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-01');

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-20',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-approaching-disposals')->assertExitCode(0);

    Notification::assertSentTo($this->admin, RecordDisposalApproaching::class, function (RecordDisposalApproaching $notification): bool {
        return $notification->urgency === 'warning';
    });

    Carbon::setTestNow();
});

it('sends a notification for overdue records', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-01');

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-02-01',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-approaching-disposals')->assertExitCode(0);

    Notification::assertSentTo($this->admin, RecordDisposalApproaching::class, function (RecordDisposalApproaching $notification): bool {
        return $notification->daysUntilExpiry < 0 && $notification->urgency === 'critical';
    });

    Carbon::setTestNow();
});

it('does not notify for records not yet approaching expiry', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-01');

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-06-01',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-approaching-disposals')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('does not notify for non-record files', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-01');

    File::factory()->create([
        'is_record' => false,
        'retention_expires_at' => '2026-03-10',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-approaching-disposals')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('does not notify for records already in ready_for_disposal status', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-01');

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-10',
        'retention_status' => RetentionStatus::READY_FOR_DISPOSAL,
    ]);

    artisan('notifications:notify-approaching-disposals')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('skips records that were recently notified to prevent duplicates', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-01 08:00:00');

    $file = File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-10',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    // Simulate a notification already sent for this file within the last 23 hours.
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => RecordDisposalApproaching::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $this->admin->id,
        'data' => json_encode(['file_id' => $file->id]),
        'created_at' => now()->subHours(1),
        'updated_at' => now()->subHours(1),
    ]);

    artisan('notifications:notify-approaching-disposals')->assertExitCode(0);

    Notification::assertNothingSent();

    Carbon::setTestNow();
});

it('re-notifies after the dedup window expires', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-01 08:00:00');

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-10',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-approaching-disposals')->assertExitCode(0);
    Notification::assertSentTo($this->admin, RecordDisposalApproaching::class);

    Notification::fake();

    // Advance time beyond the 23-hour dedup window.
    Carbon::setTestNow('2026-03-02 08:01:00');

    artisan('notifications:notify-approaching-disposals')->assertExitCode(0);
    Notification::assertSentTo($this->admin, RecordDisposalApproaching::class);

    Carbon::setTestNow();
});

it('notifies all users with disposal-management permission', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-03-01');

    $anotherRetentionUser = User::factory()->create(['status' => 'active']);
    $anotherRetentionUser->roles()->attach($this->role);

    $userWithoutPermission = User::factory()->create(['status' => 'active']);

    File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => '2026-03-10',
        'retention_status' => RetentionStatus::ACTIVE,
    ]);

    artisan('notifications:notify-approaching-disposals')->assertExitCode(0);

    Notification::assertSentTo($this->admin, RecordDisposalApproaching::class);
    Notification::assertSentTo($anotherRetentionUser, RecordDisposalApproaching::class);
    Notification::assertNotSentTo($userWithoutPermission, RecordDisposalApproaching::class);

    Carbon::setTestNow();
});
