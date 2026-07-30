<?php

namespace App\Modules\Access\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface AccessUserRepositoryInterface
{
    public function paginateFor(User $actor, int $perPage = 15): LengthAwarePaginator;

    public function findFor(User $actor, int $id): User;

    public function assignableRoles(): Collection;

    public function availableClinics(User $actor): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User;

    /**
     * @param  array<int, int>  $roleIds
     */
    public function syncRoles(User $user, array $roleIds, User $actor): void;
}
