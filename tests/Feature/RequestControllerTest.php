<?php

use App\Jobs\ProcessFileOcr;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Permission;
use App\Models\RequestLog;
use App\Models\Requests;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use App\Services\FileVersionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('authenticated users can create a request', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create();
    $tags = Tag::factory()->count(2)->metadata()->create();

    $this->actingAs($user);

    $response = $this->postJson('/api/requests', [
        'document_type' => 'Financial Records',
        'file_number' => 'FN-0001',
        'request_title' => 'Request for Budget Report',
        'start_date' => '01/2026',
        'end_date' => '03/2026',
        'purpose' => 'For audit review',
        'priority' => 'urgent',
        'required_format' => 'Retrieve Original Copy',
        'department_id' => (string) $department->id,
        'request_id' => (string) Str::uuid(),
        'requires_scan' => false,
        'metadata_tag_ids' => $tags->pluck('id')->all(),
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('requests', [
        'document_type' => 'Financial Records',
        'file_number' => 'FN-0001',
        'request_title' => 'Request for Budget Report',
        'priority' => 'urgent',
        'requested_by' => $user->id,
        'status' => 'Pending',
        'department_id' => $department->id,
    ]);
    $this->assertDatabaseHas('requests', [
        'id' => $response->json('id'),
        'start_date' => '2026-01-01 00:00:00',
        'end_date' => '2026-03-31 00:00:00',
    ]);

    foreach ($tags as $tag) {
        $this->assertDatabaseHas('request_tag_pivot', [
            'request_id' => $response->json('id'),
            'tag_id' => $tag->id,
        ]);
    }

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'action' => 'request_created',
    ]);
    expect(AuditLog::where('action', 'request_created')->where('auditable_id', $response->json('id'))->count())->toBe(1);
});

test('request creation persists requires scan flag', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create();

    $this->actingAs($user);

    $response = $this->postJson('/api/requests', [
        'document_type' => 'Correspondence',
        'file_number' => 'FN-9000',
        'request_title' => 'Request needing scan',
        'start_date' => '02/2026',
        'end_date' => '05/2026',
        'purpose' => 'Archival compliance',
        'priority' => 'standard',
        'required_format' => 'Reproduce Hardcopy',
        'department_id' => (string) $department->id,
        'request_id' => (string) Str::uuid(),
        'requires_scan' => true,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('requests', [
        'id' => $response->json('id'),
        'requires_scan' => true,
        'scan_review_status' => null,
        'scan_review_notes' => null,
    ]);
    $this->assertDatabaseHas('requests', [
        'id' => $response->json('id'),
        'start_date' => '2026-02-01 00:00:00',
        'end_date' => '2026-05-31 00:00:00',
    ]);
});

test('request creation validates required fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/api/requests', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors([
        'document_type',
        'request_title',
        'purpose',
        'priority',
        'required_format',
        'department_id',
        'request_id',
        'requires_scan',
    ]);
});

test('request can be created without file number', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/api/requests', [
        'document_type' => 'Legal',
        'request_title' => 'Test Request Without File Number',
        'purpose' => 'Test purpose',
        'required_format' => 'Electronic Copy Only',
        'priority' => 'standard',
        'department_id' => (string) $department->id,
        'request_id' => (string) Str::uuid(),
        'requires_scan' => false,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('requests', [
        'document_type' => 'Legal',
        'file_number' => null,
        'request_title' => 'Test Request Without File Number',
        'requested_by' => $user->id,
    ]);
});

test('request creation validates format values', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/api/requests', [
        'document_type' => 'Legal',
        'file_number' => 'FN-0001',
        'request_title' => 'Test Request',
        'purpose' => 'Test purpose',
        'required_format' => 'Invalid Format',
        'priority' => 'invalid',
        'department_id' => (string) $department->id,
        'request_id' => (string) Str::uuid(),
        'requires_scan' => false,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['required_format', 'priority']);
});

test('authenticated users can list their own requests', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownRequest = Requests::factory()->create(['requested_by' => $user->id]);
    $otherRequest = Requests::factory()->create(['requested_by' => $otherUser->id]);

    $this->actingAs($user);

    $response = $this->getJson('/api/requests/my');

    $response->assertOk();
    $response->assertJsonFragment(['request_title' => $ownRequest->request_title]);
    $response->assertJsonMissing(['request_title' => $otherRequest->request_title]);
});

test('authenticated users can list all requests', function () {
    $user = User::factory()->create();

    $request1 = Requests::factory()->create();
    $request2 = Requests::factory()->create();

    $this->actingAs($user);

    $response = $this->getJson('/api/requests');

    $response->assertOk();
    $response->assertJsonFragment(['request_title' => $request1->request_title]);
    $response->assertJsonFragment(['request_title' => $request2->request_title]);
});

test('authenticated users can view a single request', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create([
        'requested_by' => $user->id,
        'requires_scan' => true,
        'scan_review_status' => 'Rejected',
        'scan_review_notes' => 'Upload is blurry',
    ]);

    $this->actingAs($user);

    $response = $this->getJson("/api/requests/{$request->id}");

    $response->assertOk();
    $response->assertJsonFragment([
        'request_title' => $request->request_title,
        'requires_scan' => true,
        'scan_review_status' => 'Rejected',
        'scan_review_notes' => 'Upload is blurry',
    ]);
});

test('authenticated users can update request status', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create([
        'status' => 'Pending',
        'requires_scan' => true,
    ]);

    $this->actingAs($user);

    $response = $this->patchJson("/api/requests/{$request->id}/status", [
        'status' => 'Claimed',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => 'Awaiting Update',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'auditable_id' => $request->id,
        'action' => 'request_status_changed',
    ]);
    expect(AuditLog::where('auditable_id', $request->id)->where('action', 'request_status_changed')->count())->toBe(1);
});

test('status update validates allowed values', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create();

    $this->actingAs($user);

    $response = $this->patchJson("/api/requests/{$request->id}/status", [
        'status' => 'InvalidStatus',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('status');
});

test('claiming a request without scan requirement completes immediately', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create([
        'status' => 'Pending',
        'requires_scan' => false,
        'scan_review_status' => 'Rejected',
        'scan_review_notes' => 'old note',
    ]);

    $this->actingAs($user);

    $response = $this->patchJson("/api/requests/{$request->id}/status", [
        'status' => 'Claimed',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => 'Complete',
        'scan_review_status' => null,
        'scan_review_notes' => null,
    ]);
});

test('rejecting a request stores reviewer notes', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create([
        'status' => 'In Review',
        'requires_scan' => true,
    ]);

    $this->actingAs($user);

    $response = $this->patchJson("/api/requests/{$request->id}/status", [
        'status' => 'Rejected',
        'notes' => 'Need clearer details',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => 'Rejected',
        'notes' => 'Need clearer details',
    ]);
});

test('authenticated users can attach a file to a request', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create();
    $file = File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::ACTIVE,
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/api/requests/{$request->id}/attach-file", [
        'file_id' => $file->id,
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('file_request', [
        'request_id' => $request->id,
        'file_id' => $file->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'auditable_id' => $request->id,
        'action' => 'request_file_attached',
    ]);
});

test('disposed files require acknowledgement before attaching to a request', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create();
    $file = File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::DISPOSED,
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/api/requests/{$request->id}/attach-file", [
        'file_id' => $file->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['disposed_acknowledged']);

    $this->assertDatabaseMissing('file_request', [
        'request_id' => $request->id,
        'file_id' => $file->id,
    ]);
});

test('disposed files can be attached when acknowledgement is provided', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create();
    $file = File::factory()->create([
        'retention_status' => \App\Support\RetentionStatus::DISPOSED,
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/api/requests/{$request->id}/attach-file", [
        'file_id' => $file->id,
        'disposed_acknowledged' => true,
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('file_request', [
        'request_id' => $request->id,
        'file_id' => $file->id,
    ]);
});

test('duplicate file attachment is rejected', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create();
    $file = File::factory()->create();

    $request->files()->attach($file->id);

    $this->actingAs($user);

    $response = $this->postJson("/api/requests/{$request->id}/attach-file", [
        'file_id' => $file->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonFragment(['message' => 'File already attached.']);
});

test('request cannot have more than one linked record file', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create();
    $firstFile = File::factory()->create([
        'record_id' => 'REC-'.Str::upper(Str::random(8)),
    ]);
    $secondFile = File::factory()->create([
        'record_id' => 'REC-'.Str::upper(Str::random(8)),
    ]);

    $request->files()->attach($firstFile->id);

    $this->actingAs($user);

    $response = $this->postJson("/api/requests/{$request->id}/attach-file", [
        'file_id' => $secondFile->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['file_id']);
    $response->assertJsonPath('errors.file_id.0', 'Only one linked file is allowed per request.');

    $this->assertDatabaseMissing('file_request', [
        'request_id' => $request->id,
        'file_id' => $secondFile->id,
    ]);
});

test('authenticated users can detach a file from a request', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create();
    $file = File::factory()->create();

    $request->files()->attach($file->id);

    $this->actingAs($user);

    $response = $this->deleteJson("/api/requests/{$request->id}/detach-file/{$file->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('file_request', [
        'request_id' => $request->id,
        'file_id' => $file->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'auditable_id' => $request->id,
        'action' => 'request_file_detached',
    ]);
});

test('uploading a scan moves scan status to in review', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $request = Requests::factory()->create([
        'status' => 'Awaiting Update',
        'requires_scan' => true,
        'scan_review_status' => null,
    ]);
    $targetFileName = 'document-'.Str::lower(Str::random(5));

    $targetFile = File::factory()->create([
        'department_id' => $request->department_id,
        'record_id' => 'REC-'.Str::upper(Str::random(8)),
        'file_name' => $targetFileName.'.pdf',
    ]);

    $request->files()->attach($targetFile->id);

    $this->actingAs($user);

    $response = $this->postJson("/api/requests/{$request->id}/upload-scan", [
        'file' => UploadedFile::fake()->create($targetFileName.'.pdf', 150, 'application/pdf'),
        'target_file_id' => $targetFile->id,
        'notes' => 'Uploading scan for review',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => 'Awaiting Update',
        'scan_review_status' => 'In Review',
        'scan_review_notes' => null,
        'notes' => 'Uploading scan for review',
    ]);
    $createdScan = File::query()
        ->where('uploaded_by', $user->id)
        ->where('record_id', 'like', 'SCAN-%')
        ->latest()
        ->first();

    expect($createdScan)->not->toBeNull();
    expect($createdScan?->file_name)->toBe($targetFileName);
});

test('uploading a scan accepts filenames with numeric dotted suffixes', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $request = Requests::factory()->create([
        'status' => 'Awaiting Update',
        'requires_scan' => true,
    ]);

    $targetFileName = 'CAO-No.-2-s.-2022';

    $targetFile = File::factory()->create([
        'department_id' => $request->department_id,
        'record_id' => 'REC-'.Str::upper(Str::random(8)),
        'file_name' => $targetFileName,
    ]);

    $request->files()->attach($targetFile->id);

    $this->actingAs($user);

    $response = $this->postJson("/api/requests/{$request->id}/upload-scan", [
        'file' => UploadedFile::fake()->create($targetFileName.'.pdf', 150, 'application/pdf'),
        'target_file_id' => $targetFile->id,
    ]);

    $response->assertCreated();

    $createdScan = File::query()
        ->where('uploaded_by', $user->id)
        ->where('record_id', 'like', 'SCAN-%')
        ->latest()
        ->first();

    expect($createdScan)->not->toBeNull();
    expect($createdScan?->file_name)->toBe($targetFileName);
});

test('uploading a scan accepts image files', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $request = Requests::factory()->create([
        'status' => 'Awaiting Update',
        'requires_scan' => true,
    ]);
    $targetFileName = 'image-record-'.Str::lower(Str::random(5));

    $targetFile = File::factory()->create([
        'department_id' => $request->department_id,
        'record_id' => 'REC-'.Str::upper(Str::random(8)),
        'file_name' => $targetFileName.'.pdf',
    ]);

    $request->files()->attach($targetFile->id);

    $this->actingAs($user);

    $response = $this->postJson("/api/requests/{$request->id}/upload-scan", [
        'file' => UploadedFile::fake()->image($targetFileName.'.png', 120, 160),
        'target_file_id' => $targetFile->id,
    ]);

    $response->assertCreated();

    $createdScan = File::query()
        ->where('uploaded_by', $user->id)
        ->where('record_id', 'like', 'SCAN-%')
        ->latest()
        ->first();

    expect($createdScan)->not->toBeNull();
    expect($createdScan?->file_name)->toBe($targetFileName);
    expect($createdScan?->mime_type)->toBe('image/png');
});

test('request cannot have more than one scanned upload at a time', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    [$request, $targetFile] = createRequestWithDraftScan([
        'status' => 'Awaiting Update',
        'requires_scan' => true,
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/api/requests/{$request->id}/upload-scan", [
        'file' => UploadedFile::fake()->create($targetFile->file_name, 120, 'application/pdf'),
        'target_file_id' => $targetFile->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['file']);
    $response->assertJsonPath('errors.file.0', 'Only one scanned document is allowed per request. Remove the existing scan first.');
});

test('uploading a scan rejects unsupported file types', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $request = Requests::factory()->create([
        'status' => 'Awaiting Update',
        'requires_scan' => true,
    ]);
    $targetFileName = 'invalid-upload-'.Str::lower(Str::random(5));

    $targetFile = File::factory()->create([
        'department_id' => $request->department_id,
        'record_id' => 'REC-'.Str::upper(Str::random(8)),
        'file_name' => $targetFileName.'.pdf',
    ]);

    $request->files()->attach($targetFile->id);

    $this->actingAs($user);

    $response = $this->postJson("/api/requests/{$request->id}/upload-scan", [
        'file' => UploadedFile::fake()->create($targetFileName.'.txt', 10, 'text/plain'),
        'target_file_id' => $targetFile->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('file');
});

test('users can delete draft scan uploads', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    [$request, , $draftScan] = createRequestWithDraftScan([
        'requested_by' => $user->id,
        'scan_review_status' => 'In Review',
    ]);

    Storage::disk('local')->put($draftScan->file_path, 'PDF');

    $this->actingAs($user);

    $response = $this->deleteJson("/api/requests/{$request->id}/scans/{$draftScan->id}");

    $response->assertOk();

    $this->assertDatabaseMissing('files', [
        'id' => $draftScan->id,
    ]);

    Storage::disk('local')->assertMissing($draftScan->file_path);

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'scan_review_status' => null,
        'scan_review_notes' => null,
    ]);
});

test('draft scan deletion endpoint rejects non-draft files', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    [$request, , $pendingFile] = createRequestWithPendingScan([
        'requested_by' => $user->id,
    ]);

    Storage::disk('local')->put($pendingFile->file_path, 'PDF');

    $this->actingAs($user);

    $response = $this->deleteJson("/api/requests/{$request->id}/scans/{$pendingFile->id}");

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('file');

    $this->assertDatabaseHas('files', [
        'id' => $pendingFile->id,
    ]);
});

test('reviewers can reject pending scans with notes', function () {
    $reviewer = User::factory()->create();
    grantReviewPermission($reviewer);

    [$request, , $pendingFile] = createRequestWithPendingScan();

    Storage::fake('local');
    Storage::disk('local')->put($pendingFile->file_path, 'PDF');

    $pendingScanId = $pendingFile->id;
    $pendingScanPath = $pendingFile->file_path;

    $this->actingAs($reviewer);

    $response = $this->postJson("/api/requests/{$request->id}/scans/{$pendingScanId}/reject", [
        'reason' => 'Need an official seal on the last page.',
    ]);

    $response->assertOk();

    // File should be completely deleted from database
    $this->assertDatabaseMissing('files', [
        'id' => $pendingScanId,
    ]);

    // File should be deleted from storage
    Storage::disk('local')->assertMissing($pendingScanPath);

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => 'Awaiting Update',
        'scan_review_status' => 'Rejected',
        'scan_review_notes' => 'Need an official seal on the last page.',
    ]);

    // Rejection should be logged
    $this->assertDatabaseHas('request_logs', [
        'request_id' => $request->id,
        'action' => 'scan_rejected',
    ]);
});

test('reviewers can approve pending scans and complete the request', function () {
    $reviewer = User::factory()->create();
    grantReviewPermission($reviewer);

    [$request, $targetFile, $pendingFile] = createRequestWithPendingScan([
        'status' => 'In Review',
    ]);

    config(['filesystems.default' => 'local']);
    Storage::fake('local');
    Storage::disk('local')->put($pendingFile->file_path, 'PDF');

    app()->bind(FileVersionService::class, function () {
        return new class extends FileVersionService
        {
            public function createVersion(File $file, array $attributes, ?string $disk = null): FileVersion
            {
                return tap(new FileVersion, function (FileVersion $version): void {
                    $version->id = (string) Str::uuid();
                    $version->version_number = 2;
                });
            }
        };
    });

    Queue::fake();

    $this->actingAs($reviewer);

    $response = $this->postJson("/api/requests/{$request->id}/scans/{$pendingFile->id}/approve");

    $response->assertOk();

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => 'Complete',
        'scan_review_status' => 'Approved',
        'scan_review_notes' => null,
    ]);

    $this->assertDatabaseMissing('files', [
        'id' => $pendingFile->id,
    ]);

    Queue::assertPushed(ProcessFileOcr::class);
});

test('request history returns logs', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create(['requested_by' => $user->id]);

    RequestLog::create([
        'request_id' => $request->id,
        'user_id' => $user->id,
        'action' => 'request_created',
        'details' => ['description' => 'added'],
    ]);

    $this->actingAs($user);

    $response = $this->getJson('/api/requests/history');

    $response->assertOk();
    $response->assertJsonFragment(['action' => 'request_created']);
});

test('unauthenticated users cannot create requests', function () {
    $response = $this->postJson('/api/requests', [
        'document_type' => 'Correspondence',
    ]);

    $response->assertUnauthorized();
});

function grantReviewPermission(User $user): void
{
    $permission = Permission::factory()->create([
        'slug' => 'review-content',
    ]);

    $role = Role::factory()->create();
    $role->permissions()->attach($permission);
    $user->roles()->attach($role);
}

/**
 * @return array{Requests, File, File}
 */
function createRequestWithPendingScan(array $requestAttributes = []): array
{
    $request = Requests::factory()->create(array_merge([
        'requires_scan' => true,
        'status' => 'In Review',
        'scan_review_status' => 'In Review',
    ], $requestAttributes));

    $targetFile = File::factory()->create([
        'department_id' => $request->department_id,
        'record_id' => 'REC-'.Str::upper(Str::random(8)),
    ]);

    $pendingPath = 'scans/'.Str::uuid().'.pdf';

    $pendingFile = File::factory()->create([
        'department_id' => $request->department_id,
        'record_id' => 'SCAN-'.Str::upper(Str::random(8)),
        'status' => 'pending',
        'file_name' => 'pending-scan-'.Str::lower(Str::random(6)),
        'file_path' => $pendingPath,
        'remarks' => json_encode([
            'type' => 'request_scan',
            'request_id' => $request->id,
            'request_number' => $request->request_id,
            'request_title' => $request->request_title,
            'target_file_id' => $targetFile->id,
            'target_file_record_id' => $targetFile->record_id,
            'target_file_name' => $targetFile->displayName(),
        ], JSON_UNESCAPED_SLASHES),
    ]);

    $request->files()->attach([$targetFile->id, $pendingFile->id]);

    return [$request->fresh('files'), $targetFile->fresh(), $pendingFile->fresh()];
}

/**
 * @return array{Requests, File, File}
 */
function createRequestWithDraftScan(array $requestAttributes = []): array
{
    $request = Requests::factory()->create(array_merge([
        'requires_scan' => true,
        'status' => 'Awaiting Update',
        'scan_review_status' => 'In Review',
        'scan_review_notes' => null,
    ], $requestAttributes));

    $targetFile = File::factory()->create([
        'department_id' => $request->department_id,
        'record_id' => 'REC-'.Str::upper(Str::random(8)),
    ]);

    $draftPath = 'scans/'.Str::uuid().'.pdf';

    $draftFile = File::factory()->create([
        'department_id' => $request->department_id,
        'record_id' => 'SCAN-'.Str::upper(Str::random(8)),
        'status' => 'draft',
        'file_name' => 'draft-scan-'.Str::lower(Str::random(6)),
        'file_path' => $draftPath,
        'remarks' => json_encode([
            'type' => 'request_scan',
            'request_id' => $request->id,
            'request_number' => $request->request_id,
            'request_title' => $request->request_title,
            'target_file_id' => $targetFile->id,
            'target_file_record_id' => $targetFile->record_id,
            'target_file_name' => $targetFile->displayName(),
        ], JSON_UNESCAPED_SLASHES),
    ]);

    $request->files()->attach([$targetFile->id, $draftFile->id]);

    return [$request->fresh('files'), $targetFile->fresh(), $draftFile->fresh()];
}

test('searchable files endpoint returns files with OCR search', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create();

    $file1 = File::factory()->create([
        'file_name' => 'Annual Budget Report.pdf',
        'record_id' => 'REC-2026-001',
        'is_record' => true,
        'ocr_text' => 'This is the annual budget for fiscal year 2026. Total expenses: $500,000',
        'ocr_status' => 'completed',
    ]);

    $file2 = File::factory()->create([
        'file_name' => 'Quarterly Report.pdf',
        'record_id' => 'REC-2026-002',
        'is_record' => true,
        'ocr_text' => 'Sales report showing revenue of $250,000 for Q1 2026',
        'ocr_status' => 'completed',
    ]);

    $this->actingAs($user);

    $response = $this->getJson("/api/requests/{$request->id}/searchable-files?search=budget");

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'file_name',
                'record_id',
                'snippet',
                'ocr_status',
            ],
        ],
        'has_more',
    ]);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($file1->id);
    expect($response->json('data.0.snippet'))->toContain('budget');
});

test('searchable files excludes already attached files', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create();

    $attachedFile = File::factory()->create([
        'file_name' => 'Already Linked.pdf',
        'record_id' => 'REC-2026-001',
        'is_record' => true,
    ]);

    $availableFile = File::factory()->create([
        'file_name' => 'Available File.pdf',
        'record_id' => 'REC-2026-002',
        'is_record' => true,
    ]);

    $request->files()->attach($attachedFile->id);

    $this->actingAs($user);

    $response = $this->getJson("/api/requests/{$request->id}/searchable-files?search=pdf");

    $response->assertOk();

    $fileIds = collect($response->json('data'))->pluck('id')->all();
    expect($fileIds)->not->toContain($attachedFile->id);
    expect($fileIds)->toContain($availableFile->id);
});

test('searchable files excludes SCAN records', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create();

    $scanFile = File::factory()->create([
        'file_name' => 'Scanned Document.pdf',
        'record_id' => 'SCAN-12345',
        'is_record' => true,
    ]);

    $normalFile = File::factory()->create([
        'file_name' => 'Normal Document.pdf',
        'record_id' => 'REC-2026-001',
        'is_record' => true,
    ]);

    $this->actingAs($user);

    $response = $this->getJson("/api/requests/{$request->id}/searchable-files?search=document");

    $response->assertOk();

    $fileIds = collect($response->json('data'))->pluck('id')->all();
    expect($fileIds)->not->toContain($scanFile->id);
    expect($fileIds)->toContain($normalFile->id);
});

test('searchable files returns empty when search is empty', function () {
    $user = User::factory()->create();
    $request = Requests::factory()->create();

    File::factory()->create([
        'file_name' => 'Test File.pdf',
        'is_record' => true,
    ]);

    $this->actingAs($user);

    $response = $this->getJson("/api/requests/{$request->id}/searchable-files?search=");

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
    expect($response->json('has_more'))->toBeFalse();
});
