<?php

namespace App\Modules\Printers\Controllers;

use App\Modules\Printers\Requests\SavePrinterRequest;
use App\Modules\Printers\Services\PrinterService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PrinterController
{
    public function __construct(
        private readonly PrinterService $printers,
        private readonly TenantContext $tenant
    ) {}

    public function index(): View
    {
        $this->ensureClinicUser();

        return view('printers.index', $this->viewData([
            'printers' => $this->printers->paginate(),
        ]));
    }

    public function create(): View
    {
        $this->ensureClinicUser();

        return view('printers.create', $this->viewData(['printer' => null]));
    }

    public function store(SavePrinterRequest $request): RedirectResponse
    {
        $this->ensureClinicUser();
        $this->printers->create($request->validated());

        return redirect()->route('printers.index')->with('success', 'Impressora cadastrada com sucesso.');
    }

    public function edit(int $printer): View
    {
        $this->ensureClinicUser();

        return view('printers.edit', $this->viewData([
            'printer' => $this->printers->find($printer),
        ]));
    }

    public function update(SavePrinterRequest $request, int $printer): RedirectResponse
    {
        $this->ensureClinicUser();
        $this->printers->update($this->printers->find($printer), $request->validated());

        return redirect()->route('printers.index')->with('success', 'Configuração da impressora atualizada.');
    }

    public function test(int $printer): View
    {
        $this->ensureClinicUser();

        return view('printers.test', $this->viewData([
            'printer' => $this->printers->find($printer),
        ]));
    }

    /** @param array<string, mixed> $data */
    private function viewData(array $data): array
    {
        return $data + [
            'types' => PrinterService::types(),
            'purposes' => PrinterService::purposes(),
            'connections' => PrinterService::connections(),
            'paperSizes' => PrinterService::paperSizes(),
        ];
    }

    private function ensureClinicUser(): void
    {
        abort_if($this->tenant->isGlobal(), 403);
    }
}
