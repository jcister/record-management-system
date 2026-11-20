<?php

namespace App\Support;

class PersonNameFormatter
{
    /**
     * Compose a full name from split parts, adding the extension when present.
     */
    public static function compose(?string $first, ?string $middle, ?string $last, ?string $extension): string
    {
        $segments = [];

        if ($first = trim((string) $first)) {
            $segments[] = $first;
        }

        if ($middle = trim((string) $middle)) {
            $segments[] = $middle;
        }

        if ($last = trim((string) $last)) {
            $segments[] = $last;
        }

        $base = trim(implode(' ', $segments));
        $suffix = trim((string) $extension);

        return $base === '' ? '' : ($suffix !== '' ? $base.', '.$suffix : $base);
    }

    /**
     * @return array{first: string, middle: string, last: string, extension: string}
     */
    public static function split(
        ?string $first,
        ?string $middle,
        ?string $last,
        ?string $extension,
        ?string $fullName
    ): array {
        if ($first || $middle || $last || $extension) {
            return [
                'first' => trim((string) $first),
                'middle' => trim((string) $middle),
                'last' => trim((string) $last),
                'extension' => trim((string) $extension),
            ];
        }

        $full = trim((string) $fullName);
        if ($full === '') {
            return ['first' => '', 'middle' => '', 'last' => '', 'extension' => ''];
        }

        $detectedExtension = '';
        $namePortion = $full;

        if (str_contains($full, ',')) {
            [$namePortion, $suffix] = array_map('trim', explode(',', $full, 2));
            $detectedExtension = $suffix;
        }

        $tokens = array_values(array_filter(preg_split('/\s+/', $namePortion) ?: []));
        if ($tokens === []) {
            return ['first' => '', 'middle' => '', 'last' => '', 'extension' => $detectedExtension];
        }

        $supportedExtensions = ['JR', 'JR.', 'SR', 'SR.', 'II', 'III', 'IV', 'V', 'VI'];
        $lastToken = strtoupper(str_replace(['.', ','], '', end($tokens)));
        if ($detectedExtension === '' && in_array($lastToken, $supportedExtensions, true)) {
            $detectedExtension = array_pop($tokens) ?? '';
        }

        $firstName = array_shift($tokens) ?? '';
        $lastName = array_pop($tokens) ?? '';
        $middleName = implode(' ', $tokens);

        return [
            'first' => trim($firstName),
            'middle' => trim($middleName),
            'last' => trim($lastName),
            'extension' => trim($detectedExtension),
        ];
    }
}
