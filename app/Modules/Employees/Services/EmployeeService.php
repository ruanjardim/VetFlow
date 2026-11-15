<?php

namespace App\Modules\Employees\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Employees\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(private readonly AuditTrailService $audit) {}

    public function paginate(User $actor, ?int $clinicId = null): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['clinic', 'user'])
            ->when($actor->clinic_id !== null, fn ($query) => $query->where('clinic_id', $actor->clinic_id))
            ->when($actor->clinic_id === null && $clinicId, fn ($query) => $query->where('clinic_id', $clinicId))
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    public function find(User $actor, int $id): Employee
    {
        return Employee::query()
            ->with(['clinic', 'user'])
            ->when($actor->clinic_id !== null, fn ($query) => $query->where('clinic_id', $actor->clinic_id))
            ->findOrFail($id);
    }

    public function create(User $actor, array $data): Employee
    {
        return DB::transaction(function () use ($actor, $data): Employee {
            $employee = Employee::query()->create($this->attributes($actor, $data));
            $employee->load(['clinic', 'user']);
            $this->audit->record(
                'employee.created',
                $employee,
                [],
                $this->snapshot($employee),
                $actor,
                clinicId: $employee->clinic_id,
                subjectLabel: $employee->name
            );

            return $employee;
        });
    }

    public function update(User $actor, Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($actor, $employee, $data): Employee {
            $before = $this->snapshot($employee);
            $employee->update($this->attributes($actor, $data, $employee));
            $employee->load(['clinic', 'user']);
            $this->audit->record(
                'employee.updated',
                $employee,
                $before,
                $this->snapshot($employee),
                $actor,
                clinicId: $employee->clinic_id,
                subjectLabel: $employee->name
            );

            return $employee;
        });
    }

    private function attributes(User $actor, array $data, ?Employee $employee = null): array
    {
        $clinicId = $actor->clinic_id ?? (int) $data['clinic_id'];
        $paymentMethod = $data['payment_method'] ?? null;
        $paymentDetails = $paymentMethod ? array_filter([
            'pix_key' => $data['pix_key'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_branch' => $data['bank_branch'] ?? null,
            'bank_account' => $data['bank_account'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
        ], fn (mixed $value): bool => filled($value)) : null;

        return [
            'clinic_id' => $clinicId,
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'position' => $data['position'] ?? null,
            'document' => array_key_exists('document', $data) ? $data['document'] : $employee?->document,
            'employment_type' => $data['employment_type'],
            'hire_date' => $data['hire_date'] ?? null,
            'termination_date' => $data['termination_date'] ?? null,
            'base_compensation' => filled($data['base_compensation'] ?? null) ? $data['base_compensation'] : null,
            'payment_method' => $paymentMethod,
            'payment_details' => $paymentDetails,
            'active' => (bool) $data['active'],
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function snapshot(Employee $employee): array
    {
        return [
            'clinic_id' => $employee->clinic_id,
            'user_id' => $employee->user_id,
            'name' => $employee->name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'position' => $employee->position,
            'employment_type' => $employee->employment_type,
            'hire_date' => $employee->hire_date?->toDateString(),
            'termination_date' => $employee->termination_date?->toDateString(),
            'base_compensation' => $employee->base_compensation,
            'active' => (bool) $employee->active,
            'has_document' => filled($employee->document),
            'payment' => $employee->paymentSnapshot(),
        ];
    }
}
