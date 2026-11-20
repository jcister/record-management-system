<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\FileVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileThumbnailController extends Controller
{
    public function showCurrent(File $file, Request $request): StreamedResponse
    {
        $version = $file->currentVersion;

        if ($version === null) {
            abort(404, 'No current version available.');
        }

        return $this->streamThumbnail($version, $request);
    }

    public function showVersion(File $file, FileVersion $version, Request $request): StreamedResponse
    {
        if ($version->file_id !== $file->id) {
            abort(404, 'Version does not belong to this file.');
        }

        return $this->streamThumbnail($version, $request);
    }

    private function streamThumbnail(FileVersion $version, Request $request): StreamedResponse
    {
        $format = $this->determineFormat($version, $request->query('format'));

        if ($format === null) {
            abort(404, 'Thumbnail not available.');
        }

        $diskName = $version->thumbnail_disk ?? config('filesystems.default', 'local');
        $disk = Storage::disk($diskName);
        $path = $format === 'webp'
            ? $version->thumbnail_path_webp
            : $version->thumbnail_path_jpeg;

        if ($path === null || ! $disk->exists($path)) {
            abort(404, 'Thumbnail not found.');
        }

        $mime = $format === 'webp' ? 'image/webp' : 'image/jpeg';

        return response()->stream(
            function () use ($disk, $path): void {
                $stream = $disk->readStream($path);
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $mime,
                'Content-Length' => $disk->size($path),
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }

    private function determineFormat(FileVersion $version, ?string $preferred): ?string
    {
        $normalized = $preferred !== null ? strtolower($preferred) : null;

        if ($normalized === 'webp' && $version->thumbnail_path_webp !== null) {
            return 'webp';
        }

        if (($normalized === 'jpeg' || $normalized === 'jpg') && $version->thumbnail_path_jpeg !== null) {
            return 'jpeg';
        }

        if ($version->thumbnail_path_webp !== null) {
            return 'webp';
        }

        if ($version->thumbnail_path_jpeg !== null) {
            return 'jpeg';
        }

        return null;
    }
}
