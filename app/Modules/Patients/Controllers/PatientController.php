<?php

namespace App\Modules\Patients\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Patients\Requests\ChangePatientLifecycleRequest;
use App\Modules\Patients\Requests\StorePatientRequest;
use App\Modules\Patients\Requests\UpdatePatientRequest;
use App\Modules\Patients\Services\PatientClinicalProfileService;
use App\Modules\Patients\Services\PatientLifecycleService;
use App\Modules\Patients\Services\PatientPhotoService;
use App\Modules\Patients\Services\PatientService;
use App\Modules\Patients\Services\PatientTaxonomyService;
use App\Modules\Patients\Support\PatientLifecycleStatus;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientController extends BaseCrudController
{
    public function __construct(
        private readonly PatientService $patients,
        private readonly PatientTaxonomyService $taxonomy,
        private readonly PatientClinicalProfileService $profile,
        private readonly PatientPhotoService $photos,
        private readonly PatientLifecycleService $lifecycle
    ) {
        $this->service = $this->patients;
        $this->viewPath = 'patients';
        $this->routeName = 'patients';
        $this->viewVariable = 'patients';
    }

    public function index()
    {
        $request = request();
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in([...PatientLifecycleStatus::keys(), 'all'])],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $status = (string) ($validated['status'] ?? PatientLifecycleStatus::ACTIVE);

        return view('patients.index', [
            'patients' => $this->patients
                ->search($search !== '' ? $search : null, $status)
                ->withQueryString(),
            'search' => $search,
            'lifecycleStatus' => $status,
            'lifecycleLabels' => PatientLifecycleStatus::LABELS,
        ]);
    }

    public function create()
    {
        return view('patients.create', $this->formData());
    }

    public function edit(int $id)
    {
        $patient = $this->service->findOrFail($id);

        if (! $patient->isAvailable()) {
            return redirect()
                ->route('patients.show', $patient->id)
                ->with('error', 'Restaure a ficha antes de editar os dados do paciente.');
        }

        return view('patients.edit', array_merge($this->formData($patient), [
            'item' => $patient,
        ]));
    }

    public function show(int $id)
    {
        $user = auth()->user();

        return view('patients.show', $this->profile->forPatient($id, [
            'appointments' => $user?->can('appointments.manage') ?? false,
            'medicalRecords' => $user?->can('medical-records.manage') ?? false,
            'prescriptions' => $user?->can('prescriptions.manage') ?? false,
            'vaccinations' => $user?->can('vaccinations.manage') ?? false,
            'hospitalizations' => $user?->can('hospitalizations.manage') ?? false,
        ]));
    }

    public function photo(int $patient): StreamedResponse
    {
        return $this->photos->response($this->service->findOrFail($patient));
    }

    public function changeLifecycle(ChangePatientLifecycleRequest $request, int $patient)
    {
        $validated = $request->validated();
        $updated = $this->lifecycle->transition(
            $patient,
            $validated['status'],
            $validated['reason']
        );

        return redirect()
            ->route('patients.show', $updated->id)
            ->with('success', 'Estado do paciente atualizado com sucesso.');
    }

    protected function storeRequest(): string
    {
        return StorePatientRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdatePatientRequest::class;
    }

    private function availableTutors()
    {
        return Tutor::query()
            ->orderBy('name')
            ->get();
    }

    private function formData(?object $patient = null): array
    {
        return [
            'tutors' => $this->availableTutors(),
            'speciesCatalog' => $this->taxonomy->formCatalog(
                $patient?->animal_species_id ? (int) $patient->animal_species_id : null
            ),
            'speciesCategories' => PatientTaxonomyService::CATEGORY_LABELS,
            'taxonomySelection' => $this->taxonomy->selectedIds($patient),
        ];
    }
}
