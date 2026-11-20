<?php

namespace App\Support;

use Carbon\Carbon;

class DateInput
{
    public static function normalize(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        // Already in ISO format (YYYY-MM-DD)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmed)) {
            return $trimmed;
        }

        // Match MM/DD/YY (2-digit year) or MM/DD/YYYY (4-digit year)
        if (! preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})$/', $trimmed, $matches)) {
            return null;
        }

        $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $year = (int) $matches[3];

        // Only convert 2-digit years to 4-digit
        if ($year < 50) {
            $year += 2000;
        } elseif ($year < 100) {
            $year += 1900;
        }
        // 4-digit years are used as-is

        $iso = sprintf('%04d-%02d-%02d', $year, (int) $month, (int) $day);

        try {
            Carbon::parse($iso);

            return $iso;
        } catch (\Throwable) {
            return null;
        }
    }
}
