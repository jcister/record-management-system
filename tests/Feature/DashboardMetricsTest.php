<?php

use App\Models\AuditLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\Permission;
use App\Models\Requests;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $organizeContentPermission = Permission::firstOrCreate(
        ['slug' => 'organize-content'],
        ['name' => 'Organize Content']
    );

    $superAdminRole = Role::factory()->create([
        'name' => 'Super Admin',
        'slug' => 'super-admin',
    ]);
    $superAdminRole->permissions()->syncWithoutDetaching([$organizeContentPermission->id]);

    $adminRole = Role::factory()->create([
        'name' => 'Admin',
        'slug' => 'admin',
    ]);
    $adminRole->permissions()->syncWithoutDetaching([$organizeContentPermission->id]);

    $viewerRole = Role::factory()->create([
        'name' => 'Viewer',
        'slug' => 'viewer',
    ]);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->roles()->attach($superAdminRole);

    $this->admin = User::factory()->create();
    $this->admin->roles()->attach($adminRole);

    $this->viewer = User::factory()->create();
    $this->viewer->roles()->attach($viewerRole);
});

it('allows admin users to access dashboard', function () {
    $response = $this->actingAs($this->admin)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('isAdmin')
        ->has('dashboardData')
    );
});

it('allows super admin users to access dashboard', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('isAdmin', true)
        ->has('dashboardData')
    );
});

it('denies access to users without organize-content permission', function () {
    $response = $this->actingAs($this->viewer)
        ->get('/dashboard');

    $response->assertForbidden();
});

it('returns correct metrics for admin dashboard', function () {
    // Clear existing data first to ensure clean state
    File::query()->delete();
    Folder::query()->delete();
    Requests::query()->delete();

    // Create test data
    File::factory(5)->create();
    Folder::factory(3)->create();
    Requests::factory(10)->create();

    $expectedFiles = File::count();
    $expectedFolders = Folder::count();
    $expectedRequests = Requests::count();

    $response = $this->actingAs($this->admin)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('dashboardData.metrics.total_files', $expectedFiles)
        ->where('dashboardData.metrics.total_folders', $expectedFolders)
        ->where('dashboardData.metrics.total_requests', $expectedRequests)
    );
});

it('calculates storage metrics correctly', function () {
    // Create files with known sizes
    File::factory()->create(['file_size' => 1024 * 1024]); // 1MB
    File::factory()->create(['file_size' => 2 * 1024 * 1024]); // 2MB

    $response = $this->actingAs($this->admin)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('dashboardData.storage.total_storage', 3 * 1024 * 1024) // 3MB total
        ->has('dashboardData.storage.used_percentage')
        ->has('dashboardData.storage.storage_capacity')
    );
});

it('does not expose overdue requests metrics', function () {
    Requests::factory()->create([
        'status' => 'Pending',
        'end_date' => now()->subDays(5),
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->missing('dashboardData.overdue_requests')
    );
});

it('returns pending requests breakdown', function () {
    Requests::factory(3)->create(['status' => 'Pending']);
    Requests::factory(2)->create(['status' => 'Claimed']);
    Requests::factory(1)->create(['status' => 'Awaiting Update']);
    Requests::factory(4)->create(['status' => 'In Review']);
    Requests::factory(2)->create(['status' => 'Complete']);
    Requests::factory()->create(['status' => 'Rejected']);

    $response = $this->actingAs($this->admin)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('dashboardData.pending_requests.pending_count', 3)
        ->where('dashboardData.pending_requests.claimed_count', 2)
        ->where('dashboardData.pending_requests.awaiting_update_count', 1)
        ->where('dashboardData.pending_requests.in_review_count', 4)
        ->where('dashboardData.pending_requests.total_active', 10)
    );
});

it('returns requests grouped by status', function () {
    Requests::factory(5)->create(['status' => 'Pending']);
    Requests::factory(3)->create(['status' => 'Complete']);
    Requests::factory(2)->create(['status' => 'Rejected']);

    $response = $this->actingAs($this->admin)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('dashboardData.requests_by_status', 3)
    );
});

it('returns recent activities with user names', function () {
    // Clear existing audit logs and create fresh ones for this test
    AuditLog::query()->delete();

    AuditLog::factory(5)->create([
        'user_id' => $this->admin->id,
        'user_name' => $this->admin->name,
        'action' => 'file_viewed',
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('dashboardData.recent_activities', 5)
        ->where('dashboardData.recent_activities.0.user_name', $this->admin->name)
    );
});

it('returns files upload trend for 30 days', function () {
    // Create files from different days
    File::factory()->create(['created_at' => now()->subDays(5)]);
    File::factory()->create(['created_at' => now()->subDays(5)]);
    File::factory()->create(['created_at' => now()->subDays(10)]);

    $response = $this->actingAs($this->admin)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('dashboardData.files_upload_trend')
    );
});

it('returns quick access shortcuts with counts', function () {
    // Clear data and create fresh test data
    Requests::query()->delete();
    File::query()->delete();

    Requests::factory(3)->create(['status' => 'Pending']);
    File::factory(5)->create(['created_at' => now()->subDays(3)]);

    // Clear audit logs AFTER creating requests/files (since observers create logs)
    // Then create exactly 20 audit logs for testing
    AuditLog::query()->delete();
    AuditLog::factory(20)->create(['created_at' => now()->subDays(10)]);

    $expectedPendingApprovals = Requests::whereIn('status', ['Pending', 'Claimed', 'Awaiting Update', 'In Review'])->count();
    $expectedRecentUploads = File::where('created_at', '>=', now()->subDays(7))->count();
    $expectedAuditLogs = AuditLog::where('created_at', '>=', now()->subDays(30))->count();

    $response = $this->actingAs($this->admin)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('dashboardData.quick_access.pending_approvals', $expectedPendingApprovals)
        ->where('dashboardData.quick_access.recent_uploads', $expectedRecentUploads)
        ->where('dashboardData.quick_access.audit_logs_total', $expectedAuditLogs)
    );
});

it('returns basic dashboard for non-admin users with organize-content permission', function () {
    $permission = Permission::where('slug', 'organize-content')->firstOrFail();

    $recordsOfficerRole = Role::factory()->create([
        'name' => 'Records Officer',
        'slug' => 'records-officer',
    ]);
    $recordsOfficerRole->permissions()->syncWithoutDetaching([$permission->id]);

    $recordsOfficer = User::factory()->create();
    $recordsOfficer->roles()->attach($recordsOfficerRole);

    $response = $this->actingAs($recordsOfficer)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('isAdmin', false)
        ->missing('dashboardData')
        ->has('folders')
        ->has('files')
    );
});

it('shows fewer recent items for admin users', function () {
    // Admin dashboard shows only 5 recent items
    File::factory(10)->create();
    Folder::factory(10)->create();

    $response = $this->actingAs($this->admin)
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('folders', 5)
        ->has('files', 5)
    );
});
