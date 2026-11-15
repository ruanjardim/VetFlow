<?php

namespace App\Modules\Implementation\Services;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Models\ImplementationPilotRelease;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImplementationPilotReleaseService
{
    /**
     * @param  Collection<int, Clinic>  $clinics
     * @return array<int, array<string, mixed>>
     */
    public function forClinics(Collection $clinics): array
    {
        if ($clinics->isEmpty()) {
            return [];
        }

        $latest = ImplementationPilotRelease::query()
            ->whereIn('clinic_id', $clinics->pluck('id'))
            ->latest('recorded_at')
            ->latest('id')
            ->get()
            ->unique('clinic_id')
            ->keyBy('clinic_id');

        return $clinics
            ->map(function (Clinic $clinic) use ($latest): array {
                /** @var ImplementationPilotRelease|null $release */
                $release = $latest->get($clinic->id);

                return [
                    'clinic_id' => $clinic->id,
                    'clinic_name' => $clinic->trade_name,
                    'has_release' => $release !== null,
                    'revision' => $release?->revision,
                    'release_owner' => $release?->release_owner,
                    'support_owner' => $release?->support_owner,
                    'planned_start_date' => $release?->planned_start_date,
                    'scope' => $release?->scope,
                    'release_notes' => $release?->release_notes,
                    'recorded_at' => $release?->recorded_at,
                    'user_name' => $release?->user_name,
                ];
            })
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $data */
    public function record(
        Clinic $clinic,
        User $user,
        array $data
    ): ImplementationPilotRelease {
        return DB::transaction(function () use ($clinic, $user, $data): ImplementationPilotRelease {
            $revision = ((int) ImplementationPilotRelease::query()
                ->where('clinic_id', $clinic->id)
                ->lockForUpdate()
                ->max('revision')) + 1;

            return ImplementationPilotRelease::query()->create([
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'clinic_name' => $clinic->trade_name,
                'user_name' => $user->name,
                'revision' => $revision,
                'release_owner' => trim((string) $data['release_owner']),
                'support_owner' => trim((string) $data['support_owner']),
                'planned_start_date' => $data['planned_start_date'] ?? null,
                'scope' => trim((string) $data['scope']),
                'release_notes' => trim((string) $data['release_notes']),
                'recorded_at' => now(),
            ]);
        });
    }
}
