<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

it('rejects chunked completion when the filename already exists in the same folder', function (): void {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    File::factory()->create([
        'folder_id' => $folder->id,
        'file_name' => 'JCMA-01-2024',
    ]);

    $uploadSessionId = (string) Str::uuid();

    Cache::put("chunk_upload:{$uploadSessionId}", [
        'upload_session_id' => $uploadSessionId,
        'user_id' => $user->id,
        'file_name' => 'JCMA-01-2024',
        'file_size' => 12345,
        'total_chunks' => 1,
        'received_chunks' => [0],
        'metadata' => [
            'author' => 'Juan Luna',
            'department_id' => null,
            'record_id' => 'REC-CHUNK-EDGE-002',
            'date_created' => null,
            'date_released' => null,
            'physical_location' => null,
            'folder_id' => $folder->id,
            'visibility' => 'public',
            'status' => 'approved',
            'retention_status' => 'active',
            'retention_duration' => null,
            'retention_expires_at' => null,
        ],
        'metadata_tag_ids' => [],
        'physical_location_tag_ids' => [],
        'started_at' => now()->toIso8601String(),
    ], now()->addHour());

    Sanctum::actingAs($user, ['upload-chunks']);

    $response = $this->postJson('/api/files/chunked/complete', [
        'upload_session_id' => $uploadSessionId,
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('code', 'duplicate_filename')
        ->assertJsonPath('folder_id', $folder->id)
        ->assertJsonPath('suggested_name', 'JCMA-01-2024 (1)');

    expect(File::query()->where('folder_id', $folder->id)->count())->toBe(1);
});
