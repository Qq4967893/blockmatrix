<?php

namespace App\Utils;

final class S3FileKey
{
    public static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $value) !== 1) {
            return ltrim($value, '/');
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return $value;
        }

        $path = parse_url($value, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return $value;
        }

        return ltrim($path, '/');
    }
}
