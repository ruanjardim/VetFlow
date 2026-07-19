<?php

namespace App\Models\Concerns;

use App\Modules\Clinics\Models\Clinic;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToClinicTenant
{
    public static function bootBelongsToClinicTenant(): void
    {
        static::addGlobalScope('clinic_tenant', function (Builder $builder): void {
            /** @var Model $model */
            $model = $builder->getModel();

            app(TenantContext::class)->apply($builder, $model);
        });

        static::creating(function (Model $model): void {
            app(TenantContext::class)->stampModel($model);
        });

        static::updating(function (Model $model): void {
            app(TenantContext::class)->stampModel($model);
        });
    }

    public function tenantColumn(): string
    {
        return 'clinic_id';
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
