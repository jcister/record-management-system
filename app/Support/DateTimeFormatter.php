<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

class DateTimeFormatter
{
    /**
     * Format a datetime to ISO 8601 format in the application's timezone.
     *
     * Returns format: 2026-02-18T15:30:00+08:00 (for Manila time)
     */
    public static function toIso8601(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return Carbon::parse($date)
            ->timezone(config('app.timezone'))
            ->toIso8601String();
    }

    /**
     * Format a datetime to a standard datetime string in the application's timezone.
     *
     * Returns format: 2026-02-18 15:30:00
     */
    public static function toDateTimeString(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return Carbon::parse($date)
            ->timezone(config('app.timezone'))
            ->toDateTimeString();
    }

    /**
     * Format a datetime to a date string in the application's timezone.
     *
     * Returns format: 2026-02-18
     */
    public static function toDateString(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return Carbon::parse($date)
            ->timezone(config('app.timezone'))
            ->toDateString();
    }

    /**
     * Parse a datetime string and convert it to the application's timezone.
     *
     * @param  string  $date  ISO 8601 datetime string or any parseable datetime format
     */
    public static function parse(string $date): Carbon
    {
        return Carbon::parse($date)->timezone(config('app.timezone'));
    }

    /**
     * Get the current datetime in the application's timezone.
     */
    public static function now(): Carbon
    {
        return Carbon::now(config('app.timezone'));
    }

    /**
     * Get the application's timezone.
     */
    public static function timezone(): string
    {
        return config('app.timezone', 'UTC');
    }

    /**
     * Get the timezone offset in hours for display purposes.
     *
     * Returns: +08:00 for Manila time
     */
    public static function timezoneOffset(): string
    {
        $carbon = Carbon::now(config('app.timezone'));

        return $carbon->format('P');
    }
}
