<?php

namespace App\Modules\Patients\Services;

use App\Core\Base\BaseService;
use App\Modules\Patients\Contracts\PatientRepositoryInterface;
use App\Modules\Patients\Support\PatientLifecycleStatus;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PatientService extends BaseService
{
    public function __construct(
        PatientRepositoryInterface $repository,
        private readonly PatientTaxonomyService $taxonomy,
        private readonly PatientPhotoService $photos
    ) {
        $this->repository = $repository;
    }

    public function search(?string $search = null, string $status = 'active', int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->search($search, $status, $perPage);
    }

    public function create(array $data): Model
    {
        $storedPhoto = null;
        $clinicId = null;

        try {
            return DB::transaction(function () use ($data, &$storedPhoto, &$clinicId): Model {
                [$data, $photo] = $this->extractPhotoInput($data);
                $tutor = $this->resolveTutor($data);
                $clinicId = (int) $tutor->clinic_id;
                $data = $this->taxonomy->applyToPatientData($data, $clinicId);
                $data['clinic_id'] = $clinicId;

                if ($photo) {
                    $storedPhoto = $this->photos->store($photo, $clinicId);
                    $data['photo_path'] = $storedPhoto;
                }

                return parent::create($data);
            });
        } catch (Throwable $exception) {
            $this->photos->delete($storedPhoto, (int) $clinicId);

            throw $exception;
        }
    }

    public function update(int $id, array $data): Model
    {
        $storedPhoto = null;
        $previousPhoto = null;
        $clinicId = null;
        $removePhoto = false;

        try {
            $patient = DB::transaction(function () use (
                $id,
                $data,
                &$storedPhoto,
                &$previousPhoto,
                &$clinicId,
                &$removePhoto
            ): Model {
                [$data, $photo, $removePhoto] = $this->extractPhotoInput($data);
                $patient = $this->repository->findOrFail($id);

                if (! $patient->isAvailable()) {
                    throw ValidationException::withMessages([
                        'patient' => sprintf(
                            'Restaure a ficha do paciente antes de editar. Estado atual: %s.',
                            PatientLifecycleStatus::label($patient->lifecycle_status)
                        ),
                    ]);
                }

                $tutor = $this->resolveTutor($data);

                if (
                    $patient->clinic_id !== null
                    && (int) $patient->clinic_id !== (int) $tutor->clinic_id
                ) {
                    throw ValidationException::withMessages([
                        'tutor_id' => 'O tutor responsável deve pertencer à mesma clínica do paciente.',
                    ]);
                }

                $clinicId = (int) $tutor->clinic_id;
                $previousPhoto = $patient->photo_path;
                $data = $this->taxonomy->applyToPatientData($data, $clinicId);
                $data['clinic_id'] = $clinicId;

                if ($photo) {
                    $storedPhoto = $this->photos->store($photo, $clinicId);
                    $data['photo_path'] = $storedPhoto;
                } elseif ($removePhoto) {
                    $data['photo_path'] = null;
                }

                $this->repository->update($patient, $data);

                return $patient->refresh();
            });
        } catch (Throwable $exception) {
            $this->photos->delete($storedPhoto, (int) $clinicId);

            throw $exception;
        }

        if (($storedPhoto || $removePhoto) && $previousPhoto) {
            $this->photos->delete($previousPhoto, (int) $clinicId);
        }

        return $patient;
    }

    /** @return array{0: array<string, mixed>, 1: UploadedFile|null, 2: bool} */
    private function extractPhotoInput(array $data): array
    {
        $photo = $data['photo'] ?? null;
        $removePhoto = (bool) ($data['remove_photo'] ?? false);

        unset($data['photo'], $data['remove_photo']);

        return [
            $data,
            $photo instanceof UploadedFile && $photo->isValid() ? $photo : null,
            $removePhoto,
        ];
    }

    private function resolveTutor(array $data): Tutor
    {
        return Tutor::query()->findOrFail((int) ($data['tutor_id'] ?? 0));
    }
}
