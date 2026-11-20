<?php

namespace App\Support;

class FileName
{
    public static function normalize(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $condensed = preg_replace('/\s+/', ' ', $name);
        $trimmed = trim($condensed ?? '');

        return $trimmed === '' ? '' : $trimmed;
    }

    public static function withoutExtension(?string $name): ?string
    {
        $normalized = self::normalize($name);

        if ($normalized === null || $normalized === '') {
            return $normalized;
        }

        $lastDot = strrpos($normalized, '.');

        if ($lastDot === false || $lastDot === 0) {
            return $normalized;
        }

        return substr($normalized, 0, $lastDot);
    }

    public static function withPathExtension(
        ?string $name,
        ?string $path,
        ?string $mimeType = null,
        string $fallback = 'file'
    ): string {
        $normalized = self::normalize($name) ?? '';
        $base = $normalized === '' ? $fallback : $normalized;

        $extension = '';

        // 1. Try to get extension from path.
        // Only accept segments that look like real extensions (alphanumeric,
        // contains at least one letter, max 8 chars). This rejects dotted
        // numeric suffixes like "-2025" that pathinfo() would otherwise return
        // for names such as "CMO-NO.-4-S.-2025".
        if ($path !== null && $path !== '') {
            $rawExt = pathinfo($path, PATHINFO_EXTENSION) ?: '';
            if ($rawExt !== '' && preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9]{1,8}$/', $rawExt)) {
                $extension = $rawExt;
            }
        }

        // 2. Fallback to MIME type if no extension in path
        if ($extension === '' && $mimeType !== null) {
            $extension = self::guessExtensionFromMime($mimeType);
        }

        $normalizedExtension = strtolower(ltrim((string) $extension, '.'));

        if ($normalizedExtension === '') {
            return $base;
        }

        if (str_ends_with(strtolower($base), '.'.$normalizedExtension)) {
            return $base;
        }

        return $base.'.'.$normalizedExtension;
    }

    /**
     * Resolve the best Content-Type for a file.
     *
     * 1. Returns the stored MIME type if non-empty.
     * 2. Falls back to guessing from the path extension (validated — rejects
     *    pseudo-extensions like "-2025").
     * 3. Defaults to "application/octet-stream".
     */
    public static function resolveMimeType(?string $mimeType, ?string $path): string
    {
        if ($mimeType !== null && $mimeType !== '') {
            return $mimeType;
        }

        if ($path !== null && $path !== '') {
            $rawExt = pathinfo($path, PATHINFO_EXTENSION) ?: '';
            if ($rawExt !== '' && preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9]{1,8}$/', $rawExt)) {
                $mime = self::guessMimeFromExtension(strtolower($rawExt));
                if ($mime !== '') {
                    return $mime;
                }
            }
        }

        return 'application/octet-stream';
    }

    private static function guessExtensionFromMime(?string $mimeType): string
    {
        $mime = strtolower((string) $mimeType);

        return match ($mime) {
            'application/pdf', 'application/x-pdf' => 'pdf',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/tiff' => 'tiff',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
            'image/heif', 'image/heic' => 'heic',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => '',
        };
    }

    private static function guessMimeFromExtension(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'tiff', 'tif' => 'image/tiff',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'heic', 'heif' => 'image/heif',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => '',
        };
    }
}
