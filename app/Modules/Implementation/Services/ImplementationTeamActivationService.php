<?php

namespace App\Modules\Implementation\Services;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Support\Collection;

class ImplementationTeamActivationService
{
    /**
     * @param  Collection<int, Clinic>  $clinics
     * @param  array<int, array<string, mixed>>  $pilotChecklists
     * @return array<int, array<string, mixed>>
     */
    public function forClinics(Collection $clinics, array $pilotChecklists): array
    {
        if ($clinics->isEmpty()) {
            return [];
        }

        $usersByClinic = User::query()
            ->active()
            ->whereIn('clinic_id', $clinics->pluck('id'))
            ->with(['roles' => fn ($query) => $query->active()])
            ->get()
            ->groupBy('clinic_id');
        $checklistsByClinic = collect($pilotChecklists)->keyBy('clinic_id');

        return $clinics
            ->map(function (Clinic $clinic) use ($usersByClinic, $checklistsByClinic): array {
                $users = $usersByClinic->get($clinic->id, collect());
                $veterinarians = $users->filter(
                    fn (User $user): bool => filled($user->veterinary_license_number)
                        && filled($user->veterinary_license_state)
                );
                $groomingProfessionals = $users->where('grooming_professional', true);
                $professionals = $users->filter(
                    fn (User $user): bool => $user->grooming_professional
                        || $veterinarians->contains('id', $user->id)
                );
                $accessCheck = collect($checklistsByClinic->get($clinic->id, []))
                    ->get('checks', []);
                $accessCheck = collect($accessCheck)->firstWhere('key', 'access_validated');

                return [
                    'clinic_id' => $clinic->id,
                    'clinic_name' => $clinic->trade_name,
                    'active_users' => $users->count(),
                    'administrators' => $users->filter(
                        fn (User $user): bool => $user->roles->contains('slug', 'administrador')
                    )->count(),
                    'veterinarians' => $veterinarians->count(),
                    'grooming_professionals' => $groomingProfessionals->count(),
                    'operational_professionals' => $professionals->count(),
                    'access_validation' => [
                        'completed' => (bool) ($accessCheck['completed'] ?? false),
                        'has_decision' => (bool) ($accessCheck['has_decision'] ?? false),
                        'user_name' => $accessCheck['user_name'] ?? null,
                        'decided_at' => $accessCheck['decided_at'] ?? null,
                    ],
                ];
            })
            ->values()
            ->all();
    }
}
