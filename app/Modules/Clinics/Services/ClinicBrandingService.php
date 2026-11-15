<?php

namespace App\Modules\Clinics\Services;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\AnimalSpecies;

class ClinicBrandingService
{
    public const MODE_AUTOMATIC = 'automatic';

    public const MODE_MANUAL = 'manual';

    public const MODE_NONE = 'none';

    public const ICON_GENERIC = 'generic';

    public const ICON_CANINE = 'canine';

    public const ICON_FELINE = 'feline';

    public const ICON_EQUINE = 'equine';

    public const ICON_AVIAN = 'avian';

    public const ICON_EXOTIC = 'exotic';

    /** @return array<string, string> */
    public static function modes(): array
    {
        return [
            self::MODE_AUTOMATIC => 'Automático pelas espécies de atuação',
            self::MODE_MANUAL => 'Escolher o ícone',
            self::MODE_NONE => 'Não exibir ícone',
        ];
    }

    /** @return array<string, string> */
    public static function icons(): array
    {
        return [
            self::ICON_GENERIC => 'Pata genérica',
            self::ICON_CANINE => 'Canino',
            self::ICON_FELINE => 'Felino',
            self::ICON_EQUINE => 'Equino',
            self::ICON_AVIAN => 'Ave',
            self::ICON_EXOTIC => 'Exóticos e silvestres',
        ];
    }

    public function resolveForUser(?User $user): ?string
    {
        if (! $user?->clinic_id) {
            return self::ICON_GENERIC;
        }

        $clinic = $user->relationLoaded('clinic')
            ? $user->clinic
            : $user->clinic()->first();

        return $clinic ? $this->resolveForClinic($clinic) : self::ICON_GENERIC;
    }

    public function resolveForClinic(Clinic $clinic): ?string
    {
        return match ($clinic->brand_icon_mode) {
            self::MODE_NONE => null,
            self::MODE_MANUAL => array_key_exists((string) $clinic->brand_icon_key, self::icons())
                ? $clinic->brand_icon_key
                : self::ICON_GENERIC,
            default => $this->automaticIcon($clinic),
        };
    }

    private function automaticIcon(Clinic $clinic): string
    {
        $species = AnimalSpecies::query()
            ->select(['animal_species.normalized_name', 'animal_species.category'])
            ->join('user_animal_species', 'user_animal_species.animal_species_id', '=', 'animal_species.id')
            ->join('users', 'users.id', '=', 'user_animal_species.user_id')
            ->where('users.clinic_id', $clinic->id)
            ->where('users.active', true)
            ->whereNull('users.deleted_at')
            ->where('animal_species.active', true)
            ->distinct()
            ->get();

        if ($species->isEmpty()) {
            return self::ICON_GENERIC;
        }

        $icons = $species
            ->map(fn (AnimalSpecies $item): string => $this->iconForSpecies($item))
            ->unique()
            ->values();

        return $icons->count() === 1
            ? (string) $icons->first()
            : self::ICON_GENERIC;
    }

    private function iconForSpecies(AnimalSpecies $species): string
    {
        $normalizedName = (string) $species->normalized_name;

        if ($normalizedName === 'canino') {
            return self::ICON_CANINE;
        }

        if ($normalizedName === 'felino') {
            return self::ICON_FELINE;
        }

        if (in_array($normalizedName, ['equino', 'asinino', 'muar'], true)) {
            return self::ICON_EQUINE;
        }

        return match ($species->category) {
            'Aves' => self::ICON_AVIAN,
            'Répteis e anfíbios', 'Aquáticos', 'Silvestres e outros' => self::ICON_EXOTIC,
            default => self::ICON_GENERIC,
        };
    }
}
