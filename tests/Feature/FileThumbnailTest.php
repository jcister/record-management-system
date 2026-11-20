<?php

use App\Jobs\GenerateFileThumbnail;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use App\Services\FileVersionService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('dispatches a thumbnail job when creating a PDF version', function (): void {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $file = File::factory()->create([
        'uploaded_by' => $user->id,
    ]);

    $path = 'private/uploads/'.Str::uuid().'.pdf';
    Storage::disk('local')->put($path, 'pdf-content');

    $version = app(FileVersionService::class)->createVersion(
        $file,
        [
            'file_path' => $path,
            'file_name' => 'sample.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1_234,
            'uploaded_by' => $user->id,
        ],
        'local'
    );

    Queue::assertPushed(GenerateFileThumbnail::class, function (GenerateFileThumbnail $job) use ($version): bool {
        return $job->fileVersionId === $version->id;
    });
});

it('streams the current version thumbnail when available', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $file = File::factory()->create([
        'uploaded_by' => $user->id,
    ]);

    $version = FileVersion::query()->create([
        'file_id' => $file->id,
        'version_number' => 1,
        'file_name' => 'example.pdf',
        'file_path' => 'private/uploads/'.Str::uuid().'.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2_048,
        'uploaded_by' => $user->id,
        'thumbnail_disk' => 'local',
        'thumbnail_path_webp' => 'thumbnails/'.$file->id.'/example.webp',
        'thumbnail_generated_at' => now(),
    ]);

    $file->forceFill([
        'current_version_id' => $version->id,
        'latest_version_number' => 1,
    ])->save();

    Storage::disk('local')->put($version->thumbnail_path_webp, 'webp-bytes');

    actingAs($user);

    $response = get(route('api.files.thumbnail', $file));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/webp');
    expect($response->streamedContent())->toBe('webp-bytes');
});
