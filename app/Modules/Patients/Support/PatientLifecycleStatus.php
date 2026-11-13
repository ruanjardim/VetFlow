<?php

namespace App\Modules\Patients\Support;

final class PatientLifecycleStatus
{
    public const ACTIVE = 'active';

    public const ARCHIVED = 'archived';

    public const DECEASED = 'deceased';

    public const LABELS = [
        self::ACTIVE => 'Ativo',
        self::ARCHIVED => 'Arquivado',
        self::DECEASED => 'Óbito',
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(?string $status): string
    {
        return self::LABELS[$status] ?? 'Não informado';
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, match ($from) {
            self::ACTIVE => [self::ARCHIVED, self::DECEASED],
            self::ARCHIVED => [self::ACTIVE, self::DECEASED],
            self::DECEASED => [self::ACTIVE],
            default => [],
        }, true);
    }
}
