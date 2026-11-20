<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\FileVersion;
use App\Services\AuditService;
use App\Services\FileVersionService;
use App\Support\FileName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileVersionController extends Controller
{
    public function __construct(private FileVersionService $fileVersionService, private AuditService $audit) {}

    public function index(File $file): JsonResponse
    {
        $versions = $file->versions()
            ->with('uploader')
            ->orderByDesc('version_number')
            ->get();

        return response()->json([
            'data' => $versions,
        ]);
    }

    public function download(File $file, FileVersion $version): StreamedResponse
    {
        $this->ensureVersionBelongsToFile($file, $version);

        $disk = config('filesystems.default', 'local');
        $path = ltrim($version->file_path, '/');

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $stream = Storage::disk($disk)->readStream($path);

        if ($stream === false || $stream === null) {
            abort(404);
        }

        $downloadFilename = basename($this->resolveDownloadFilename($version));
        $contentType = $this->resolveContentType($version);

        $this->audit->log(
            'file_version_downloaded',
            (auth()->user()?->name ?? AuditService::SYSTEM_ACTOR_NAME).' downloaded version '.$version->version_number.' of "'.$file->displayName().'".',
            $file,
            [
                'version_number' => $version->version_number,
                'file_size' => $version->file_size,
                'mime_type' => $version->mime_type,
            ]
        );

        return new StreamedResponse(function () use ($stream): void {
            while (! feof($stream)) {
                echo fread($stream, 8192);
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $downloadFilename
            ),
        ]);
    }

    public function restore(Request $request, File $file, FileVersion $version): JsonResponse
    {
        $this->ensureVersionBelongsToFile($file, $version);

        $changeSummary = $request->input('change_summary');

        $promotedVersion = $this->fileVersionService->promoteExistingVersion(
            $version,
            $request->user()?->id,
            $changeSummary
        );

        $this->audit->log(
            'file_version_restored',
            ($request->user()?->name ?? AuditService::SYSTEM_ACTOR_NAME).' restored version '.$version->version_number.' of "'.$file->displayName().'".',
            $file,
            [
                'version_number' => $version->version_number,
                'change_summary' => $changeSummary,
            ]
        );

        return response()->json([
            'message' => 'Version restored successfully.',
            'data' => $promotedVersion->load('uploader'),
        ]);
    }

    private function ensureVersionBelongsToFile(File $file, FileVersion $version): void
    {
        if ($version->file_id !== $file->id) {
            abort(404);
        }
    }

    /**
     * Build a safe filename for downloads, ensuring a PDF extension when appropriate.
     */
    private function resolveDownloadFilename(FileVersion $version): string
    {
        $baseName = trim((string) $version->file_name);
        $fallbackName = 'version-'.$version->version_number;

        if ($baseName === '') {
            $baseName = $fallbackName;
        }

        return FileName::withPathExtension(
            $baseName,
            (string) $version->file_path,
            $version->mime_type,
            $fallbackName
        );
    }

    private function resolveContentType(FileVersion $version): string
    {
        return FileName::resolveMimeType($version->mime_type, $version->file_path);
    }
}
