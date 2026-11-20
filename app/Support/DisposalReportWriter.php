<?php

namespace App\Support;

use App\Models\DisposalBatch;
use App\Models\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

class DisposalReportWriter
{
    public const DIRECTORY = 'disposal-reports';

    /**
     * @param  Collection<int, File>  $files
     */
    public function write(DisposalBatch $batch, Collection $files, string $stage): string
    {
        $fileName = Str::of($batch->reference)
            ->slug('-')
            ->append('-', $stage, '.json')
            ->toString();

        $relativePath = self::DIRECTORY.'/'.$fileName;

        $payload = [
            'batch' => [
                'id' => (string) $batch->id,
                'reference' => $batch->reference,
                'status' => $batch->status,
                'stage' => $stage,
                'file_count' => $files->count(),
                'generated_at' => now()->toIso8601String(),
            ],
            'files' => $files->map(fn (File $file) => [
                'id' => (string) $file->id,
                'record_id' => (string) $file->record_id,
                'name' => $file->displayName(),
                'retention_status' => $file->retention_status,
                'retention_expires_at' => $file->retention_expires_at?->toDateString(),
                'physical_location' => $file->physical_location,
                'physical_tags' => $file->physicalLocationTags
                    ->map(fn ($tag) => [
                        'id' => (int) $tag->id,
                        'name' => $tag->name,
                    ])
                    ->values()
                    ->all(),
            ])->values()->all(),
        ];

        $json = $this->encode($payload);

        Storage::disk('local')->put($relativePath, $json);

        return $relativePath;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return json_encode([
                'error' => 'Unable to encode disposal batch payload',
                'message' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }
}
