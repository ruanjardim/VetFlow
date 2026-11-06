<?php

namespace App\Modules\Saas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Saas\Models\Coupon;
use App\Modules\Saas\Models\LegalDocument;
use App\Modules\Saas\Models\Plan;
use App\Modules\Saas\Models\SubscriptionPromotion;
use App\Modules\Saas\Requests\ApplyCouponRequest;
use App\Modules\Saas\Requests\CancelPromotionRequest;
use App\Modules\Saas\Requests\CouponRequest;
use App\Modules\Saas\Requests\LegalDocumentRequest;
use App\Modules\Saas\Requests\OnboardTenantRequest;
use App\Modules\Saas\Requests\PlanRequest;
use App\Modules\Saas\Requests\PublishLegalDocumentRequest;
use App\Modules\Saas\Requests\SubscriptionRequest;
use App\Modules\Saas\Services\CouponService;
use App\Modules\Saas\Services\LegalDocumentService;
use App\Modules\Saas\Services\SaasAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SaasAdminController extends Controller
{
    public function __construct(
        private readonly SaasAdministrationService $service,
        private readonly CouponService $coupons,
        private readonly LegalDocumentService $legal
    ) {}

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

    public function applyCoupon(ApplyCouponRequest $request, Clinic $clinic): RedirectResponse
    {
        $subscription = $clinic->subscription()->firstOrFail();
        $this->coupons->apply($subscription, $request->validated('coupon_code'), $request->user());

        return back()->with('success', 'Cupom aplicado à assinatura com preço e vigência congelados.');
    }

    public function cancelPromotion(
        CancelPromotionRequest $request,
        Clinic $clinic,
        SubscriptionPromotion $promotion
    ): RedirectResponse {
        abort_unless($promotion->clinic_id === $clinic->id, 404);
        $this->coupons->cancel($promotion, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Promoção cancelada sem apagar o histórico.');
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

    public function coupons(): View
    {
        return view('saas.coupons.index', ['coupons' => $this->coupons->paginate()]);
    }

    public function createCoupon(): View
    {
        return view('saas.coupons.create', $this->coupons->formData());
    }

    public function storeCoupon(CouponRequest $request): RedirectResponse
    {
        $coupon = $this->coupons->save($request->validated(), $request->user());

        return redirect()->route('saas.coupons.edit', $coupon)->with('success', 'Cupom criado com sucesso.');
    }

    public function editCoupon(Coupon $coupon): View
    {
        $coupon->load('plans')->loadCount('promotions');

        return view('saas.coupons.edit', ['coupon' => $coupon, ...$this->coupons->formData()]);
    }

    public function updateCoupon(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->coupons->save($request->validated(), $request->user(), $coupon);

        return back()->with('success', 'Cupom atualizado com sucesso.');
    }

    public function legalDocuments(): View
    {
        return view('saas.legal.index', ['documents' => $this->legal->documents()]);
    }

    public function createLegalDocument(): View
    {
        return view('saas.legal.create', ['types' => LegalDocument::TYPES]);
    }

    public function storeLegalDocument(LegalDocumentRequest $request): RedirectResponse
    {
        $document = $this->legal->saveDraft($request->validated(), $request->user());

        return redirect()->route('saas.legal.index')->with('success', 'Nova versão criada como minuta: '.$document->version.'.');
    }

    public function publishLegalDocument(
        PublishLegalDocumentRequest $request,
        LegalDocument $legalDocument
    ): RedirectResponse {
        $this->legal->publish($legalDocument, $request->user(), $request->validated('reviewer_name'));

        return back()->with('success', 'Documento publicado após confirmação explícita da revisão jurídica.');
    }
}
