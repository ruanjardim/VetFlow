<?php

namespace App\Core\Base;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Support\Tenancy\TenantContext;

abstract class BaseRepository
{
    protected Model $model;

    protected function query()
    {
        return app(TenantContext::class)->apply($this->model->newQuery(), $this->model);
    }

    protected function tenantData(array $data): array
    {
        return app(TenantContext::class)->stamp($this->model, $data);
    }

    public function all(): Collection
    {
        return $this->query()->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage);
    }

    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function create(array $data): Model
    {
        return $this->model->create($this->tenantData($data));
    }

    public function update(Model $model, array $data): bool
    {
        return $model->update($this->tenantData($data));
    }

    public function delete(Model $model): ?bool
    {
        return $model->delete();
    }
}
