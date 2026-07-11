<?php

namespace App\Core\Validation;

class BrazilianDocumentValidator
{
    public static function cpf(?string $cpf): bool
    {
        $cpf = self::onlyNumbers($cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;

            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }

            $digit = ((10 * $sum) % 11) % 10;

            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function cnpj(?string $cnpj): bool
    {
        $cnpj = self::onlyNumbers($cnpj);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weightsFirstDigit = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weightsSecondDigit = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $firstDigit = self::calculateCnpjDigit(substr($cnpj, 0, 12), $weightsFirstDigit);

        if ((int) $cnpj[12] !== $firstDigit) {
            return false;
        }

        $secondDigit = self::calculateCnpjDigit(substr($cnpj, 0, 13), $weightsSecondDigit);

        return (int) $cnpj[13] === $secondDigit;
    }

    public static function onlyNumbers(?string $value): string
    {
        return preg_replace('/\D/', '', $value ?? '');
    }

    private static function calculateCnpjDigit(string $base, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (int) $base[$index] * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}