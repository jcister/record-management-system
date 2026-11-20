<?php

namespace App\Jobs;

use App\Models\File;
use App\Models\FileVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class GenerateFileThumbnail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(public string $fileVersionId) {}

    public function handle(): void
    {
        $version = FileVersion::query()
            ->with('file')
            ->find($this->fileVersionId);

        if ($version === null || $version->file === null) {
            return;
        }

        if (! $this->isPdf($version)) {
            return;
        }

        try {
            $file = $version->file;
            $sourceDisk = $this->resolveFileDisk($file);

            if (! $sourceDisk->exists($version->file_path)) {
                Log::warning('Thumbnail generation skipped: source file missing', [
                    'file_id' => $file->id,
                    'file_version_id' => $version->id,
                    'path' => $version->file_path,
                ]);

                return;
            }

            $sourcePath = $sourceDisk->path($version->file_path);

            $pngPath = $this->renderFirstPageToPng($sourcePath, $version->id);

            $targetDiskName = config('filesystems.default', 'local');
            $targetDisk = Storage::disk($targetDiskName);
            $thumbnailDir = 'thumbnails/'.$file->id;
            $targetDisk->makeDirectory($thumbnailDir);

            $webpPath = $thumbnailDir.'/'.$version->id.'.webp';
            $jpegPath = $thumbnailDir.'/'.$version->id.'.jpg';

            $webpSaved = $this->writeThumbnail($pngPath, $targetDiskName, $webpPath, 'webp');
            $jpegSaved = $this->writeThumbnail($pngPath, $targetDiskName, $jpegPath, 'jpeg');

            $version->forceFill([
                'thumbnail_disk' => $targetDiskName,
                'thumbnail_path_webp' => $webpSaved ? $webpPath : null,
                'thumbnail_path_jpeg' => $jpegSaved ? $jpegPath : null,
                'thumbnail_generated_at' => now(),
            ])->save();

            Storage::disk('local')->deleteDirectory('thumbnails/tmp/'.$version->id);
        } catch (\Throwable $exception) {
            Log::error('Thumbnail generation failed', [
                'file_version_id' => $version->id,
                'file_id' => $version->file_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function renderFirstPageToPng(string $absoluteSourcePath, string $versionId): string
    {
        $tempDir = 'thumbnails/tmp/'.$versionId;
        $localDisk = Storage::disk('local');

        $localDisk->deleteDirectory($tempDir);
        $localDisk->makeDirectory($tempDir);

        $outputPrefix = $localDisk->path($tempDir.'/thumb');

        $process = new Process([
            'pdftoppm',
            '-png',
            '-f', '1',
            '-l', '1',
            '-singlefile',
            '-scale-to-x', '800',
            '-scale-to-y', '-1',
            $absoluteSourcePath,
            $outputPrefix,
        ]);

        $process->setTimeout(120);
        $process->mustRun();

        $pngPath = $outputPrefix.'.png';

        if (! is_file($pngPath)) {
            throw new \RuntimeException('PDF to PNG conversion did not produce an output file.');
        }

        return $pngPath;
    }

    private function writeThumbnail(string $pngPath, string $diskName, string $destinationPath, string $format): bool
    {
        if (! function_exists('imagecreatefrompng')) {
            return false;
        }

        $sourceImage = imagecreatefrompng($pngPath);

        if ($sourceImage === false) {
            return false;
        }

        $resized = $this->resizeToWidth($sourceImage, 500);
        imagedestroy($sourceImage);

        if ($resized === null) {
            return false;
        }

        ob_start();

        $writeResult = match ($format) {
            'webp' => function_exists('imagewebp') ? imagewebp($resized, null, 82) : false,
            'jpeg' => imagejpeg($resized, null, 85),
            default => false,
        };

        $binary = ob_get_clean();
        imagedestroy($resized);

        if ($writeResult !== true || $binary === false) {
            return false;
        }

        return Storage::disk($diskName)->put($destinationPath, $binary);
    }

    private function resizeToWidth(\GdImage $image, int $targetWidth): ?\GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $targetWidth = min($targetWidth, $width);
        $targetHeight = (int) round($height * ($targetWidth / $width));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            return null;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $copied = imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        if (! $copied) {
            imagedestroy($canvas);

            return null;
        }

        return $canvas;
    }

    private function isPdf(FileVersion $file): bool
    {
        $mime = $file->mime_type ?? '';
        $filename = $file->file_name ?? '';

        return str_contains(strtolower($mime), 'pdf') || str_ends_with(strtolower($filename), '.pdf');
    }

    private function resolveFileDisk(File $file): FilesystemAdapter
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
