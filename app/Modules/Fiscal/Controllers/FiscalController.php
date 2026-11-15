<?php

namespace App\Modules\Fiscal\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Requests\ActivateFiscalProductionRequest;
use App\Modules\Fiscal\Requests\ApproveFiscalSaleAllocationRequest;
use App\Modules\Fiscal\Requests\CancelFiscalDocumentRequest;
use App\Modules\Fiscal\Requests\ConnectFocusCredentialRequest;
use App\Modules\Fiscal\Requests\DeactivateFiscalProductionRequest;
use App\Modules\Fiscal\Requests\DisconnectFocusCredentialRequest;
use App\Modules\Fiscal\Requests\InutilizeNfceRangeRequest;
use App\Modules\Fiscal\Requests\PrepareFiscalDocumentRequest;
use App\Modules\Fiscal\Requests\StoreFiscalHomologationReviewRequest;
use App\Modules\Fiscal\Requests\UpdateFiscalProfileRequest;
use App\Modules\Fiscal\Services\FiscalDocumentLifecycleService;
use App\Modules\Fiscal\Services\FiscalDocumentService;
use App\Modules\Fiscal\Services\FiscalDpsSequenceService;
use App\Modules\Fiscal\Services\FiscalHomologationReadinessService;
use App\Modules\Fiscal\Services\FiscalNfceInutilizationService;
use App\Modules\Fiscal\Services\FiscalNfceSequenceService;
use App\Modules\Fiscal\Services\FiscalOperationsAlertService;
use App\Modules\Fiscal\Services\FiscalProductionActivationService;
use App\Modules\Fiscal\Services\FiscalProfileService;
use App\Modules\Fiscal\Services\FiscalProviderCredentialService;
use App\Modules\Fiscal\Services\FiscalSaleAllocationService;
use App\Modules\Fiscal\Services\FocusNfcePayloadService;
use App\Modules\Fiscal\Services\FocusNfePayloadService;
use App\Modules\Fiscal\Support\FiscalDocumentType;
use App\Modules\Fiscal\Support\FiscalMunicipalityCatalog;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalController
{
    public function __construct(
        private readonly FiscalProfileService $profiles,
        private readonly FiscalDocumentService $documents,
        private readonly FiscalDocumentLifecycleService $lifecycle,
        private readonly FiscalDpsSequenceService $dpsSequences,
        private readonly FiscalNfceSequenceService $nfceSequences,
        private readonly FiscalNfceInutilizationService $nfceInutilizations,
        private readonly FiscalOperationsAlertService $operationsAlerts,
        private readonly FiscalHomologationReadinessService $homologationReadiness,
        private readonly FiscalProviderCredentialService $focusCredentials,
        private readonly FiscalProductionActivationService $productionActivations,
        private readonly FiscalSaleAllocationService $saleAllocations,
        private readonly FocusNfePayloadService $focusPayloads,
        private readonly FocusNfcePayloadService $focusNfcePayloads,
        private readonly TenantContext $tenant
    ) {}

    public function index(Request $request): View
    {
        $clinicId = $this->selectedClinicId($request);
        $clinic = Clinic::query()->findOrFail($clinicId);
        $profile = $this->profiles->forClinic($clinicId);
        $nfceSequence = $this->nfceSequences->currentFor($profile);
        $homologationReadiness = $this->homologationReadiness->evaluate($clinic, $profile);

        return view('fiscal.index', [
            'profile' => $profile,
            'documents' => FiscalDocument::query()
                ->with(['sale', 'creator'])
                ->where('clinic_id', $clinicId)
                ->latest()
                ->paginate(20),
            'types' => FiscalDocumentType::labels(),
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
            'taxCatalogStats' => $this->taxCatalogStats($clinicId),
            'municipalityReadiness' => $homologationReadiness['municipality_readiness'],
            'nfceReadiness' => $homologationReadiness['nfce_readiness'],
            'runtimeReadiness' => $homologationReadiness['runtime_readiness'],
            'homologationReadiness' => $homologationReadiness,
            'externalOperationsAvailable' => $this->lifecycle->isAvailable(),
            'fiscalAlerts' => $this->operationsAlerts->forClinic($clinicId),
            'municipalitySuggestions' => FiscalMunicipalityCatalog::suggestions(),
            'providerOptions' => FiscalProviderCatalog::options(),
            'providerLabel' => FiscalProviderCatalog::label($profile->provider),
            'dpsSequence' => $this->dpsSequences->currentFor($profile),
            'nfceSequence' => $nfceSequence,
            'nfceInutilizations' => $this->nfceInutilizations->recentForClinic($clinicId),
            'nfceInutilizationAvailable' => $this->nfceInutilizations->isAvailable($profile),
            'focusCredential' => $this->focusCredentials->activeForClinic($clinicId),
            'productionFocusCredential' => $this->focusCredentials->activeForClinic($clinicId, 'production'),
            'productionActivation' => $this->productionActivations->activeForClinic($clinicId),
            'productionRuntimeEnabled' => (bool) config('fiscal.production.enabled', false),
            'customerManagedFocusCredentials' => $this->focusCredentials->customerManagedMode(),
        ]);
    }

    public function update(UpdateFiscalProfileRequest $request): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $this->profiles->update($clinicId, $request->validated());

        return redirect()
            ->route('fiscal.index', $this->routeClinic($clinicId))
            ->with('success', 'Configuração fiscal de homologação atualizada.');
    }

    public function connectFocusCredential(ConnectFocusCredentialRequest $request): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $this->focusCredentials->connect(
            $clinicId,
            $request->validated('focus_token'),
            $request->user(),
            $request->validated('environment')
        );

        $environment = $request->validated('environment');

        return redirect()
            ->route('fiscal.index', $this->routeClinic($clinicId))
            ->with('success', 'Conta Focus de '.$environment.' conectada. O token foi criptografado e não será exibido novamente.');
    }

    public function disconnectFocusCredential(DisconnectFocusCredentialRequest $request): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $this->focusCredentials->disconnect(
            $clinicId,
            $request->user(),
            $request->validated('environment')
        );

        return redirect()
            ->route('fiscal.index', $this->routeClinic($clinicId))
            ->with('success', 'Conta Focus desconectada. Novas transmissões fiscais desta clínica estão bloqueadas.');
    }

    public function activateProduction(ActivateFiscalProductionRequest $request): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $clinic = Clinic::query()->findOrFail($clinicId);
        $profile = $this->profiles->forClinic($clinicId);
        $this->productionActivations->activate($clinic, $profile, $request->user(), $request->validated());

        return redirect()
            ->route('fiscal.index', $this->routeClinic($clinicId))
            ->with('success', 'Produção fiscal ativada para esta clínica. As próximas preparações usarão documentos com validade fiscal.');
    }

    public function deactivateProduction(DeactivateFiscalProductionRequest $request): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $clinic = Clinic::query()->findOrFail($clinicId);
        $profile = $this->profiles->forClinic($clinicId);
        $this->productionActivations->deactivate(
            $clinic,
            $profile,
            $request->user(),
            $request->validated('reason')
        );

        return redirect()
            ->route('fiscal.index', $this->routeClinic($clinicId))
            ->with('success', 'Produção fiscal desativada. Novas preparações voltaram para homologação.');
    }

    public function storeHomologationReview(StoreFiscalHomologationReviewRequest $request): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $clinic = Clinic::query()->findOrFail($clinicId);
        $profile = $this->profiles->forClinic($clinicId);
        $this->homologationReadiness->record($clinic, $profile, $request->user(), $request->validated());

        return redirect()
            ->route('fiscal.index', $this->routeClinic($clinicId))
            ->with('success', 'Revisão de homologação registrada. Produção continua bloqueada e exige uma etapa técnica separada.');
    }

    public function homologationReport(Request $request): View
    {
        [$clinic, $profile] = $this->homologationReportContext($request);

        return view('fiscal.homologation-report', [
            'report' => $this->homologationReadiness->report($clinic, $profile),
            'requiresClinic' => $this->tenant->isGlobal(),
            'selectedClinicId' => (int) $clinic->id,
        ]);
    }

    public function homologationReportJson(Request $request): StreamedResponse
    {
        [$clinic, $profile] = $this->homologationReportContext($request);
        $report = $this->homologationReadiness->report($clinic, $profile);
        $fileName = 'vetflow-homologacao-fiscal-'
            .Str::slug($clinic->trade_name ?: $clinic->corporate_name)
            .'-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(
            fn () => print json_encode(
                $report,
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            ),
            $fileName,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function prepare(PrepareFiscalDocumentRequest $request, int $sale): RedirectResponse
    {
        $saleModel = Sale::query()->findOrFail($sale);
        $document = $this->documents->prepareFromSale($saleModel, $request->validated('type'), $request->user());

        return redirect()
            ->route('fiscal.documents.show', $document->id)
            ->with('success', 'Documento preparado em '.$document->environment.'. Nenhum envio externo foi realizado.');
    }

    public function show(int $document): View
    {
        $document = FiscalDocument::query()
            ->with(['sale', 'creator', 'events.actor'])
            ->findOrFail($document);

        return view('fiscal.show', [
            'document' => $document,
            'types' => FiscalDocumentType::labels(),
            'providerLabel' => FiscalProviderCatalog::label($document->provider),
            'submissionAvailable' => $this->lifecycle->isAvailable($document),
            'webhookRecoveryAvailable' => $this->lifecycle->isWebhookRecoveryAvailable($document),
            'allocationReview' => $document->sale ? $this->saleAllocations->review($document->sale) : null,
        ]);
    }

    public function approveSaleAllocation(ApproveFiscalSaleAllocationRequest $request, int $sale): RedirectResponse
    {
        $saleModel = Sale::query()->findOrFail($sale);
        $allocation = $this->saleAllocations->approve($saleModel, $request->user());
        $document = $allocation->documents->sortBy(fn (FiscalDocument $item): int => $item->type === 'nfce' ? 0 : 1)->first();

        return redirect()
            ->route('fiscal.documents.show', $document->id)
            ->with('success', 'Rateio fiscal aprovado e congelado. Os documentos agora compartilham o mesmo fechamento em centavos.');
    }

    public function generatePayload(Request $request, int $document): RedirectResponse
    {
        $document = FiscalDocument::query()->findOrFail($document);
        if ($document->type === FiscalDocumentType::NFCE) {
            $this->focusNfcePayloads->generate($document, $request->user());
            $message = 'Payload NFC-e modelo 65 gerado e número reservado. Nenhuma transmissão externa foi realizada.';
        } else {
            $this->focusPayloads->generate($document, $request->user());
            $message = 'Payload NFS-e gerado e número da DPS reservado. Nenhuma transmissão externa foi realizada.';
        }

        return redirect()
            ->route('fiscal.documents.show', $document->id)
            ->with('success', $message);
    }

    public function queueSubmission(Request $request, int $document): RedirectResponse
    {
        $document = FiscalDocument::query()->findOrFail($document);
        $this->lifecycle->queueForSubmission($document, $request->user());

        return redirect()
            ->route('fiscal.documents.show', $document->id)
            ->with('success', 'Documento colocado na fila fiscal de '.$document->environment.'. O processamento ocorrerá em segundo plano.');
    }

    public function queueSynchronization(Request $request, int $document): RedirectResponse
    {
        $document = FiscalDocument::query()->findOrFail($document);
        $this->lifecycle->queueSynchronization($document, $request->user());

        return redirect()
            ->route('fiscal.documents.show', $document->id)
            ->with('success', 'Consulta de situação colocada na fila fiscal.');
    }

    public function queueCancellation(CancelFiscalDocumentRequest $request, int $document): RedirectResponse
    {
        $document = FiscalDocument::query()->findOrFail($document);
        $this->lifecycle->queueCancellation(
            $document,
            $request->user(),
            $request->validated('cancellation_reason')
        );

        return redirect()
            ->route('fiscal.documents.show', $document->id)
            ->with('success', 'Cancelamento colocado na fila fiscal. O documento só será marcado como cancelado após a confirmação do provedor.');
    }

    public function queueNfceInutilization(InutilizeNfceRangeRequest $request): RedirectResponse
    {
        $clinicId = $this->selectedClinicId($request);
        $clinic = Clinic::query()->findOrFail($clinicId);
        $profile = $this->profiles->forClinic($clinicId);
        $this->nfceInutilizations->queue($profile, $clinic, $request->user(), [
            'number_start' => (int) $request->validated('number_start'),
            'number_end' => (int) $request->validated('number_end'),
            'justification' => $request->validated('justification'),
        ]);

        return redirect()
            ->route('fiscal.index', $this->routeClinic($clinicId))
            ->with('success', 'Inutilização colocada na fila fiscal de '.$profile->environment.'. Aguarde a confirmação da SEFAZ.');
    }

    public function queueWebhookResend(Request $request, int $document): RedirectResponse
    {
        $document = FiscalDocument::query()->findOrFail($document);
        $this->lifecycle->queueWebhookResend($document, $request->user());

        return redirect()
            ->route('fiscal.documents.show', $document->id)
            ->with('success', 'Solicitação de reenvio do webhook colocada na fila fiscal.');
    }

    public function downloadArtifact(Request $request, int $document, string $artifact): Response
    {
        $document = FiscalDocument::query()->findOrFail($document);
        $download = $this->lifecycle->downloadArtifact($document, $request->user(), $artifact);

        return response($download['content'], 200, [
            'Content-Type' => $download['content_type'],
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $download['filename']
            ),
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function selectedClinicId(Request $request): int
    {
        if (! $this->tenant->isGlobal()) {
            return (int) $this->tenant->clinicId();
        }

        $requestedId = (int) $request->input('clinic_id');
        $clinicId = Clinic::query()
            ->where('active', true)
            ->when($requestedId > 0, fn ($query) => $query->whereKey($requestedId))
            ->orderBy('trade_name')
            ->value('id');

        abort_if(! $clinicId, 404, 'Nenhum estabelecimento ativo disponível.');

        return (int) $clinicId;
    }

    /** @return array{Clinic, FiscalProfile} */
    private function homologationReportContext(Request $request): array
    {
        $clinicId = $this->selectedClinicId($request);

        return [
            Clinic::query()->findOrFail($clinicId),
            $this->profiles->forClinic($clinicId),
        ];
    }

    private function availableClinics()
    {
        return $this->tenant->isGlobal()
            ? Clinic::query()->where('active', true)->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
    }

    /** @return array<string, int> */
    private function routeClinic(int $clinicId): array
    {
        return $this->tenant->isGlobal() ? ['clinic_id' => $clinicId] : [];
    }

    /** @return array<string, int> */
    private function taxCatalogStats(int $clinicId): array
    {
        $products = Product::query()->where('clinic_id', $clinicId)->where('active', true);
        $services = PetShopService::query()->where('clinic_id', $clinicId)->where('active', true);
        $productTotal = (clone $products)->count();
        $serviceTotal = (clone $services)->count();
        $productReady = (clone $products)->whereHas('fiscalProfile', fn ($query) => $query->where('ready', true))->count();
        $serviceReady = (clone $services)->whereHas('fiscalProfile', fn ($query) => $query->where('ready', true))->count();

        return [
            'product_total' => $productTotal,
            'product_ready' => $productReady,
            'product_pending' => $productTotal - $productReady,
            'service_total' => $serviceTotal,
            'service_ready' => $serviceReady,
            'service_pending' => $serviceTotal - $serviceReady,
        ];
    }
}
