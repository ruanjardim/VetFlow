<?php

namespace App\Modules\Saas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Requests\OnboardTenantRequest;
use App\Modules\Saas\Requests\PlanRequest;
use App\Modules\Saas\Requests\SubscriptionRequest;
use App\Modules\Saas\Services\SaasAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SaasAdminController extends Controller
{
    public function __construct(private readonly SaasAdministrationService $service) {}

    public function dashboard(): View
    {
        return view('saas.dashboard', $this->service->dashboardData());
    }

    public function plans(): View
    {
        return view('saas.plans.index', ['plans' => $this->service->paginatePlans()]);
    }

    public function createPlan(): View
    {
        return view('saas.plans.create', $this->service->planFormData());
    }

    public function storePlan(PlanRequest $request): RedirectResponse
    {
        $plan = $this->service->savePlan($request->validated(), $request->user());

        return redirect()->route('saas.plans.edit', $plan)->with('success', 'Plano criado com sucesso.');
    }

    public function editPlan(Plan $plan): View
    {
        abort_if($plan->internal, 403, 'O plano interno de compatibilidade não pode ser editado.');
        $plan->load('featureValues.feature');

        return view('saas.plans.edit', ['plan' => $plan, ...$this->service->planFormData()]);
    }

    public function updatePlan(PlanRequest $request, Plan $plan): RedirectResponse
    {
        abort_if($plan->internal, 403, 'O plano interno de compatibilidade não pode ser editado.');
        $this->service->savePlan($request->validated(), $request->user(), $plan);

        return back()->with('success', 'Plano atualizado com sucesso.');
    }

    public function establishments(): View
    {
        return view('saas.establishments.index', ['clinics' => $this->service->paginateClinics()]);
    }

    public function establishment(Clinic $clinic): View
    {
        return view('saas.establishments.show', $this->service->establishmentData($clinic));
    }

    public function updateSubscription(SubscriptionRequest $request, Clinic $clinic): RedirectResponse
    {
        $this->service->updateSubscription($clinic, $request->validated(), $request->user());

        return back()->with('success', 'Assinatura e exceções atualizadas.');
    }

    public function createOnboarding(): View
    {
        return view('saas.onboarding.create', $this->service->onboardingFormData());
    }

    public function storeOnboarding(OnboardTenantRequest $request): RedirectResponse
    {
        $clinic = $this->service->onboard($request->validated(), $request->user());

        return redirect()->route('saas.establishments.show', $clinic)->with('success', 'Estabelecimento implantado com assinatura e administrador inicial.');
    }
}
