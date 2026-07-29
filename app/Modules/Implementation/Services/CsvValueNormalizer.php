<?php

namespace App\Modules\Implementation\Services;

use DateTimeImmutable;

class CsvValueNormalizer
{
    public function nullableString(mixed $value): ?string
    {
        $value = trim($this->toUtf8((string) ($value ?? '')));

        return $value !== '' ? $value : null;
    }

    public function decimal(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        $value = str_replace(' ', '', $value);

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return $value;
    }

    public function date(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            $isValid = $date !== false
                && ($errors === false || (
                    $errors['warning_count'] === 0
                    && $errors['error_count'] === 0
                ))
                && $date->format($format) === $value;

            if ($isValid) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
