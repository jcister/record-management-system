<?php

use App\Models\Department;
use App\Models\File;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use App\Support\RetentionStatus;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->department = Department::factory()->create();
    $this->physicalLocationTag = Tag::factory()->physicalLocation()->create();

    $permission = Permission::firstOrCreate(
        ['slug' => 'upload-submit-reports'],
        ['name' => 'Upload Submit Reports']
    );
    $role = Role::factory()->create();
    $role->permissions()->attach($permission);
    $this->user->roles()->attach($role);
});

function basePayload(UploadedFile $file, string $departmentId, int $physicalLocationTagId): array
{
    return [
        'file' => $file,
        'file_name' => 'Test File',
        'visibility' => 'public',
        'status' => 'pending',
        'author' => 'Test Author',
        'department_id' => (string) $departmentId,
        'record_id' => 'REC-'.uniqid(),
        'physical_location_tag_ids' => [$physicalLocationTagId],
        'retention_duration' => 'one_year',
        'date_released' => '02/19/26',
    ];
}

it('accepts valid PDF file and creates record', function (): void {
    $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n>>\nendobj\n%%EOF";
    $file = UploadedFile::fake()->createWithContent('document.pdf', $pdfContent);

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', true);
    expect(File::count())->toBe(1);
});

it('defaults retention status to active when not provided', function (): void {
    $file = UploadedFile::fake()->image('photo-default.jpg');

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertCreated();

    expect(File::first()->retention_status)->toBe(RetentionStatus::ACTIVE);
});

it('validates retention status options', function (): void {
    $file = UploadedFile::fake()->image('photo-invalid-status.jpg');
    $payload = basePayload($file, $this->department->id, $this->physicalLocationTag->id);
    $payload['retention_status'] = 'invalid-status';

    $response = $this->actingAs($this->user)->postJson('/api/files', $payload);

    $response->assertStatus(422)->assertJsonValidationErrors(['retention_status']);
});

it('accepts any readable file type for upload', function (): void {
    // Any readable file is accepted; only PDF/image become records
    $file = UploadedFile::fake()->createWithContent('data.zip', 'PK content here');

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', false);
    $stored = File::query()->where('is_record', false)->first();
    expect($stored)->not->toBeNull();
    expect(File::query()->where('is_record', true)->count())->toBe(0);
});

it('stores DOCX as StoredFile only', function (): void {
    $zip = new ZipArchive;
    $tempFile = tempnam(sys_get_temp_dir(), 'docx');
    $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><document></document>');
    $zip->close();

    $file = new UploadedFile($tempFile, 'document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', false);
    expect(File::query()->where('is_record', false)->count())->toBe(1);

    unlink($tempFile);
});

it('stores non-record file types as StoredFile only', function (): void {
    // Create a regular ZIP without word/document.xml - accepted and stored only
    $zip = new ZipArchive;
    $tempFile = tempnam(sys_get_temp_dir(), 'zip');
    $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('random.txt', 'This is just a ZIP file');
    $zip->close();

    $file = new UploadedFile($tempFile, 'fake.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', false);
    expect(File::query()->where('is_record', false)->count())->toBe(1);

    unlink($tempFile);
});

it('accepts valid image file and creates record', function (): void {
    $file = UploadedFile::fake()->image('photo.jpg');

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', true);
    expect(File::count())->toBe(1);
});

it('accepts image extension and creates record', function (): void {
    $file = UploadedFile::fake()->createWithContent('photo.jpg', 'binary image content');

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', true);
    expect(File::count())->toBe(1);
});

it('stores text file as StoredFile only', function (): void {
    $file = UploadedFile::fake()->createWithContent('document.txt', 'This is a valid text file.');

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', false);
    expect(File::query()->where('is_record', false)->count())->toBe(1);
});

it('rejects file exceeding size limit', function (): void {
    // Create a file larger than 100MB (102400 KB)
    $file = UploadedFile::fake()->create('large.pdf', 102401);

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['file']);
});

it('stores RTF with valid content as StoredFile only', function (): void {
    $rtfContent = '{\rtf1\ansi\deff0 {\fonttbl {\f0 Times New Roman;}} \f0\fs60 Hello World!}';
    $file = UploadedFile::fake()->createWithContent('document.rtf', $rtfContent);

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', false);
    expect(File::query()->where('is_record', false)->count())->toBe(1);
});

it('stores RTF file as StoredFile only', function (): void {
    $file = UploadedFile::fake()->createWithContent('doc.rtf', 'Plain text with .rtf extension');

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', false);
    expect(File::query()->where('is_record', false)->count())->toBe(1);
});

it('persists date fields when provided', function (): void {
    $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n>>\nendobj\n%%EOF";
    $file = UploadedFile::fake()->createWithContent('dates.pdf', $pdfContent);

    $payload = basePayload($file, $this->department->id, $this->physicalLocationTag->id);
    $payload['date_created'] = '02/10/26';
    $payload['date_released'] = '02/12/26';
    $payload['retention_duration'] = 'ten_years';

    $response = $this->actingAs($this->user)->postJson('/api/files', $payload);

    $response->assertStatus(201);

    $saved = File::where('record_id', $payload['record_id'])->firstOrFail();

    expect($saved->date_created?->toDateString())->toBe('2026-02-10')
        ->and($saved->date_released?->toDateString())->toBe('2026-02-12')
        ->and($saved->retention_duration)->toBe('ten_years')
        ->and($saved->retention_expires_at?->toDateString())->toBe('2036-02-12');
});

it('requires retention duration when creating a record', function (): void {
    $file = UploadedFile::fake()->createWithContent('missing-retention.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");

    $payload = basePayload($file, $this->department->id, $this->physicalLocationTag->id);
    unset($payload['retention_duration']);

    $response = $this->actingAs($this->user)->postJson('/api/files', $payload);

    $response->assertStatus(422)->assertJsonValidationErrors(['retention_duration']);
});

it('requires date released when creating a record', function (): void {
    $file = UploadedFile::fake()->createWithContent('missing-date-released.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");

    $payload = basePayload($file, $this->department->id, $this->physicalLocationTag->id);
    unset($payload['date_released']);

    $response = $this->actingAs($this->user)->postJson('/api/files', $payload);

    $response->assertStatus(422)->assertJsonValidationErrors(['date_released']);
});

it('rejects date released earlier than date created', function (): void {
    $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n>>\nendobj\n%%EOF";
    $file = UploadedFile::fake()->createWithContent('invalid-dates.pdf', $pdfContent);

    $payload = basePayload($file, $this->department->id, $this->physicalLocationTag->id);
    $payload['date_created'] = '02/12/26';
    $payload['date_released'] = '02/10/26';

    $response = $this->actingAs($this->user)->postJson('/api/files', $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['date_released']);
});

it('rejects creating a file with a duplicate name in the same folder', function (): void {
    $folder = \App\Models\Folder::factory()->create();
    File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'JCMA-01-2024',
    ]);

    $file = UploadedFile::fake()->createWithContent('JCMA-01-2024.pdf', "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n>>\nendobj\n%%EOF");
    $payload = basePayload($file, $this->department->id, $this->physicalLocationTag->id);
    $payload['folder_id'] = $folder->id;
    $payload['file_name'] = 'JCMA-01-2024';

    $response = $this->actingAs($this->user)->postJson('/api/files', $payload);

    $response->assertUnprocessable()
        ->assertJsonPath('code', 'duplicate_filename')
        ->assertJsonPath('folder_id', $folder->id);

    expect(File::query()->where('folder_id', $folder->id)->count())->toBe(1);
});

it('recalculates retention expiration when duration changes', function (): void {
    $file = File::factory()->create([
        'department_id' => (string) $this->department->id,
        'uploaded_by' => $this->user->id,
        'date_created' => '2024-01-10',
        'date_released' => '2024-01-15',
        'retention_duration' => 'one_year',
        'retention_expires_at' => '2025-01-15',
    ]);

    $response = $this->actingAs($this->user)->patchJson(
        "/api/files/{$file->id}",
        [
            'retention_duration' => 'ten_years',
        ]
    );

    $response->assertOk();

    expect($file->refresh()->retention_expires_at?->toDateString())->toBe('2034-01-15');
});

it('stores ODT as StoredFile only', function (): void {
    $zip = new ZipArchive;
    $tempFile = tempnam(sys_get_temp_dir(), 'odt');
    $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('content.xml', '<?xml version="1.0"?><document></document>');
    $zip->close();

    $file = new UploadedFile($tempFile, 'document.odt', 'application/vnd.oasis.opendocument.text', null, true);

    $response = $this->actingAs($this->user)->postJson('/api/files', basePayload($file, $this->department->id, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', false);
    expect(File::query()->where('is_record', false)->count())->toBe(1);

    unlink($tempFile);
});
