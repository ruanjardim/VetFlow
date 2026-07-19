<?php

namespace App\Http\Requests\Concerns;

use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ValidatesTenantScopedReferences
{
    protected function existsInCurrentClinic(string $table, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column);
        $clinicId = $this->tenantReferenceClinicId();

        if ($clinicId !== null) {
            $rule->where(fn ($query) => $query->where('clinic_id', $clinicId));
        }

        return $rule;
    }

    private function tenantReferenceClinicId(): ?int
    {
        $contextClinicId = app(TenantContext::class)->clinicId();

        if ($contextClinicId !== null) {
            return $contextClinicId;
        }

        $selectedClinicId = $this->input('clinic_id');

        return $selectedClinicId !== null && $selectedClinicId !== ''
            ? (int) $selectedClinicId
            : null;
    }
}
