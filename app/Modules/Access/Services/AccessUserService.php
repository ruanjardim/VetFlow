<?php

namespace App\Modules\Access\Services;

use App\Models\Role;
use App\Models\User;
use App\Modules\Access\Contracts\AccessUserRepositoryInterface;
use App\Modules\Audit\Services\AuditTrailService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessUserService
{
    public function __construct(
        private readonly AccessUserRepositoryInterface $repository,
        private readonly AuditTrailService $audit
    ) {}

    public function paginate(User $actor): LengthAwarePaginator
    {
        return $this->repository->paginateFor($actor);
    }

    public function find(User $actor, int $id): User
    {
        return $this->repository->findFor($actor, $id);
    }

    /**
     * @return array{accessUser: ?User, roles: Collection, clinics: Collection}
     */
    public function formData(User $actor, ?User $accessUser = null): array
    {
        return [
            'accessUser' => $accessUser,
            'roles' => $this->repository->assignableRoles(),
            'clinics' => $this->repository->availableClinics($actor),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): User
    {
        [$roleIds] = $this->validatedRoles($data['role_ids'] ?? []);
        $attributes = $this->userAttributes($actor, $data);

        return DB::transaction(function () use ($actor, $attributes, $roleIds): User {
            $user = $this->repository->create($attributes);
            $this->repository->syncRoles($user, $roleIds, $actor);

            $user->load(['clinic', 'roles']);
            $this->audit->record(
                'access.user.created',
                $user,
                [],
                $this->auditSnapshot($user),
                $actor,
                [],
                $user->clinic_id,
                $user->name
            );

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $accessUser, array $data): User
    {
        [$roleIds, $roles] = $this->validatedRoles($data['role_ids'] ?? []);
        $attributes = $this->userAttributes($actor, $data, $accessUser);
        $before = $this->auditSnapshot($accessUser);
        $passwordChanged = ! empty($data['password']);

        $this->guardSelfUpdate($actor, $accessUser, $attributes, $roles);

        return DB::transaction(function () use ($actor, $accessUser, $attributes, $roleIds, $before, $passwordChanged): User {
            $user = $this->repository->update($accessUser, $attributes);
            $this->repository->syncRoles($user, $roleIds, $actor);
            $user->load(['clinic', 'roles']);

            $this->audit->record(
                'access.user.updated',
                $user,
                $before,
                $this->auditSnapshot($user),
                $actor,
                $passwordChanged ? ['password_changed' => true] : [],
                $user->clinic_id,
                $user->name
            );

            return $user;
        });
    }

    /**
     * @param  array<int, mixed>  $roleIds
     * @return array{0: array<int, int>, 1: Collection}
     */
    private function validatedRoles(array $roleIds): array
    {
        $roleIds = collect($roleIds)
            ->map(fn ($roleId): int => (int) $roleId)
            ->unique()
            ->values();

        $roles = $this->repository->assignableRoles()
            ->whereIn('id', $roleIds)
            ->values();

        if ($roleIds->isEmpty() || $roles->count() !== $roleIds->count()) {
            throw ValidationException::withMessages([
                'role_ids' => 'Selecione apenas perfis padrao ativos do VetFlow.',
            ]);
        }

        return [$roleIds->all(), $roles];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function userAttributes(User $actor, array $data, ?User $accessUser = null): array
    {
        $attributes = [
            'clinic_id' => $actor->clinic_id ?? ($data['clinic_id'] ?? null),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'position' => $data['position'] ?? null,
            'active' => (bool) $data['active'],
        ];

        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        if ($accessUser === null && ! array_key_exists('password', $attributes)) {
            throw ValidationException::withMessages([
                'password' => 'Informe uma senha para o novo colaborador.',
            ]);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function guardSelfUpdate(
        User $actor,
        User $accessUser,
        array $attributes,
        Collection $roles
    ): void {
        if ($actor->id !== $accessUser->id) {
            return;
        }

        if (! $attributes['active']) {
            throw ValidationException::withMessages([
                'active' => 'Voce nao pode desativar o proprio acesso.',
            ]);
        }

        if ($attributes['clinic_id'] !== $actor->clinic_id) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Voce nao pode alterar a propria clinica de acesso.',
            ]);
        }

        $keepsManagementAccess = $roles->contains(
            fn (Role $role): bool => $role->permissions->contains('slug', 'users.manage')
        );

        if (! $keepsManagementAccess) {
            throw ValidationException::withMessages([
                'role_ids' => 'Mantenha um perfil com gestao de usuarios no seu proprio acesso.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(User $user): array
    {
        $user->loadMissing('roles');

        return [
            'clinic_id' => $user->clinic_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'position' => $user->position,
            'active' => (bool) $user->active,
            'roles' => $user->roles->pluck('slug')->sort()->values()->all(),
        ];
    }
}
