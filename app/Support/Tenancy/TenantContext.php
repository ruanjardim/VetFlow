<?php

namespace App\Support\Tenancy;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TenantContext
{
    public function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function clinicId(): ?int
    {
        return $this->user()?->clinic_id;
    }

    public function isGlobal(): bool
    {
        return $this->clinicId() === null;
    }

    public function shouldScope(Model $model): bool
    {
        return method_exists($model, 'tenantColumn') && ! $this->isGlobal();
    }

    public function apply(Builder $query, Model $model): Builder
    {
        if (! $this->shouldScope($model)) {
            return $query;
        }

        return $query->where(
            $model->getTable().'.'.$model->tenantColumn(),
            $this->clinicId()
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function stamp(Model $model, array $data): array
    {
        if ($this->shouldScope($model)) {
            $data[$model->tenantColumn()] = $this->clinicId();
        }

        return $data;
    }

    public function stampModel(Model $model): void
    {
        if (! $this->shouldScope($model)) {
            return;
        }

        $model->setAttribute($model->tenantColumn(), $this->clinicId());
    }
}
