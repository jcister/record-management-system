<?php

use App\Jobs\ProcessFileOcr;
use App\Models\Department;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function grantUploadContentPermissionForFileSearch(User $user): void
{
    $permission = Permission::firstOrCreate(
        ['slug' => 'upload-submit-reports'],
        ['name' => 'Upload Submit Reports']
    );

    $role = Role::factory()->create();
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $user->roles()->attach($role);
}

it('searches files by OCR text', function (): void {
    $user = User::factory()->create();
    grantUploadContentPermissionForFileSearch($user);
    $file = File::factory()->create([
        'ocr_text' => 'uniquephrase ocrtext content',
        'file_name' => 'test-file',
    ]);

    $this->actingAs($user);

    $response = $this->getJson('/api/files?search=uniquephrase');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($file->id);
});

it('dispatches OCR job on file creation', function (): void {
    Bus::fake();

    $user = User::factory()->create();
    grantUploadContentPermissionForFileSearch($user);
    $department = Department::factory()->create();
    $locationTag = Tag::factory()->physicalLocation()->create();

    $payload = [
        'author' => 'Test Author',
        'department_id' => (string) $department->id,
        'record_id' => 'REC-'.uniqid(),
        'file_name' => 'ocr-test',
        'file' => UploadedFile::fake()->createWithContent('ocr-test.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
        'visibility' => 'public',
        'status' => 'pending',
        'physical_location_tag_ids' => [$locationTag->id],
        'retention_duration' => 'one_year',
        'date_released' => now()->toDateString(),
    ];

    $this->actingAs($user);

    $response = $this->postJson('/api/files', $payload);

    $response->assertCreated();

    Bus::assertDispatched(ProcessFileOcr::class, function (ProcessFileOcr $job) use ($response): bool {
        $version = FileVersion::find($job->fileVersionId);

        return $version?->file_id === $response->json('data.id');
    });
});
