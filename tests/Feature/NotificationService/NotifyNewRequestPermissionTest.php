<?php

use App\Models\Permission;
use App\Models\Requests;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewRequestSubmitted;
use App\Services\NotificationService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->permission = Permission::where('slug', 'manage-requests')->first();

    $this->role = Role::factory()->create();
    $this->role->permissions()->attach($this->permission);

    $this->adminUser = User::factory()->create(['status' => 'active']);
    $this->adminUser->roles()->attach($this->role);

    $this->requestor = User::factory()->create(['status' => 'active']);
});

it('notifies users with manage-requests permission when new request is submitted', function (): void {
    Notification::fake();

    $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);

    app(NotificationService::class)->notifyNewRequest($request, $this->requestor);

    Notification::assertSentTo($this->adminUser, NewRequestSubmitted::class);
});

it('does not notify users without manage-requests permission', function (): void {
    Notification::fake();

    $userWithoutPermission = User::factory()->create(['status' => 'active']);
    $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);

    app(NotificationService::class)->notifyNewRequest($request, $this->requestor);

    Notification::assertNotSentTo($userWithoutPermission, NewRequestSubmitted::class);
});

it('does not self-notify the requestor', function (): void {
    Notification::fake();

    // Give requestor the manage-requests permission
    $this->requestor->roles()->attach($this->role);

    $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);

    app(NotificationService::class)->notifyNewRequest($request, $this->requestor);

    Notification::assertNotSentTo($this->requestor, NewRequestSubmitted::class);
});

it('does not notify inactive users', function (): void {
    Notification::fake();

    $this->adminUser->update(['status' => 'inactive']);

    $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);

    app(NotificationService::class)->notifyNewRequest($request, $this->requestor);

    Notification::assertNotSentTo($this->adminUser, NewRequestSubmitted::class);
});

it('does not send notification when requestor is null', function (): void {
    Notification::fake();

    $request = Requests::factory()->create(['requested_by' => $this->requestor->id]);

    app(NotificationService::class)->notifyNewRequest($request, null);

    Notification::assertNothingSent();
});
