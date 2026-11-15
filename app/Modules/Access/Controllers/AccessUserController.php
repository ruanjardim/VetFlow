<?php

namespace App\Modules\Access\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Requests\StoreAccessUserRequest;
use App\Modules\Access\Requests\UpdateAccessUserRequest;
use App\Modules\Access\Services\AccessUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessUserController extends Controller
{
    public function __construct(
        private readonly AccessUserService $service
    ) {}

    public function index(Request $request): View
    {
        return view('access.users.index', [
            'users' => $this->service->paginate($request->user()),
            'licenseUsage' => $this->service->licenseUsage($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('access.users.create', $this->service->formData(
            $request->user(),
            preferredClinicId: $request->integer('clinic_id') ?: null,
            returnTo: $request->string('return_to')->toString(),
            preferredEmployeeId: $request->integer('employee_id') ?: null,
        ));
    }

    public function store(StoreAccessUserRequest $request): RedirectResponse
    {
        $createdUser = $this->service->create($request->user(), $request->validated());

        if ($request->validated('return_to') === 'implementation') {
            return redirect()
                ->route('implementation.index')
                ->with('success', 'Colaborador criado. Continue a ativação da equipe desta clínica.');
        }

        if ($request->filled('employee_id') && $createdUser->employee) {
            return redirect()
                ->route('employees.edit', $createdUser->employee->id)
                ->with('success', 'Acesso criado e vinculado ao cadastro funcional existente.');
        }

        return redirect()
            ->route('access-users.index')
            ->with('success', 'Colaborador criado com os perfis selecionados.');
    }

    public function edit(Request $request, int $user): View
    {
        $accessUser = $this->service->find($request->user(), $user);

        return view(
            'access.users.edit',
            $this->service->formData($request->user(), $accessUser)
        );
    }

    public function update(UpdateAccessUserRequest $request, int $user): RedirectResponse
    {
        $accessUser = $this->service->find($request->user(), $user);
        $this->service->update($request->user(), $accessUser, $request->validated());

        return redirect()
            ->route('access-users.index')
            ->with('success', 'Acesso do colaborador atualizado.');
    }
}
