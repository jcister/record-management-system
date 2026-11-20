<?php

namespace App\Support;

class Utf8Sanitizer
{
    public static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::sanitize($item);
            }

            return $value;
        }

        if (is_object($value)) {
            foreach (get_object_vars($value) as $key => $item) {
                $value->$key = self::sanitize($item);
            }

            return $value;
        }

        if (is_string($value)) {
            if (mb_check_encoding($value, 'UTF-8')) {
                return $value;
            }

            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

            return $converted === false ? $value : $converted;
        }

        return $value;
    }
}
