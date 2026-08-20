<?php

namespace App\Modules\Clinics\Services;

use App\Core\Base\BaseService;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Clinics\Contracts\ClinicRepositoryInterface;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClinicService extends BaseService
{
    public function __construct(
        ClinicRepositoryInterface $repository,
        private readonly AuditTrailService $audit
    ) {
        $this->repository = $repository;
    }

    public function update(int $id, array $data): Model
    {
        return DB::transaction(function () use ($id, $data): Clinic {
            /** @var Clinic $clinic */
            $clinic = $this->repository->findOrFail($id);
            $before = $clinic->only(['brand_icon_mode', 'brand_icon_key']);

            $this->repository->update($clinic, $data);
            $clinic->refresh();

            $this->audit->record(
                'clinic.branding.updated',
                $clinic,
                $before,
                $clinic->only(['brand_icon_mode', 'brand_icon_key']),
                subjectLabel: $clinic->trade_name ?: $clinic->corporate_name,
                clinicId: $clinic->id
            );

            return $clinic;
        });
    }
}
