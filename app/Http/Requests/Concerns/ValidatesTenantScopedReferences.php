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
        $clinicId = app(TenantContext::class)->clinicId();

        if ($clinicId !== null) {
            $rule->where(fn ($query) => $query->where('clinic_id', $clinicId));
        }

        return $rule;
    }
}
