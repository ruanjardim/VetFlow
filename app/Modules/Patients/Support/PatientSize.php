<?php

namespace App\Modules\Patients\Support;

/**
 * Porte do pet usado pelo Banho e Tosa para escolher a tabela de preco.
 *
 * As faixas de peso sao apenas uma sugestao editavel (premissa operacional,
 * nao regra fiscal): o operador sempre pode escolher outro porte no cadastro.
 */
final class PatientSize
{
    public const LABELS = [
        'small' => 'Pequeno',
        'medium' => 'Médio',
        'large' => 'Grande',
        'giant' => 'Gigante',
    ];

    /** Limite superior (kg, inclusivo) de cada porte sugerido pelo peso. */
    public const WEIGHT_LIMITS = [
        'small' => 10.0,
        'medium' => 25.0,
        'large' => 45.0,
    ];

    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(?string $size): ?string
    {
        return $size ? (self::LABELS[$size] ?? null) : null;
    }

    public static function suggestFromWeight(mixed $weight): ?string
    {
        if ($weight === null || $weight === '' || ! is_numeric($weight) || (float) $weight <= 0) {
            return null;
        }

        foreach (self::WEIGHT_LIMITS as $size => $limit) {
            if ((float) $weight <= $limit) {
                return $size;
            }
        }

        return 'giant';
    }

    /** Porte informado ou, na falta dele, o sugerido pelo peso. */
    public static function resolve(?string $size, mixed $weight): ?string
    {
        if ($size && array_key_exists($size, self::LABELS)) {
            return $size;
        }

        return self::suggestFromWeight($weight);
    }

    /** Coluna de preco do servico PetShop correspondente ao porte. */
    public static function priceColumn(?string $size): ?string
    {
        return $size && array_key_exists($size, self::LABELS) ? "{$size}_price" : null;
    }
}
