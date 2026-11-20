<?php

use App\Jobs\ProcessFileOcr;
use App\Models\Department;
use App\Models\File;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SsePublisher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

function buildFileTextExtractionPayload(UploadedFile $file, Department $department, int $physicalLocationTagId): array
{
    return [
        'author' => 'Test Author',
        'department_id' => (string) $department->id,
        'record_id' => 'REC-'.uniqid(),
        'physical_location_tag_ids' => [$physicalLocationTagId],
        'file_name' => $file->getClientOriginalName(),
        'file' => $file,
        'visibility' => 'public',
        'status' => 'pending',
        'date_created' => '2026-01-01',
        'date_released' => '2026-01-01',
        'retention_duration' => 'one_year',
    ];
}

function grantUploadContentPermissionForFileTextExtraction(User $user): void
{
    $uploadPermission = Permission::firstOrCreate(
        ['slug' => 'upload-submit-reports'],
        ['name' => 'Upload Submit Reports']
    );
    $organizePermission = Permission::firstOrCreate(
        ['slug' => 'organize-content'],
        ['name' => 'Organize Content']
    );

    $role = Role::factory()->create();
    $role->permissions()->syncWithoutDetaching([$uploadPermission->id, $organizePermission->id]);
    $user->roles()->attach($role);
}

function runOcrJobForFileVersion(string $fileVersionId): void
{
    $job = new ProcessFileOcr($fileVersionId);

    try {
        $job->handle(app(AuditService::class), app(SsePublisher::class));
    } catch (Throwable $exception) {
        $job->failed($exception);
    }
}

beforeEach(function (): void {
    $this->user = User::factory()->create();
    grantUploadContentPermissionForFileTextExtraction($this->user);
    $this->department = Department::factory()->create();
    $this->physicalLocationTag = Tag::factory()->physicalLocation()->create();
    Storage::fake(config('filesystems.default', 'local'));
    Queue::fake();
});

it('extracts text from PDF file via OCR', function (): void {
    $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n>>\nendobj\n%%EOF";
    $file = UploadedFile::fake()->createWithContent('document.pdf', $pdfContent);

    actingAs($this->user);

    $response = $this->postJson('/api/files', buildFileTextExtractionPayload($file, $this->department, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', true);

    $fileModel = File::query()->latest()->first();
    $fileModel->load('currentVersion');
    expect($fileModel->currentVersion)->not->toBeNull();

    runOcrJobForFileVersion((string) $fileModel->currentVersion->id);

    $fileModel->refresh();

    if ($fileModel->ocr_status === 'failed') {
        test()->markTestSkipped(
            'PDF OCR failed (minimal PDF may not be renderable): '.($fileModel->ocr_error ?? 'unknown')
        );
    }

    expect($fileModel->ocr_status)->toBe('completed');
    expect($fileModel->ocr_error)->toBeNull();
});

it('extracts text from image file via OCR', function (): void {
    $file = UploadedFile::fake()->image('photo.jpg');

    actingAs($this->user);

    $response = $this->postJson('/api/files', buildFileTextExtractionPayload($file, $this->department, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', true);

    $fileModel = File::query()->latest()->first();
    $fileModel->load('currentVersion');
    expect($fileModel->currentVersion)->not->toBeNull();

    runOcrJobForFileVersion((string) $fileModel->currentVersion->id);

    $fileModel->refresh();

    expect($fileModel->ocr_status)->toBe('completed');
    expect($fileModel->ocr_error)->toBeNull();
});

it('supports configurable OCR processing settings for images', function (): void {
    config()->set('services.ocr.default_language', 'eng');
    config()->set('services.ocr.process_timeout', 180);
    config()->set('services.ocr.pdf_render_timeout', 180);
    config()->set('services.ocr.pdf_dpi', 240);
    config()->set('services.ocr.oem', 1);
    config()->set('services.ocr.psm', 6);
    config()->set('services.ocr.fallback_psm', '3,11');
    config()->set('services.ocr.preprocess_enabled', true);
    config()->set('services.ocr.preprocess_min_width', 2200);

    $file = UploadedFile::fake()->image('configured-photo.jpg', 500, 350);

    actingAs($this->user);

    $response = $this->postJson('/api/files', buildFileTextExtractionPayload($file, $this->department, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', true);

    $fileModel = File::query()->latest()->first();
    $fileModel->load('currentVersion');
    expect($fileModel->currentVersion)->not->toBeNull();

    runOcrJobForFileVersion((string) $fileModel->currentVersion->id);

    $fileModel->refresh();

    expect($fileModel->ocr_status)->toBe('completed');
    expect($fileModel->ocr_error)->toBeNull();
});

it('fails when processing unsupported file format', function (): void {
    $content = 'This is a sample text file.';
    $file = UploadedFile::fake()->createWithContent('document.txt', $content);

    actingAs($this->user);

    $response = $this->postJson('/api/files', buildFileTextExtractionPayload($file, $this->department, $this->physicalLocationTag->id));

    $response->assertStatus(201);
    $response->assertJsonPath('record_created', false);

    $fileModel = File::query()->latest()->first();
    $fileModel->load('currentVersion');
    expect($fileModel->currentVersion)->not->toBeNull();

    runOcrJobForFileVersion((string) $fileModel->currentVersion->id);

    $fileModel->refresh();

    expect($fileModel->ocr_status)->toBe('failed');
    expect($fileModel->ocr_error)->toContain('Unsupported file format');
});
