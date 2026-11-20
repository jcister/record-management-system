<?php

namespace App\Concerns;

trait NormalizesSearchTerm
{
    protected function normalizeSearchTerm(string $search): string
    {
        $cleaned = preg_replace('/[^A-Za-z0-9\s]/', ' ', $search) ?? '';

        return trim(preg_replace('/\s+/', ' ', $cleaned) ?? '');
    }
}
