<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ValidUploadedFile implements ValidationRule
{
    /**
     * Run the validation rule. Accepts any file type; only checks readability.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $path = $value->getRealPath();

        if ($path === false || ! is_file($path)) {
            $fail('The :attribute file could not be read.');

            return;
        }
    }
}
