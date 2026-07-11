<?php

namespace App\Modules\Products\Support;

class Gtin
{
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    public static function looksValid(?string $value): bool
    {
        $digits = self::normalize($value);

        return $digits !== null && strlen($digits) >= 8 && strlen($digits) <= 14;
    }

    public static function variants(?string $value): array
    {
        $digits = self::normalize($value);

        if (! self::looksValid($digits)) {
            return [];
        }

        $variants = [$digits];

        if (strlen($digits) === 14 && str_starts_with($digits, '0')) {
            $variants[] = substr($digits, 1);
        }

        if (strlen($digits) === 13) {
            $variants[] = '0'.$digits;
        }

        return array_values(array_unique(array_filter($variants)));
    }
}
