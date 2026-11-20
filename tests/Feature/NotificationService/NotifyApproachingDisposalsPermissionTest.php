<?php

use App\Models\File;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RecordDisposalApproaching;
use App\Services\NotificationService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->permission = Permission::where('slug', 'disposal-management')->first();

    $this->role = Role::factory()->create();
    $this->role->permissions()->attach($this->permission);

    $this->retentionUser = User::factory()->create(['status' => 'active']);
    $this->retentionUser->roles()->attach($this->role);
});

it('notifies users with disposal-management permission about approaching disposals', function (): void {
    Notification::fake();

    $file = File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => now()->addDays(10),
    ]);

    app(NotificationService::class)->notifyApproachingDisposals($file);

    Notification::assertSentTo($this->retentionUser, RecordDisposalApproaching::class);
});

it('does not notify users without disposal-management permission', function (): void {
    Notification::fake();

    $userWithoutPermission = User::factory()->create(['status' => 'active']);

    $file = File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => now()->addDays(10),
    ]);

    app(NotificationService::class)->notifyApproachingDisposals($file);

    Notification::assertNotSentTo($userWithoutPermission, RecordDisposalApproaching::class);
});

it('does not notify inactive users', function (): void {
    Notification::fake();

    $this->retentionUser->update(['status' => 'inactive']);

    $file = File::factory()->create([
        'is_record' => true,
        'retention_expires_at' => now()->addDays(10),
    ]);

    app(NotificationService::class)->notifyApproachingDisposals($file);

    Notification::assertNotSentTo($this->retentionUser, RecordDisposalApproaching::class);
});
