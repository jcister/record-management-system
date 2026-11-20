<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ValidOcrContent implements ValidationRule
{
    /**
     * Run the validation rule.
     * Only PDF and image files are supported for OCR.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $path = $value->getRealPath();

        if ($path === false || ! is_file($path)) {
            $fail('The :attribute file could not be read.');

            return;
        }

        $supportedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif', 'bmp', 'webp', 'heic', 'heif'];

        if (! in_array($extension, $supportedExtensions, true)) {
            $fail('The :attribute file type is not supported. Only PDF and image files can be processed.');

            return;
        }

        try {
            switch ($extension) {
                case 'pdf':
                    if (! $this->isValidPdf($path)) {
                        $fail('This PDF file is corrupted or invalid.');
                    }
                    break;

                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'tiff':
                case 'tif':
                case 'bmp':
                case 'webp':
                case 'heic':
                case 'heif':
                    if (! $this->isValidImage($path)) {
                        $fail('This image file is corrupted or unreadable.');
                    }
                    break;
            }
        } catch (\Throwable $exception) {
            $fail('The :attribute could not be processed.');
        }
    }

    /**
     * Quick sanity check for PDF headers
     */
    private function isValidPdf(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        return str_starts_with($header, '%PDF');
    }

    /**
     * Validate image file using getimagesize
     */
    private function isValidImage(string $path): bool
    {
        $imageInfo = @getimagesize($path);

        return $imageInfo !== false;
    }
}
