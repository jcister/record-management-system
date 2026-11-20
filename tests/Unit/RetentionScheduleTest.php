<?php

use App\Support\RetentionSchedule;

it('returns null when missing input', function (): void {
    expect(RetentionSchedule::expiresAt(null, RetentionSchedule::DURATION_ONE_YEAR))->toBeNull()
        ->and(RetentionSchedule::expiresAt('2024-01-01', null))->toBeNull();
});

it('returns null for perpetual duration', function (): void {
    expect(RetentionSchedule::expiresAt('2024-01-01', RetentionSchedule::DURATION_PERPETUAL))->toBeNull();
});

it('calculates retention expiry for supported durations', function (string $duration, string $expected): void {
    expect(RetentionSchedule::expiresAt('2024-03-15', $duration))->toBe($expected);
})->with([
    'one year' => [RetentionSchedule::DURATION_ONE_YEAR, '2025-03-15'],
    'ten years' => [RetentionSchedule::DURATION_TEN_YEARS, '2034-03-15'],
]);

it('maps durations to year counts', function (): void {
    expect(RetentionSchedule::yearsForDuration(RetentionSchedule::DURATION_ONE_YEAR))->toBe(1)
        ->and(RetentionSchedule::yearsForDuration(RetentionSchedule::DURATION_TEN_YEARS))->toBe(10)
        ->and(RetentionSchedule::yearsForDuration('unknown'))->toBeNull();
});
