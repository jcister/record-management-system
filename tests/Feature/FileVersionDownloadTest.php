<?php

use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

function extractedVersionDownloadFilename(TestResponse $response): ?string
{
    $header = (string) $response->headers->get('Content-Disposition', '');

    if (preg_match('/filename="?([^\";]+)"?/i', $header, $matches) === 1) {
        return $matches[1];
    }

    return null;
}

beforeEach(function (): void {
    config()->set('filesystems.default', 'local');
    Storage::fake('local');
});

it('downloads a version as a pdf with a proper extension', function (): void {
    $user = User::factory()->create();

    $file = File::factory()->create([
        'uploaded_by' => $user->id,
        'file_name' => 'report',
        'file_path' => 'private/uploads/report.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $version = FileVersion::query()->create([
        'file_id' => $file->id,
        'version_number' => 1,
        'file_name' => 'report',
        'file_path' => 'private/uploads/report.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_by' => $user->id,
        'checksum' => null,
        'version_label' => 'v1',
        'change_summary' => null,
        'ocr_status' => 'completed',
        'ocr_text' => null,
        'ocr_language' => 'eng',
        'ocr_error' => null,
        'assembly_status' => 'completed',
    ]);

    $file->forceFill([
        'current_version_id' => $version->id,
        'latest_version_number' => 1,
    ])->save();

    Storage::disk('local')->put('private/uploads/report.pdf', 'PDF content');

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/versions/{$version->id}/download");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect(extractedVersionDownloadFilename($response))->toBe('report.pdf');
    expect($response->streamedContent())->toBe('PDF content');
});

it('preserves a double-dot version filename during download', function (): void {
    $user = User::factory()->create();

    $file = File::factory()->create([
        'uploaded_by' => $user->id,
        'file_name' => 'CMO-NO.-4-S.-2025..pdf',
        'file_path' => 'private/uploads/cmo.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $version = FileVersion::query()->create([
        'file_id' => $file->id,
        'version_number' => 1,
        'file_name' => 'CMO-NO.-4-S.-2025..pdf',
        'file_path' => 'private/uploads/cmo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_by' => $user->id,
        'checksum' => null,
        'version_label' => 'v1',
        'change_summary' => null,
        'ocr_status' => 'completed',
        'ocr_text' => null,
        'ocr_language' => 'eng',
        'ocr_error' => null,
        'assembly_status' => 'completed',
    ]);

    $file->forceFill([
        'current_version_id' => $version->id,
        'latest_version_number' => 1,
    ])->save();

    Storage::disk('local')->put('private/uploads/cmo.pdf', 'PDF content');

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/versions/{$version->id}/download");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect(extractedVersionDownloadFilename($response))->toBe('CMO-NO.-4-S.-2025..pdf');
    expect($response->streamedContent())->toBe('PDF content');
});

it('preserves a single-dot version filename during download', function (): void {
    $user = User::factory()->create();

    $file = File::factory()->create([
        'uploaded_by' => $user->id,
        'file_name' => 'CMO-NO.-4-S.-2025.pdf',
        'file_path' => 'private/uploads/cmo-single.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $version = FileVersion::query()->create([
        'file_id' => $file->id,
        'version_number' => 1,
        'file_name' => 'CMO-NO.-4-S.-2025.pdf',
        'file_path' => 'private/uploads/cmo-single.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_by' => $user->id,
        'checksum' => null,
        'version_label' => 'v1',
        'change_summary' => null,
        'ocr_status' => 'completed',
        'ocr_text' => null,
        'ocr_language' => 'eng',
        'ocr_error' => null,
        'assembly_status' => 'completed',
    ]);

    $file->forceFill([
        'current_version_id' => $version->id,
        'latest_version_number' => 1,
    ])->save();

    Storage::disk('local')->put('private/uploads/cmo-single.pdf', 'PDF content');

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/versions/{$version->id}/download");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect(extractedVersionDownloadFilename($response))->toBe('CMO-NO.-4-S.-2025.pdf');
    expect($response->streamedContent())->toBe('PDF content');
});

it('appends pdf extension for a version stored with a pseudo-extension path', function (): void {
    $user = User::factory()->create();

    // Simulates a version assembled by the old chunked-upload bug where "-2025"
    // was treated as the extension and the real ".pdf" was never appended.
    $file = File::factory()->create([
        'uploaded_by' => $user->id,
        'file_name' => 'CMO-NO.-4-S.-2025',
        'file_path' => 'private/uploads/abc_CMO-NO.-4-S..-2025',
        'mime_type' => 'application/pdf',
    ]);

    $version = FileVersion::query()->create([
        'file_id' => $file->id,
        'version_number' => 1,
        'file_name' => 'CMO-NO.-4-S.-2025',
        'file_path' => 'private/uploads/abc_CMO-NO.-4-S..-2025',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_by' => $user->id,
        'checksum' => null,
        'version_label' => 'v1',
        'change_summary' => null,
        'ocr_status' => 'completed',
        'ocr_text' => null,
        'ocr_language' => 'eng',
        'ocr_error' => null,
        'assembly_status' => 'completed',
    ]);

    $file->forceFill([
        'current_version_id' => $version->id,
        'latest_version_number' => 1,
    ])->save();

    Storage::disk('local')->put('private/uploads/abc_CMO-NO.-4-S..-2025', 'PDF content');

    $response = $this->actingAs($user)->get("/api/files/{$file->id}/versions/{$version->id}/download");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect(extractedVersionDownloadFilename($response))->toBe('CMO-NO.-4-S.-2025.pdf');
    expect($response->streamedContent())->toBe('PDF content');
});
