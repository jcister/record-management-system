<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class RetentionStatusCalculator
{
    public static function determine(null|string|CarbonInterface $retentionExpiresAt): string
    {
        $date = self::normalizeDate($retentionExpiresAt);

        if ($date === null) {
            return RetentionStatus::ACTIVE;
        }

        return $date->endOfDay()->isPast()
            ? RetentionStatus::READY_FOR_DISPOSAL
            : RetentionStatus::ACTIVE;
    }

    private static function normalizeDate(null|string|CarbonInterface $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
