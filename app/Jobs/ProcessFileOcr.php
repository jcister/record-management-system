<?php

namespace App\Jobs;

use App\Models\FileVersion;
use App\Services\AuditService;
use App\Services\SsePublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ProcessFileOcr implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 2;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    private ?string $fileId = null;

    public function __construct(public string $fileVersionId)
    {
        $this->queue = 'ocr';
    }

    public function handle(AuditService $audit, SsePublisher $sse): void
    {
        $fileVersion = FileVersion::query()
            ->with('file')
            ->find($this->fileVersionId);

        if ($fileVersion === null || $fileVersion->file === null) {
            return;
        }

        $file = $fileVersion->file;
        $this->fileId = (string) $file->id;

        $this->syncOcrState($fileVersion, [
            'ocr_status' => 'processing',
            'ocr_error' => null,
        ]);

        $sse->publishFileUpdate($this->fileId, 'ocr_processing');

        $disk = config('filesystems.default', 'local');
        $path = Storage::disk($disk)->path($fileVersion->file_path);

        if (! is_file($path)) {
            $this->syncOcrState($fileVersion, [
                'ocr_status' => 'failed',
                'ocr_error' => $this->truncateOcrError("File not found at {$path}"),
            ]);

            return;
        }

        $language = null;
        try {
            $language = $this->resolveLanguage((string) ($fileVersion->ocr_language ?? $file->ocr_language));

            $text = $this->performOcr($fileVersion, $path, $language);

            $this->syncOcrState($fileVersion, [
                'ocr_text' => $text,
                'ocr_status' => 'completed',
                'ocr_error' => null,
            ]);

            $sse->publishFileUpdate($this->fileId, 'ocr_completed');

            $audit->log(
                'file_ocr_completed',
                'OCR completed.',
                $file,
                [
                    'file_version_id' => $fileVersion->id,
                    'text_length' => strlen($text),
                    'language' => $language,
                ],
                null,
                AuditService::SYSTEM_ACTOR_NAME
            );
        } catch (\Throwable $exception) {
            Log::warning('OCR processing failed', [
                'file_id' => $file->id,
                'file_version_id' => $fileVersion->id,
                'message' => $exception->getMessage(),
                'language' => $fileVersion->ocr_language ?? $file->ocr_language,
                'resolved_language' => $language ?? null,
                'stderr' => $this->extractProcessError($exception),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
            ]);

            if ($this->attempts() < $this->tries) {
                $this->syncOcrState($fileVersion, [
                    'ocr_status' => 'pending',
                    'ocr_error' => $this->truncateOcrError(sprintf(
                        'OCR attempt %d/%d failed. Retrying. Last error: %s',
                        $this->attempts(),
                        $this->tries,
                        $this->buildErrorMessage($exception)
                    )),
                ]);
            }

            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $fileVersion = FileVersion::query()
            ->with('file')
            ->find($this->fileVersionId);

        if ($fileVersion === null || $fileVersion->file === null) {
            return;
        }

        $file = $fileVersion->file;
        $this->fileId = (string) $file->id;

        $this->syncOcrState($fileVersion, [
            'ocr_status' => 'failed',
            'ocr_error' => $this->truncateOcrError($this->buildErrorMessage($exception)),
        ]);

        app(SsePublisher::class)->publishFileUpdate($this->fileId, 'ocr_failed');

        app(AuditService::class)->log(
            'file_ocr_failed',
            'OCR failed.',
            $file,
            [
                'file_version_id' => $fileVersion->id,
                'error' => $this->truncateForAudit($exception->getMessage()),
                'language' => $fileVersion->ocr_language ?? $file->ocr_language,
            ],
            null,
            AuditService::SYSTEM_ACTOR_NAME
        );
    }

    private function syncOcrState(FileVersion $version, array $attributes): void
    {
        $version->fill($attributes)->save();

        $file = $version->file;

        if ($file !== null && $file->current_version_id === $version->id) {
            $file->fill($attributes)->save();
        }
    }

    private function performOcr(FileVersion $file, string $path, string $language): string
    {
        $extension = $this->getFileExtension($file);
        $mimeType = strtolower($file->mime_type ?? '');

        if ($this->isPdf($file)) {
            return $this->runOcrForPdf($path, $language);
        }

        if ($this->isImageFile($extension, $mimeType)) {
            return $this->runOcrForImage($path, $language, $extension);
        }

        throw new \RuntimeException('Unsupported file format. Only PDF and image files can be processed for OCR.');
    }

    private function runOcrForImage(string $path, string $language, string $extension = ''): string
    {
        $preparedPath = $this->prepareImageForOcr($path, $extension);

        try {
            return trim($this->runTesseractWithFallback($preparedPath, $language));
        } finally {
            $this->cleanupPreparedImage($preparedPath, $path);
        }
    }

    private function isImageFile(string $extension, string $mimeType): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'tiff', 'tif', 'bmp', 'webp', 'heic', 'heif'];

        return in_array($extension, $imageExtensions, true)
            || str_starts_with($mimeType, 'image/');
    }

    private function getFileExtension(FileVersion $file): string
    {
        $filename = $file->file_name ?? '';

        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    private function isPdf(FileVersion $file): bool
    {
        $mime = $file->mime_type ?? '';
        $filename = $file->file_name ?? '';

        return str_contains(strtolower($mime), 'pdf') || str_ends_with(strtolower($filename), '.pdf');
    }

    private function runOcrForPdf(string $path, string $language): string
    {
        $tempDir = 'ocr/'.($this->fileId ?? 'unknown');

        Storage::disk('local')->deleteDirectory($tempDir);
        Storage::disk('local')->makeDirectory($tempDir);

        try {
            $outputPrefix = Storage::disk('local')->path($tempDir.'/page');

            $convert = new Process(['pdftoppm', '-png', '-r', (string) $this->pdfRasterDpi(), $path, $outputPrefix]);
            $convert->setTimeout($this->pdfRenderTimeout());
            $convert->mustRun();

            $images = glob(Storage::disk('local')->path($tempDir.'/page-*.png')) ?: [];
            sort($images);

            if ($images === [] || $images === false) {
                throw new \RuntimeException('PDF conversion produced no images for OCR.');
            }

            $pages = [];

            foreach ($images as $image) {
                $pages[] = $this->runOcrForImage($image, $language, 'png');
            }

            return trim(implode("\n\n", array_filter($pages)));
        } finally {
            Storage::disk('local')->deleteDirectory($tempDir);
        }
    }

    private function runTesseractWithFallback(string $path, string $language): string
    {
        $modes = $this->ocrPsmModes();

        foreach ($modes as $psm) {
            $output = trim($this->runTesseract($path, $language, $psm));

            if ($output !== '') {
                return $output;
            }
        }

        return '';
    }

    private function runTesseract(string $path, string $language, ?int $psm = null): string
    {
        $command = ['tesseract', $path, 'stdout', '-l', $language, '--oem', (string) $this->ocrEngineMode()];

        if ($psm !== null) {
            $command[] = '--psm';
            $command[] = (string) $psm;
        }

        $process = new Process($command);
        $process->setTimeout($this->ocrProcessTimeout());
        $process->mustRun();

        return $process->getOutput();
    }

    private function resolveLanguage(?string $preferred): string
    {
        $requested = $preferred !== null && $preferred !== '' ? $preferred : $this->defaultOcrLanguage();
        $supported = $this->listTesseractLanguages();

        if ($supported === null) {
            return $requested;
        }

        $requestedParts = array_filter(explode('+', $requested));
        $available = [];

        foreach ($requestedParts as $part) {
            if (in_array($part, $supported, true)) {
                $available[] = $part;
            }
        }

        if (count($available) === count($requestedParts) && $available !== []) {
            return implode('+', $available);
        }

        if (in_array('eng', $supported, true)) {
            return 'eng';
        }

        return $supported[0] ?? 'eng';
    }

    private function prepareImageForOcr(string $path, string $extension = ''): string
    {
        if (! $this->isPreprocessingEnabled()) {
            return $path;
        }

        if (! extension_loaded('gd') || ! function_exists('imagefilter')) {
            return $path;
        }

        $normalizedExtension = $extension !== ''
            ? strtolower($extension)
            : strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $image = $this->createImageResource($path, $normalizedExtension);

        if (! $image instanceof \GdImage) {
            return $path;
        }

        try {
            imagefilter($image, IMG_FILTER_GRAYSCALE);
            imagefilter($image, IMG_FILTER_CONTRAST, -20);

            $minimumWidth = $this->preprocessMinimumWidth();
            if ($minimumWidth > 0) {
                $image = $this->resizeImageForOcr($image, $minimumWidth);
            }

            $temporaryPath = $this->createTemporaryImagePath();

            if ($temporaryPath === null) {
                return $path;
            }

            if (! imagepng($image, $temporaryPath, 0)) {
                @unlink($temporaryPath);

                return $path;
            }

            return $temporaryPath;
        } finally {
            imagedestroy($image);
        }
    }

    private function resizeImageForOcr(\GdImage $image, int $minimumWidth): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= 0 || $height <= 0 || $width >= $minimumWidth) {
            return $image;
        }

        $targetHeight = max(1, (int) round(($height / $width) * $minimumWidth));
        $scaled = imagescale($image, $minimumWidth, $targetHeight, IMG_BILINEAR_FIXED);

        if ($scaled instanceof \GdImage) {
            imagedestroy($image);

            return $scaled;
        }

        return $image;
    }

    private function createImageResource(string $path, string $extension): ?\GdImage
    {
        $image = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : false,
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if ($image instanceof \GdImage) {
            return $image;
        }

        return null;
    }

    private function createTemporaryImagePath(): ?string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'ocr-pre-');

        if ($temporaryFile === false) {
            return null;
        }

        @unlink($temporaryFile);

        return $temporaryFile.'.png';
    }

    private function cleanupPreparedImage(string $preparedPath, string $originalPath): void
    {
        if ($preparedPath === $originalPath) {
            return;
        }

        if (is_file($preparedPath)) {
            @unlink($preparedPath);
        }
    }

    private function defaultOcrLanguage(): string
    {
        $language = trim((string) config('services.ocr.default_language', 'eng+fil'));

        return $language !== '' ? $language : 'eng+fil';
    }

    private function ocrProcessTimeout(): int
    {
        return max(30, (int) config('services.ocr.process_timeout', 300));
    }

    private function pdfRenderTimeout(): int
    {
        return max(30, (int) config('services.ocr.pdf_render_timeout', 300));
    }

    private function pdfRasterDpi(): int
    {
        $dpi = (int) config('services.ocr.pdf_dpi', 300);

        return max(72, min(600, $dpi));
    }

    private function ocrEngineMode(): int
    {
        $oem = (int) config('services.ocr.oem', 3);

        if (! in_array($oem, [0, 1, 2, 3], true)) {
            return 3;
        }

        return $oem;
    }

    /**
     * @return list<int>
     */
    private function ocrPsmModes(): array
    {
        $primary = $this->normalizePsm((int) config('services.ocr.psm', 3));
        $fallbackModes = $this->parsePsmList((string) config('services.ocr.fallback_psm', '6,11'));

        $modes = array_values(array_unique(array_filter([
            $primary,
            ...$fallbackModes,
        ], static fn (?int $mode): bool => $mode !== null)));

        return $modes === [] ? [3] : $modes;
    }

    /**
     * @return list<int>
     */
    private function parsePsmList(string $value): array
    {
        $segments = array_map('trim', explode(',', $value));
        $modes = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $mode = $this->normalizePsm((int) $segment);

            if ($mode !== null) {
                $modes[] = $mode;
            }
        }

        return array_values(array_unique($modes));
    }

    private function normalizePsm(int $mode): ?int
    {
        if ($mode < 0 || $mode > 13) {
            return null;
        }

        return $mode;
    }

    private function isPreprocessingEnabled(): bool
    {
        return (bool) config('services.ocr.preprocess_enabled', true);
    }

    private function preprocessMinimumWidth(): int
    {
        return max(0, (int) config('services.ocr.preprocess_min_width', 1800));
    }

    private function listTesseractLanguages(): ?array
    {
        static $cached;

        if ($cached !== null) {
            return $cached;
        }

        try {
            $process = new Process(['tesseract', '--list-langs']);
            $process->setTimeout(30);
            $process->mustRun();

            $lines = array_values(array_filter(array_map('trim', explode("\n", $process->getOutput()))));
            $languages = array_values(array_filter($lines, static fn (string $lang): bool => ! str_starts_with($lang, 'List of available languages')));

            return $cached = $languages;
        } catch (\Throwable $exception) {
            Log::info('Unable to list tesseract languages', [
                'message' => $exception->getMessage(),
            ]);

            return $cached = null;
        }
    }

    private function extractProcessError(\Throwable $exception): ?string
    {
        if ($exception instanceof ProcessFailedException) {
            $stderr = trim($exception->getProcess()->getErrorOutput());

            if ($stderr !== '') {
                return $stderr;
            }
        }

        return null;
    }

    private function buildErrorMessage(\Throwable $exception): string
    {
        $stderr = $this->extractProcessError($exception);

        if ($stderr !== null) {
            return $stderr;
        }

        return $exception->getMessage();
    }

    private function truncateForAudit(string $message, int $maxLength = 200): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 3).'...';
    }

    private function truncateOcrError(string $message, int $maxLength = 500): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 3).'...';
    }
}
