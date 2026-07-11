<?php

namespace App\Core\Support;

class DocumentNormalizer
{
    public static function onlyNumbers(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $numbers = preg_replace('/\D/', '', $value);

        return $numbers !== '' ? $numbers : null;
    }
}