<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class RetentionSchedule
{
    public const DURATION_ONE_YEAR = 'one_year';

    public const DURATION_TEN_YEARS = 'ten_years';

    public const DURATION_PERPETUAL = 'perpetual';

    /**
     * Determine when a record should expire based on its release date and selected duration.
     */
    public static function expiresAt(?string $dateReleased, ?string $duration): ?string
    {
        if ($dateReleased === null || $duration === null || $duration === self::DURATION_PERPETUAL) {
            return null;
        }

        $years = self::yearsForDuration($duration);

        if ($years === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($dateReleased)->addYears($years)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get the number of years represented by a duration option.
     */
    public static function yearsForDuration(?string $duration): ?int
    {
        return match ($duration) {
            self::DURATION_ONE_YEAR => 1,
            self::DURATION_TEN_YEARS => 10,
            default => null,
        };
    }
}
