<?php

namespace App\Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Requests\StoreEmployeeRequest;
use App\Modules\Employees\Requests\UpdateEmployeeRequest;
use App\Modules\Employees\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employees) {}

    public function index(Request $request): View
    {
        return view('employees.index', [
            'employees' => $this->employees->paginate($request->user(), $request->integer('clinic_id') ?: null),
            'clinics' => $this->clinics($request->user()),
            'selectedClinicId' => $request->integer('clinic_id') ?: null,
            'employmentTypes' => Employee::EMPLOYMENT_TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        return view('employees.create', $this->formData($request->user(), null, $request->integer('clinic_id') ?: null));
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->employees->create($request->user(), $request->validated());

        return redirect()->route('employees.index', array_filter([
            'clinic_id' => $request->user()->clinic_id === null ? $request->integer('clinic_id') : null,
        ]))->with('success', 'Colaborador cadastrado com dados funcionais e de pagamento.');
    }

    public function edit(Request $request, int $employee): View
    {
        $record = $this->employees->find($request->user(), $employee);

        return view('employees.edit', $this->formData($request->user(), $record, $record->clinic_id));
    }

    public function update(UpdateEmployeeRequest $request, int $employee): RedirectResponse
    {
        $record = $this->employees->find($request->user(), $employee);
        $this->employees->update($request->user(), $record, $request->validated());

        return redirect()->route('employees.index', array_filter([
            'clinic_id' => $request->user()->clinic_id === null ? $record->clinic_id : null,
        ]))->with('success', 'Cadastro funcional atualizado.');
    }

    private function formData(User $actor, ?Employee $employee, ?int $clinicId): array
    {
        $clinicId = $actor->clinic_id ?? $clinicId ?? Clinic::query()->active()->orderBy('trade_name')->value('id');

        return [
            'employee' => $employee,
            'clinics' => $this->clinics($actor),
            'selectedClinicId' => $clinicId,
            'users' => User::query()->where('clinic_id', $clinicId)->orderByDesc('active')->orderBy('name')->get(),
            'employmentTypes' => Employee::EMPLOYMENT_TYPES,
            'paymentMethods' => Employee::PAYMENT_METHODS,
        ];
    }

    private function clinics(User $actor)
    {
        return $actor->clinic_id === null
            ? Clinic::query()->active()->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
    }
}
