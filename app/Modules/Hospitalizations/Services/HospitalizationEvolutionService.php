<?php

namespace App\Modules\Hospitalizations\Services;

use App\Modules\Hospitalizations\Models\Hospitalization;
use App\Modules\Hospitalizations\Models\HospitalizationEvolution;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class HospitalizationEvolutionService
{
    public function create(int $hospitalizationId, array $data): HospitalizationEvolution
    {
        $hospitalization = Hospitalization::query()->findOrFail($hospitalizationId);

        if ($hospitalization->status !== 'hospitalized') {
            throw ValidationException::withMessages([
                'evolution' => 'Novas evoluções só podem ser registradas enquanto o paciente estiver internado.',
            ]);
        }

        if (Carbon::parse($data['observed_at'])->lt($hospitalization->admitted_at)) {
            throw ValidationException::withMessages([
                'observed_at' => 'A evolução não pode ser anterior à admissão.',
            ]);
        }

        return $hospitalization->evolutions()->create([
            ...Arr::only($data, [
                'observed_at',
                'weight',
                'temperature',
                'heart_rate',
                'respiratory_rate',
                'notes',
            ]),
            'clinic_id' => $hospitalization->clinic_id,
            'recorded_by' => auth()->id(),
        ]);
    }
}
