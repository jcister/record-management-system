<?php

use App\Jobs\ProcessFileOcr;
use App\Models\File;
use App\Models\FileVersion;
use Illuminate\Support\Facades\Queue;

function createProcessingVersionForRecovery(int $retryCount = 0): array
{
    $file = File::factory()->create([
        'ocr_status' => 'processing',
        'ocr_retry_count' => $retryCount,
    ]);

    $version = FileVersion::factory()->create([
        'file_id' => $file->id,
        'uploaded_by' => $file->uploaded_by,
        'ocr_status' => 'processing',
        'ocr_retry_count' => $retryCount,
        'ocr_error' => null,
    ]);

    $file->forceFill([
        'current_version_id' => $version->id,
    ])->save();

    FileVersion::query()
        ->whereKey($version->id)
        ->update(['updated_at' => now()->subMinutes(40)]);

    $version->refresh();
    $file->refresh();

    return [$file, $version];
}

it('re-queues stale OCR processing versions and increments retry count', function (): void {
    Queue::fake();

    [$file, $version] = createProcessingVersionForRecovery(1);

    $this->artisan('ocr:recover-stuck', [
        '--minutes' => 30,
        '--max-retries' => 3,
    ])->assertSuccessful();

    Queue::assertPushed(ProcessFileOcr::class, function (ProcessFileOcr $job) use ($version): bool {
        return $job->fileVersionId === (string) $version->id;
    });

    expect($version->refresh())
        ->ocr_status->toBe('pending')
        ->ocr_retry_count->toBe(2);

    expect($file->refresh())
        ->ocr_status->toBe('pending')
        ->ocr_retry_count->toBe(2);
});

it('marks stale OCR processing version as failed when retry limit is exhausted', function (): void {
    Queue::fake();

    [$file, $version] = createProcessingVersionForRecovery(3);

    $this->artisan('ocr:recover-stuck', [
        '--minutes' => 30,
        '--max-retries' => 3,
    ])->assertSuccessful();

    Queue::assertNotPushed(ProcessFileOcr::class);

    expect($version->refresh())
        ->ocr_status->toBe('failed')
        ->ocr_retry_count->toBe(3)
        ->ocr_error->toContain('retries exhausted');

    expect($file->refresh())
        ->ocr_status->toBe('failed')
        ->ocr_retry_count->toBe(3);
});

it('sets OCR status to failed when queued OCR retries are exhausted', function (): void {
    [$file, $version] = createProcessingVersionForRecovery();

    $job = new ProcessFileOcr((string) $version->id);
    $job->failed(new RuntimeException('Tesseract binary crashed'));

    expect($version->refresh())
        ->ocr_status->toBe('failed')
        ->ocr_error->toContain('Tesseract binary crashed');

    expect($file->refresh())
        ->ocr_status->toBe('failed')
        ->ocr_error->toContain('Tesseract binary crashed');
});
