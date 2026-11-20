<?php

/** @var \Tests\TestCase $this */

use App\Jobs\ProcessFileOcr;
use App\Models\Department;
use App\Models\File;
use App\Models\Folder;
use App\Models\Institution;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

function grantUploadAndOrganizePermissions(User $user): void
{
    $upload = Permission::firstOrCreate(
        ['slug' => 'upload-submit-reports'],
        ['name' => 'Upload Submit Reports']
    );
    $organize = Permission::firstOrCreate(
        ['slug' => 'organize-content'],
        ['name' => 'Organize Content']
    );

    $role = Role::factory()->create();
    $role->permissions()->syncWithoutDetaching([$upload->id, $organize->id]);
    $user->roles()->attach($role);
}

it('stores a file and tracks the uploader', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();
    $institution = Institution::factory()->create();
    $department = Department::factory()->create();
    $locationTag = Tag::factory()->physicalLocation()->create(['name' => 'Shelf A1']);

    grantUploadAndOrganizePermissions($user);

    $disk = config('filesystems.default', 'local');
    Storage::fake($disk);
    Queue::fake();

    $fakeFile = UploadedFile::fake()->createWithContent('example.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");

    $payload = [
        'folder_id' => $folder->id,
        'institution_id' => $institution->id,
        'department_id' => (string) $department->id,
        'author' => 'Jane Roe',
        'record_id' => 'REC-'.uniqid(),
        'physical_location_tag_ids' => [$locationTag->id],
        'file_name' => 'example',
        'file' => $fakeFile,
        'date_created' => '2026-01-01',
        'date_released' => '2026-01-01',
        'retention_duration' => 'one_year',
    ];

    actingAs($user);

    $response = $this->postJson('/api/files', $payload);

    $response->assertCreated()->assertJsonPath('data.uploaded_by', $user->id);

    $file = File::first();
    $file?->load('currentVersion');

    expect($file)
        ->not->toBeNull()
        ->uploaded_by->toBe($user->id)
        ->folder_id->toBe($folder->id)
        ->institution_id->toBe($institution->id)
        ->physical_location->toBe('Shelf A1');

    expect($file?->file_name)->toBe('example');
    expect($file?->currentVersion?->file_name)->toBe('example');

    Queue::assertPushed(ProcessFileOcr::class);
});

it('creates a record without uploading a file', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();
    $department = Department::factory()->create();
    $locationTag = Tag::factory()->physicalLocation()->create(['name' => 'Shelf B2']);

    grantUploadAndOrganizePermissions($user);

    Queue::fake();

    actingAs($user);

    $payload = [
        'folder_id' => $folder->id,
        'department_id' => (string) $department->id,
        'author' => 'Jane Roe',
        'record_id' => 'REC-'.uniqid(),
        'physical_location_tag_ids' => [$locationTag->id],
        'file_name' => 'Record Without File',
        'visibility' => 'public',
        'date_created' => '2026-01-01',
        'date_released' => '2026-01-01',
        'retention_duration' => 'one_year',
    ];

    $response = $this->postJson('/api/files', $payload);

    $response->assertCreated()->assertJsonPath('data.file_path', null);

    $file = File::first();

    expect($file)
        ->not->toBeNull()
        ->file_path->toBeNull()
        ->mime_type->toBeNull()
        ->file_size->toBeNull()
        ->status->toBe('approved');

    expect($file?->file_name)->toBe('Record Without File');

    Queue::assertNotPushed(ProcessFileOcr::class);
});

it('attaches a file to an existing record without creating a duplicate record', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create();
    $locationTag = Tag::factory()->physicalLocation()->create(['name' => 'Cabinet C1']);

    grantUploadAndOrganizePermissions($user);

    $disk = config('filesystems.default', 'local');
    Storage::fake($disk);
    Queue::fake();

    $file = File::factory()->create([
        'uploaded_by' => $user->id,
        'department_id' => (string) $department->id,
        'author' => 'Jane Roe',
        'record_id' => 'REC-'.uniqid(),
        'physical_location' => 'Shelf A1',
        'file_name' => 'pending',
        'file_path' => null,
        'mime_type' => null,
        'file_size' => null,
    ]);

    actingAs($user);

    $upload = UploadedFile::fake()->createWithContent('scan.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");

    $response = $this->postJson("/api/files/{$file->id}", [
        '_method' => 'patch',
        'file' => $upload,
        'department_id' => (string) $department->id,
        'author' => 'Jane Roe',
        'record_id' => $file->record_id,
        'physical_location_tag_ids' => [$locationTag->id],
        'file_name' => 'scan',
        'visibility' => 'public',
        'status' => 'approved',
    ]);

    $response->assertOk()->assertJsonPath('file_path', fn ($path) => ! empty($path));

    $file->refresh();

    expect($file->file_path)->not->toBeNull();
    expect($file->file_name)->toBe('scan');
    expect($file->physical_location)->toBe('Cabinet C1');
    expect(Storage::disk($disk)->exists($file->file_path))->toBeTrue();

    Queue::assertPushed(ProcessFileOcr::class);
});

it('updates a file without requiring every attribute', function () {
    $user = User::factory()->create();
    grantUploadAndOrganizePermissions($user);
    $file = File::factory()->create([
        'uploaded_by' => $user->id,
        'status' => 'pending',
    ]);

    actingAs($user);

    $response = $this->patchJson("/api/files/{$file->id}", [
        'status' => 'approved',
        'download_count' => 5,
    ]);

    $response->assertOk()->assertJsonPath('status', 'approved');

    expect($file->refresh())
        ->status->toBe('approved')
        ->download_count->toBe(5);
});

it('moves a file to another folder', function () {
    $user = User::factory()->create();
    grantUploadAndOrganizePermissions($user);
    $sourceFolder = Folder::factory()->create();
    $destinationFolder = Folder::factory()->create();

    $file = File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => $sourceFolder->id,
        'file_name' => 'report',
    ]);

    actingAs($user);

    $response = $this->patchJson("/api/files/{$file->id}/move", [
        'folder_id' => $destinationFolder->id,
    ]);

    $response->assertOk()->assertJsonPath('data.folder_id', $destinationFolder->id);

    expect($file->refresh())
        ->folder_id->toBe($destinationFolder->id)
        ->file_name->toBe('report');
});

it('prevents moving a file into a folder with a duplicate name', function () {
    $user = User::factory()->create();
    grantUploadAndOrganizePermissions($user);
    $destinationFolder = Folder::factory()->create();
    $sourceFolder = Folder::factory()->create();

    $conflicting = File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => $destinationFolder->id,
        'file_name' => 'report',
    ]);

    $file = File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => $sourceFolder->id,
        'file_name' => 'report',
    ]);

    actingAs($user);

    $response = $this->patchJson("/api/files/{$file->id}/move", [
        'folder_id' => $destinationFolder->id,
    ]);

    $response->assertUnprocessable()->assertJsonPath('message', fn (string $message) => str_contains($message, 'already exists'));

    expect($file->refresh()->folder_id)->toBe($sourceFolder->id);
    expect($conflicting->refresh()->folder_id)->toBe($destinationFolder->id);
});

it('allows renaming when moving to avoid duplicates', function () {
    $user = User::factory()->create();
    grantUploadAndOrganizePermissions($user);
    $destinationFolder = Folder::factory()->create();
    $sourceFolder = Folder::factory()->create();

    File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => $destinationFolder->id,
        'file_name' => 'report',
    ]);

    $file = File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => $sourceFolder->id,
        'file_name' => 'report',
    ]);

    actingAs($user);

    $response = $this->patchJson("/api/files/{$file->id}/move", [
        'folder_id' => $destinationFolder->id,
        'new_name' => 'report (1)',
    ]);

    $response->assertOk()->assertJsonPath('data.file_name', 'report (1)');

    expect($file->refresh())
        ->folder_id->toBe($destinationFolder->id)
        ->file_name->toBe('report (1)');
});

it('returns files including stored-only when indexing with folder_id', function () {
    $user = User::factory()->create();
    grantUploadAndOrganizePermissions($user);
    $folder = Folder::factory()->create();

    File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => $folder->id,
        'record_id' => 'REC-'.uniqid(),
        'is_record' => true,
    ]);

    File::factory()->create([
        'uploaded_by' => $user->id,
        'folder_id' => $folder->id,
        'record_id' => 'ATTACH-'.strtoupper(uniqid()),
        'is_record' => false,
        'file_path' => 'private/uploads/test.zip',
        'file_name' => 'test',
    ]);

    actingAs($user);

    $response = $this->getJson("/api/files?folder_id={$folder->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});
