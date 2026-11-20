<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Folder;
use App\Services\AuditService;
use App\Support\FileName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DownloadController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function store(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'string'],
            'items.*.type' => ['required', 'in:folder,file'],
        ]);

        $items = $validated['items'];

        // If a single file is requested, stream it directly (fallback safety)
        if (count($items) === 1 && $items[0]['type'] === 'file') {
            $file = File::query()->findOrFail($items[0]['id']);
            $this->assertFileExists($file);

            $downloadName = FileName::withPathExtension($file->file_name, $file->file_path, $file->mime_type);

            return response()->download(
                Storage::disk('records')->path($file->file_path),
                $downloadName
            );
        }

        $zipPath = $this->makeTempZipPath();
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to create download archive');
        }

        foreach ($items as $item) {
            if ($item['type'] === 'file') {
                $file = File::query()->findOrFail($item['id']);
                $this->assertFileExists($file);

                $this->addFileToZip($zip, $file, $file->file_name);

                continue;
            }

            $folder = Folder::query()->findOrFail($item['id']);
            $this->addFolderToZip($zip, $folder);
        }

        $zip->close();

        $downloadName = $this->determineFilename($items);

        $this->audit->log(
            'bulk_download',
            ($request->user()?->name ?? AuditService::SYSTEM_ACTOR_NAME).' downloaded '.count($items).' item(s) as "'.$downloadName.'".',
            null,
            ['item_count' => count($items), 'archive_name' => $downloadName]
        );

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }

    private function assertFileExists(File $file): void
    {
        if (! Storage::disk('records')->exists($file->file_path)) {
            abort(404, 'File not found in storage');
        }
    }

    private function addFileToZip(ZipArchive $zip, File $file, string $zipPath): void
    {
        // Construct filename with extension from file_path or MIME type
        $filename = FileName::withPathExtension($file->file_name, $file->file_path, $file->mime_type);

        // Preserve directory structure in zipPath but use proper filename
        $pathParts = pathinfo($zipPath);
        $zipPathWithExtension = $pathParts['dirname'] !== '.'
            ? $pathParts['dirname'].'/'.$filename
            : $filename;

        $zip->addFile(
            Storage::disk('records')->path($file->file_path),
            $zipPathWithExtension
        );
    }

    private function addFolderToZip(ZipArchive $zip, Folder $folder, string $prefix = ''): void
    {
        $folder->loadMissing(['files', 'children']);

        $dirName = ltrim($prefix === '' ? $folder->name : $prefix.'/'.$folder->name, '/');
        $zip->addEmptyDir($dirName);

        foreach ($folder->files as $file) {
            $this->assertFileExists($file);
            $this->addFileToZip($zip, $file, $dirName.'/'.$file->file_name);
        }

        foreach ($folder->children as $child) {
            $this->addFolderToZip($zip, $child, $dirName);
        }
    }

    private function makeTempZipPath(): string
    {
        $tmpDir = storage_path('app/tmp');

        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpFile = tempnam($tmpDir, 'download_');

        if ($tmpFile === false) {
            abort(500, 'Unable to create temporary archive');
        }

        $zipPath = $tmpFile.'.zip';
        rename($tmpFile, $zipPath);

        return $zipPath;
    }

    /**
     * @param  array<int, array{id: string, type: string}>  $items
     */
    private function determineFilename(array $items): string
    {
        if (count($items) === 1 && $items[0]['type'] === 'folder') {
            $folder = Folder::query()->findOrFail($items[0]['id']);

            return ($folder->name ?: 'folder').'.zip';
        }

        $timestamp = now()->format('Y-m-d_H-i-s');

        return "ched-onse-kmis-download-{$timestamp}.zip";
    }
}
