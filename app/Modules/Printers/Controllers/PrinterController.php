<?php

namespace App\Modules\Printers\Controllers;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Printers\Requests\SavePrinterRequest;
use App\Modules\Printers\Services\PrinterService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrinterController
{
    public function __construct(
        private readonly PrinterService $printers,
        private readonly TenantContext $tenant
    ) {}

    public function index(Request $request): View
    {
        $clinicId = $this->selectedClinicId($request);

        return view('printers.index', $this->viewData([
            'printers' => $this->printers->paginate($clinicId),
        ], $clinicId));
    }

    public function create(Request $request): View
    {
        $clinicId = $this->selectedClinicId($request);

        return view('printers.create', $this->viewData(['printer' => null], $clinicId));
    }

    public function store(SavePrinterRequest $request): RedirectResponse
    {
        $clinicId = $this->targetClinicId($request);
        $this->printers->create(array_replace($request->validated(), ['clinic_id' => $clinicId]));

        return redirect()->route('printers.index', $this->routeClinic($clinicId))->with('success', 'Impressora cadastrada com sucesso.');
    }

    public function edit(Request $request, int $printer): View
    {
        $clinicId = $this->selectedClinicId($request);

        return view('printers.edit', $this->viewData([
            'printer' => $this->printers->find($printer, $clinicId),
        ], $clinicId));
    }

    public function update(SavePrinterRequest $request, int $printer): RedirectResponse
    {
        $clinicId = $this->targetClinicId($request);
        $this->printers->update(
            $this->printers->find($printer, $clinicId),
            array_replace($request->validated(), ['clinic_id' => $clinicId])
        );

        return redirect()->route('printers.index', $this->routeClinic($clinicId))->with('success', 'Configuração da impressora atualizada.');
    }

    public function test(Request $request, int $printer): View
    {
        $clinicId = $this->selectedClinicId($request);

        return view('printers.test', $this->viewData([
            'printer' => $this->printers->find($printer, $clinicId),
        ], $clinicId));
    }

    /** @param array<string, mixed> $data */
    private function viewData(array $data, int $clinicId): array
    {
        return $data + [
            'types' => PrinterService::types(),
            'purposes' => PrinterService::purposes(),
            'connections' => PrinterService::connections(),
            'paperSizes' => PrinterService::paperSizes(),
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ];
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

    private function targetClinicId(SavePrinterRequest $request): int
    {
        return $this->selectedClinicId($request);
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
}
