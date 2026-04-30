<?php

declare(strict_types=1);

namespace App\Services;

class PhpUploadLimit
{
    public static function maxUploadKilobytes(?string $uploadMaxFilesize = null, ?string $postMaxSize = null): int
    {
        $limits = array_filter([
            self::parseToBytes($uploadMaxFilesize ?? (string) ini_get('upload_max_filesize')),
            self::parseToBytes($postMaxSize ?? (string) ini_get('post_max_size')),
        ]);

        if ($limits === []) {
            return 0;
        }

        return (int) floor(min($limits) / 1024);
    }

    public static function humanReadableMaxUpload(?string $uploadMaxFilesize = null, ?string $postMaxSize = null): string
    {
        $kilobytes = self::maxUploadKilobytes($uploadMaxFilesize, $postMaxSize);

        if ($kilobytes === 0) {
            return 'unlimited';
        }

        if ($kilobytes % 1048576 === 0) {
            return (string) ($kilobytes / 1048576).' GB';
        }

        if ($kilobytes % 1024 === 0) {
            return (string) ($kilobytes / 1024).' MB';
        }

        return (string) $kilobytes.' KB';
    }

    public static function parseToBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $metric = strtoupper(substr($value, -1));
        $number = (float) substr($value, 0, -1);

        $multiplier = match ($metric) {
            'K' => 1024,
            'M' => 1048576,
            'G' => 1073741824,
            default => 1,
        };

        return max(0, (int) floor($number * $multiplier));
    }
}
