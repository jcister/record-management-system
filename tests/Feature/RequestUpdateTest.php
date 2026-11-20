<?php

use App\Models\Department;
use App\Models\Permission;
use App\Models\Requests;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RequestWillReturnCopyChanged;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->department = Department::factory()->create();
    $this->requester = User::factory()->create();

    // Create admin user with manage-requests permission
    $this->adminUser = User::factory()->create();
    $permission = Permission::firstOrCreate(
        ['slug' => 'manage-requests'],
        ['name' => 'Manage Requests']
    );
    $role = Role::factory()->create();
    $role->permissions()->attach($permission);
    $this->adminUser->roles()->attach($role);
});

it('allows requester to toggle requires_scan before scans are uploaded', function () {
    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => false,
        'status' => 'Pending',
    ]);

    actingAs($this->requester);

    $response = $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => true,
    ]);

    $response->assertSuccessful();
    assertDatabaseHas('requests', [
        'id' => $request->id,
        'requires_scan' => true,
    ]);
});

it('allows admin to toggle requires_scan on any request', function () {
    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => false,
        'status' => 'Pending',
    ]);

    actingAs($this->adminUser);

    $response = $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => true,
    ]);

    $response->assertSuccessful();
    assertDatabaseHas('requests', [
        'id' => $request->id,
        'requires_scan' => true,
    ]);
});

it('prevents toggling requires_scan when request has uploaded scans', function () {
    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => true,
        'status' => 'Awaiting Update',
    ]);

    // Attach a scan file
    $scanFile = \App\Models\File::factory()->create([
        'record_id' => 'SCAN-TEST-001',
        'status' => 'draft',
    ]);
    $request->files()->attach($scanFile->id);

    actingAs($this->requester);

    $response = $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => false,
    ]);

    $response->assertForbidden();
});

it('prevents toggling requires_scan when request is Complete', function () {
    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => true,
        'status' => 'Complete',
    ]);

    actingAs($this->requester);

    $response = $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => false,
    ]);

    $response->assertForbidden();
});

it('prevents toggling requires_scan when request is Rejected', function () {
    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => true,
        'status' => 'Rejected',
        'notes' => 'Test rejection',
    ]);

    actingAs($this->requester);

    $response = $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => false,
    ]);

    $response->assertForbidden();
});

it('prevents non-owner without manage-requests permission from toggling requires_scan', function () {
    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => false,
        'status' => 'Pending',
    ]);

    $otherUser = User::factory()->create();
    actingAs($otherUser);

    $response = $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => true,
    ]);

    $response->assertForbidden();
});

it('sends notification to archives team when requires_scan is changed', function () {
    Notification::fake();

    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => false,
        'status' => 'Pending',
    ]);

    actingAs($this->requester);

    $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => true,
    ]);

    Notification::assertSentTo($this->adminUser, RequestWillReturnCopyChanged::class);

    $notification = Notification::sent($this->adminUser, RequestWillReturnCopyChanged::class)->first();
    $data = $notification?->toArray($this->adminUser);

    expect($data)->not->toBeNull();
    expect($data)
        ->toMatchArray([
            'request_id' => $request->id,
            'old_value' => 'No',
            'new_value' => 'Yes',
            'link' => '/requests?highlight='.rawurlencode((string) $request->request_id),
        ]);
});

it('sends notification to request owner when records officer changes requires_scan', function () {
    Notification::fake();

    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => false,
        'status' => 'Pending',
    ]);

    actingAs($this->adminUser);

    $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => true,
    ]);

    Notification::assertSentTo($this->requester, RequestWillReturnCopyChanged::class);

    $notification = Notification::sent($this->requester, RequestWillReturnCopyChanged::class)->first();
    $data = $notification?->toArray($this->requester);

    expect($data)->not->toBeNull();
    expect($data)
        ->toMatchArray([
            'request_id' => $request->id,
            'old_value' => 'No',
            'new_value' => 'Yes',
            'link' => '/requests/myrequests?highlight='.rawurlencode((string) $request->request_id),
        ]);
});

it('does not send notification when requires_scan value does not change', function () {
    Notification::fake();

    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => false,
        'status' => 'Pending',
    ]);

    actingAs($this->requester);

    $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => false,
    ]);

    Notification::assertNotSentTo($this->adminUser, RequestWillReturnCopyChanged::class);
});

it('validates requires_scan field is boolean', function () {
    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => false,
        'status' => 'Pending',
    ]);

    actingAs($this->requester);

    $response = $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => 'invalid',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('requires_scan');
});

it('logs the requires_scan change in audit trail', function () {
    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => false,
        'status' => 'Pending',
    ]);

    actingAs($this->requester);

    $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => true,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => 'App\Models\Requests',
        'auditable_id' => $request->id,
        'action' => 'request_updated',
    ]);
});

it('returns updated request data when value changes', function () {
    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => false,
        'status' => 'Pending',
    ]);

    actingAs($this->requester);

    $response = $this->patchJson("/api/requests/{$request->id}", [
        'requires_scan' => true,
    ]);

    $response->assertSuccessful();
    $response->assertJsonFragment(['requires_scan' => true]);

    // Verify database was actually updated
    assertDatabaseHas('requests', [
        'id' => $request->id,
        'requires_scan' => true,
    ]);
});

it('handles concurrent update attempts gracefully', function () {
    $request = Requests::factory()->create([
        'requested_by' => $this->requester->id,
        'department_id' => $this->department->id,
        'requires_scan' => false,
        'status' => 'Pending',
    ]);

    actingAs($this->requester);

    // Simulate rapid consecutive requests (race condition)
    $response1 = $this->patchJson("/api/requests/{$request->id}", ['requires_scan' => true]);
    $response2 = $this->patchJson("/api/requests/{$request->id}", ['requires_scan' => true]);

    // Both should succeed (second one is a no-op)
    $response1->assertSuccessful();
    $response2->assertSuccessful();

    // Final state should be true
    assertDatabaseHas('requests', [
        'id' => $request->id,
        'requires_scan' => true,
    ]);
});
