<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\AuditService;
use App\Support\FileName;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileViewerController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function view(File $file): StreamedResponse
    {
        $disk = $this->resolveFileDisk($file);

        if (! $disk->exists($file->file_path)) {
            abort(404, 'File not found in storage');
        }

        $user = Auth::user();

        $this->audit->log(
            'file_viewed',
            ($user?->name ?? AuditService::SYSTEM_ACTOR_NAME).' opened "'.$file->displayName().'".',
            $file,
            ['downloaded_at' => now()]
        );

        $filename = FileName::withPathExtension($file->file_name, $file->file_path, $file->mime_type);
        $contentType = FileName::resolveMimeType($file->mime_type, $file->file_path);

        return response()->stream(
            function () use ($disk, $file): void {
                $stream = $disk->readStream($file->file_path);
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $contentType,
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    $filename
                ),
                'Content-Length' => $disk->size($file->file_path),
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }

    public function download(File $file): StreamedResponse
    {
        $disk = $this->resolveFileDisk($file);

        if (! $disk->exists($file->file_path)) {
            abort(404, 'File not found in storage');
        }

        $user = Auth::user();

        $this->audit->log(
            'file_downloaded',
            ($user?->name ?? AuditService::SYSTEM_ACTOR_NAME).' downloaded "'.$file->displayName().'".',
            $file,
            ['downloaded_at' => now()]
        );

        $filename = FileName::withPathExtension($file->file_name, $file->file_path, $file->mime_type);
        $contentType = FileName::resolveMimeType($file->mime_type, $file->file_path);

        return response()->stream(
            function () use ($disk, $file): void {
                $stream = $disk->readStream($file->file_path);
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $contentType,
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    $filename
                ),
                'Content-Length' => $disk->size($file->file_path),
            ]
        );
    }

    private function resolveFileDisk(File $file): Filesystem
    {
        $diskNames = array_values(array_unique(array_filter([
            config('filesystems.default', 'local'),
            'records',
            'local',
        ])));

        $fallbackDisk = $diskNames[0] ?? 'local';

        foreach ($diskNames as $diskName) {
            $disk = Storage::disk($diskName);

            if ($disk->exists($file->file_path)) {
                return $disk;
            }
        }

        return Storage::disk($fallbackDisk);
    }
}
