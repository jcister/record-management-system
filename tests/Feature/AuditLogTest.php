<?php

use App\Models\AuditLog;
use App\Models\DisposalAudit;
use App\Models\DisposalBatch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function grantPermission(User $user, string $slug, string $name = ''): void
{
    $permission = Permission::firstOrCreate(
        ['slug' => $slug],
        ['name' => $name ?: Str::headline($slug), 'is_system' => true]
    );

    $role = Role::factory()->create();
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user->roles()->attach($role);
}

// ---------------------------------------------------------------------------
// API access
// ---------------------------------------------------------------------------

it('blocks audit log access without manage-log permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->getJson('/api/audit-logs')->assertForbidden();
});

it('returns audit logs for authorized users via API', function () {
    $user = User::factory()->create();
    grantPermission($user, 'user-management', 'User Management');

    AuditLog::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'action' => 'login',
        'description' => 'User signed in',
        'created_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->getJson('/api/audit-logs');

    $response->assertOk();
    $response->assertJsonFragment(['action' => 'login']);
});

// ---------------------------------------------------------------------------
// Web page access & role scoping
// ---------------------------------------------------------------------------

it('denies audit trail page access with no relevant permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/audit-logs')->assertForbidden();
});

it('grants audit trail page access to organize-content users', function () {
    $user = User::factory()->create();
    grantPermission($user, 'organize-content');

    $this->actingAs($user);

    $this->get('/audit-logs')->assertOk();
});

it('scopes audit trail to file and folder actions for organize-content users', function () {
    $user = User::factory()->create();
    grantPermission($user, 'organize-content');

    $otherUser = User::factory()->create();

    // Allowed: own file action
    AuditLog::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'action' => 'file_created',
        'description' => 'My file',
        'created_at' => now(),
    ]);

    // Allowed: file action by another user (within domain)
    AuditLog::create([
        'user_id' => $otherUser->id,
        'user_name' => $otherUser->name,
        'action' => 'folder_created',
        'description' => 'Their folder',
        'created_at' => now(),
    ]);

    // Not allowed: user management action by another user
    AuditLog::create([
        'user_id' => $otherUser->id,
        'user_name' => $otherUser->name,
        'action' => 'user_created',
        'description' => 'Admin created a user',
        'created_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/audit-logs');
    $response->assertOk();

    $logs = $response->original->getData()['page']['props']['logs']['data'];
    $actions = collect($logs)->pluck('action')->all();

    expect($actions)->toContain('file_created')
        ->toContain('folder_created')
        ->not->toContain('user_created');
});

it('always shows own activity regardless of role scope', function () {
    $user = User::factory()->create();
    grantPermission($user, 'organize-content');

    // Own login (not a content action but own activity)
    AuditLog::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'action' => 'login',
        'description' => 'Signed in',
        'created_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/audit-logs');
    $response->assertOk();

    $logs = $response->original->getData()['page']['props']['logs']['data'];
    $actions = collect($logs)->pluck('action')->all();

    expect($actions)->toContain('login');
});

it('restricts disposal rows to disposal-management users only', function () {
    $basicUser = User::factory()->create();
    grantPermission($basicUser, 'organize-content');

    $disposalUser = User::factory()->create();
    grantPermission($disposalUser, 'disposal-management');

    $batch = DisposalBatch::factory()->create();

    DisposalAudit::create([
        'batch_id' => $batch->id,
        'user_id' => $disposalUser->id,
        'action' => 'scheduled',
        'description' => 'Batch scheduled',
    ]);

    // organize-content user should NOT see disposal rows from other users
    $this->actingAs($basicUser);
    $response = $this->get('/audit-logs');
    $response->assertOk();

    $logs = $response->original->getData()['page']['props']['logs']['data'];
    $sources = collect($logs)->pluck('source')->all();

    expect($sources)->not->toContain('disposal');

    // disposal-management user should see disposal rows
    $this->actingAs($disposalUser);
    $response2 = $this->get('/audit-logs');
    $response2->assertOk();

    $logs2 = $response2->original->getData()['page']['props']['logs']['data'];
    $sources2 = collect($logs2)->pluck('source')->all();

    expect($sources2)->toContain('disposal');
});

// ---------------------------------------------------------------------------
// My Activity filter
// ---------------------------------------------------------------------------

it('my_activity filter shows only current user entries', function () {
    $user = User::factory()->create();
    grantPermission($user, 'organize-content');

    $otherUser = User::factory()->create();

    AuditLog::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'action' => 'file_created',
        'description' => 'My file',
        'created_at' => now(),
    ]);

    AuditLog::create([
        'user_id' => $otherUser->id,
        'user_name' => $otherUser->name,
        'action' => 'file_updated',
        'description' => 'Their file',
        'created_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/audit-logs?my_activity=1');
    $response->assertOk();

    $logs = $response->original->getData()['page']['props']['logs']['data'];
    $userIds = collect($logs)->pluck('user_id')->all();

    expect($userIds)->each->toBe($user->id);
});

it('my_activity bypasses role scope to show all own actions', function () {
    $user = User::factory()->create();
    grantPermission($user, 'manage-requests');

    // Own login — not in manage-requests domain, normally hidden by role scope
    AuditLog::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'action' => 'user_updated',
        'description' => 'Profile updated',
        'created_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/audit-logs?my_activity=1');
    $response->assertOk();

    $logs = $response->original->getData()['page']['props']['logs']['data'];
    $actions = collect($logs)->pluck('action')->all();

    expect($actions)->toContain('user_updated');
});

// ---------------------------------------------------------------------------
// CSV export
// ---------------------------------------------------------------------------

it('exports filtered audit logs as csv', function () {
    $user = User::factory()->create();
    grantPermission($user, 'disposal-management');

    AuditLog::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'action' => 'login',
        'description' => 'User signed in',
        'created_at' => now(),
    ]);

    AuditLog::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'action' => 'file_created',
        'description' => 'File created',
        'created_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/audit-logs/export?action=login');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('login');
    expect($csv)->not->toContain('file_created');
    expect(Str::contains($csv, 'Timestamp'))->toBeTrue();
});

it('export includes disposal audit rows matching the UI', function () {
    $user = User::factory()->create();
    grantPermission($user, 'disposal-management');

    $batch = DisposalBatch::factory()->create([
        'reference' => 'TEST-BATCH-001',
    ]);

    DisposalAudit::create([
        'batch_id' => $batch->id,
        'user_id' => $user->id,
        'action' => 'scheduled',
        'description' => 'Batch was scheduled',
    ]);

    $this->actingAs($user);

    $response = $this->get('/audit-logs/export');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('disposal_scheduled');
    expect($csv)->toContain('TEST-BATCH-001');
});

it('export disposal rows respect action filter', function () {
    $user = User::factory()->create();
    grantPermission($user, 'disposal-management');

    $batch = DisposalBatch::factory()->create();

    DisposalAudit::create([
        'batch_id' => $batch->id,
        'user_id' => $user->id,
        'action' => 'scheduled',
        'description' => 'Scheduled',
    ]);

    DisposalAudit::create([
        'batch_id' => $batch->id,
        'user_id' => $user->id,
        'action' => 'completed',
        'description' => 'Completed',
    ]);

    $this->actingAs($user);

    $response = $this->get('/audit-logs/export?action=disposal_scheduled');

    $csv = $response->streamedContent();

    expect($csv)->toContain('disposal_scheduled');
    expect($csv)->not->toContain('disposal_completed');
});

// ---------------------------------------------------------------------------
// Action groups
// ---------------------------------------------------------------------------

it('returns action groups instead of flat action list', function () {
    $user = User::factory()->create();
    grantPermission($user, 'user-management');

    AuditLog::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'action' => 'file_created',
        'description' => 'File',
        'created_at' => now(),
    ]);

    AuditLog::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'action' => 'login',
        'description' => 'Signed in',
        'created_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/audit-logs');
    $response->assertOk();

    $actionGroups = $response->original->getData()['page']['props']['actionGroups'];

    expect($actionGroups)->toBeArray();

    $labels = collect($actionGroups)->pluck('label')->all();
    expect($labels)->toContain('Content')->toContain('Authentication');

    // Each group has a label and an actions array
    foreach ($actionGroups as $group) {
        expect($group)->toHaveKeys(['label', 'actions']);
        expect($group['actions'])->toBeArray()->not->toBeEmpty();
    }
});
