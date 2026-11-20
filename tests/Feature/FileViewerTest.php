<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

function extractedDownloadFilename(TestResponse $response): ?string
{
    $header = (string) $response->headers->get('Content-Disposition', '');

    if (preg_match('/filename="?([^\";]+)"?/i', $header, $matches) === 1) {
        return $matches[1];
    }

    return null;
}

beforeEach(function () {
    Storage::fake('records');
});

it('can view a file with inline content disposition', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    // Create a test file
    Storage::disk('records')->put('drafts/test.txt', 'Test file content');

    $file = File::factory()->create([
        'folder_id' => $folder->id,
        'uploaded_by' => $user->id,
        'file_path' => 'drafts/test.txt',
        'mime_type' => 'text/plain',
        'file_name' => 'test',
    ]);

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/view");

    $response->assertSuccessful();
    expect(extractedDownloadFilename($response))->toBe('test.txt');
    expect($response->headers->get('Content-Type'))->toStartWith('text/plain');
});

it('can download a file with attachment content disposition', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    // Create a test file
    Storage::disk('records')->put('drafts/test.pdf', 'PDF content');

    $file = File::factory()->create([
        'folder_id' => $folder->id,
        'uploaded_by' => $user->id,
        'file_path' => 'drafts/test.pdf',
        'mime_type' => 'application/pdf',
        'file_name' => 'document',
    ]);

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/download");

    $response->assertSuccessful();
    expect(extractedDownloadFilename($response))->toBe('document.pdf');
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('preserves a double-dot filename during download', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    Storage::disk('records')->put('drafts/cmo.pdf', 'PDF content');

    $file = File::factory()->create([
        'folder_id' => $folder->id,
        'uploaded_by' => $user->id,
        'file_path' => 'drafts/cmo.pdf',
        'mime_type' => 'application/pdf',
        'file_name' => 'CMO-NO.-4-S.-2025..pdf',
    ]);

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/download");

    $response->assertSuccessful();
    expect(extractedDownloadFilename($response))->toBe('CMO-NO.-4-S.-2025..pdf');
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('preserves a single-dot filename during download', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    Storage::disk('records')->put('drafts/cmo-single.pdf', 'PDF content');

    $file = File::factory()->create([
        'folder_id' => $folder->id,
        'uploaded_by' => $user->id,
        'file_path' => 'drafts/cmo-single.pdf',
        'mime_type' => 'application/pdf',
        'file_name' => 'CMO-NO.-4-S.-2025.pdf',
    ]);

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/download");

    $response->assertSuccessful();
    expect(extractedDownloadFilename($response))->toBe('CMO-NO.-4-S.-2025.pdf');
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('appends pdf extension when path contains a pseudo-extension like -2025', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    // Simulates a file assembled by the old chunked-upload bug where "-2025"
    // was mistaken for an extension and the real ".pdf" was never appended.
    Storage::disk('records')->put('private/uploads/abc_CMO-NO.-4-S..-2025', 'PDF content');

    $file = File::factory()->create([
        'folder_id' => $folder->id,
        'uploaded_by' => $user->id,
        'file_path' => 'private/uploads/abc_CMO-NO.-4-S..-2025',
        'mime_type' => 'application/pdf',
        'file_name' => 'CMO-NO.-4-S.-2025',
    ]);

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/download");

    $response->assertSuccessful();
    expect(extractedDownloadFilename($response))->toBe('CMO-NO.-4-S.-2025.pdf');
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('returns 404 when file does not exist in storage', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    $file = File::factory()->create([
        'folder_id' => $folder->id,
        'uploaded_by' => $user->id,
        'file_path' => 'drafts/nonexistent.txt',
    ]);

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/view");

    $response->assertNotFound();
});

it('logs file view to download logs', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    // Create a test file
    Storage::disk('records')->put('drafts/test.txt', 'Test content');

    $file = File::factory()->create([
        'folder_id' => $folder->id,
        'uploaded_by' => $user->id,
        'file_path' => 'drafts/test.txt',
    ]);

    $this->actingAs($user)->get("/api/files/{$file->id}/view");

    // Refresh to get the newly created downloads
    $file->refresh();

    expect($file->downloads)->toHaveCount(1);
    expect($file->downloads->first()->downloaded_at)->not->toBeNull();
});

it('can view a pdf file', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    Storage::disk('records')->put('drafts/test.pdf', 'PDF content');

    $file = File::factory()->create([
        'folder_id' => $folder->id,
        'uploaded_by' => $user->id,
        'file_path' => 'drafts/test.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/view");

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('can view an image file', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    Storage::disk('records')->put('drafts/test.jpg', 'Image content');

    $file = File::factory()->create([
        'folder_id' => $folder->id,
        'uploaded_by' => $user->id,
        'file_path' => 'drafts/test.jpg',
        'mime_type' => 'image/jpeg',
    ]);

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/view");

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'image/jpeg');
});

it('can view a soft-deleted file from the trash', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create();

    Storage::disk('records')->put('drafts/trashed.pdf', 'PDF content');

    $file = File::factory()->create([
        'folder_id' => $folder->id,
        'uploaded_by' => $user->id,
        'file_path' => 'drafts/trashed.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $file->delete();

    $response = $this->actingAs($user)->get(route('api.files.view', ['file' => $file->id]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});
