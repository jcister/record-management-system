<?php

namespace App\Support;

class RecordTypeHelper
{
    /**
     * Mime types that qualify as a "Record" (PDF or image).
     *
     * @var list<string>
     */
    private static array $recordMimePrefixes = [
        'application/pdf',
        'image/',
    ];

    /**
     * File extensions that qualify as a "Record" (PDF or image).
     *
     * @var list<string>
     */
    private static array $recordExtensions = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'gif',
        'bmp',
        'webp',
        'heic',
        'tiff',
        'tif',
        'svg',
    ];

    /**
     * Whether the given mime type and extension represent a file that should become a Record.
     * Only PDF and image types return true.
     */
    public static function isRecordType(string $mimeType, string $extension): bool
    {
        $mime = strtolower(trim($mimeType));
        $ext = strtolower(trim($extension));

        foreach (self::$recordMimePrefixes as $prefix) {
            if (str_starts_with($mime, $prefix) || $mime === $prefix) {
                return true;
            }
        }

        return in_array($ext, self::$recordExtensions, true);
    }
}
