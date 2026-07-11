<?php

namespace App\Core\Base;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseService
{
    /**
     * O repositório será definido na classe filha.
     */
    protected $repository;

    /**
     * Lista todos os registros.
     */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    /**
     * Lista paginada.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Busca um registro.
     */
    public function find(int $id): Model
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Busca um registro ou falha.
     */
    public function findOrFail(int $id): Model
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Cria um novo registro.
     */
    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    /**
     * Atualiza um registro.
     */
    public function update(int $id, array $data): Model
    {
        $model = $this->repository->findOrFail($id);

        $this->repository->update($model, $data);

        return $model->refresh();
    }

    /**
     * Remove um registro.
     */
    public function delete(int $id): bool
    {
        $model = $this->repository->findOrFail($id);

        return (bool) $this->repository->delete($model);
    }
}
