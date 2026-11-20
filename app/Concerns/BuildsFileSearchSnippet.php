<?php

namespace App\Concerns;

use App\Models\File;

trait BuildsFileSearchSnippet
{
    protected function buildFileSearchSnippet(File $file, string $search): string
    {
        $haystack = $file->ocr_text ?: ($file->remarks ?? '');

        if ($haystack === '' || $search === '') {
            return '';
        }

        $position = stripos($haystack, $search);

        if ($position === false) {
            return mb_substr($haystack, 0, 160);
        }

        $start = max(0, $position - 60);

        return mb_substr($haystack, $start, 160);
    }
}
