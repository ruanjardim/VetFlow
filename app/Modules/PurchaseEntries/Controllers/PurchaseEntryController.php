<?php

namespace App\Modules\PurchaseEntries\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Products\Models\Product;
use App\Modules\PurchaseEntries\Exceptions\NfeAccessKeyLookupException;
use App\Modules\PurchaseEntries\Services\NfeAccessKeyImportService;
use App\Modules\PurchaseEntries\Services\NfeXmlImportService;
use App\Modules\PurchaseEntries\Requests\StorePurchaseEntryRequest;
use App\Modules\PurchaseEntries\Requests\UpdatePurchaseEntryRequest;
use App\Modules\PurchaseEntries\Services\PurchaseEntryInsightService;
use App\Modules\PurchaseEntries\Services\PurchaseEntryService;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

class PurchaseEntryController extends Controller
{
    public function __construct(
        private readonly PurchaseEntryService $service,
        private readonly PurchaseEntryInsightService $insights
    )
    {
    }

    public function index()
    {
        return view('purchase-entries.index', [
            'purchaseEntries' => $this->service->paginate(),
            'purchaseInsights' => $this->insights->dashboard(),
        ]);
    }

    public function create(Request $request)
    {
        return view('purchase-entries.create', [
            'entry' => null,
            'clinics' => $this->clinics(),
            'products' => $this->products(),
            'suppliers' => $this->suppliers(),
            'purchaseInsights' => $this->insights->dashboard(),
            'scanGtin' => $request->query('scan') ?: $request->query('gtin'),
        ]);
    }

    public function store(StorePurchaseEntryRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route('purchase-entries.index')
            ->with('success', 'Entrada de mercadorias criada com sucesso.');
    }

    public function edit(int $id)
    {
        return view('purchase-entries.edit', [
            'entry' => $this->service->findOrFail($id),
            'clinics' => $this->clinics(),
            'products' => $this->products(),
            'suppliers' => $this->suppliers(),
            'purchaseInsights' => $this->insights->dashboard(),
            'scanGtin' => null,
        ]);
    }

    public function replenishment()
    {
        return view('purchase-entries.replenishment', $this->insights->replenishmentData());
    }

    public function lookupProduct(Request $request, string $gtin): JsonResponse
    {
        $validated = $request->validate([
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
        ]);

        $result = $this->insights->lookupPayload($gtin, $this->selectedClinicId($request, $validated));

        return response()->json($result['payload'], $result['status']);
    }

    public function importNfeXml(
        Request $request,
        NfeXmlImportService $importer
    ): JsonResponse
    {
        $validated = $request->validate([
            'clinic_id' => [Rule::requiredIf($request->user()?->clinic_id === null), 'nullable', 'integer', 'exists:clinics,id'],
            'xml_file' => ['required', 'file', 'max:5120'],
            'create_missing_products' => ['nullable', 'boolean'],
            'create_missing_supplier' => ['nullable', 'boolean'],
        ]);

        $content = file_get_contents($validated['xml_file']->getRealPath());

        try {
            $payload = $importer->import(
                $content ?: '',
                $request->boolean('create_missing_products', true),
                $request->boolean('create_missing_supplier', true),
                $this->selectedClinicId($request, $validated)
            );

            $this->rememberNfeXmlInKeyCache($payload['invoice']['access_key'] ?? null, $content ?: '');

            return response()->json($payload);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'found' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Falha ao importar XML da NF-e.', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return response()->json([
                'found' => false,
                'message' => 'Nao consegui importar este XML agora: '.$exception->getMessage(),
            ], 500);
        }
    }

    public function importNfeKey(Request $request, NfeAccessKeyImportService $importer): JsonResponse
    {
        $validated = $request->validate([
            'clinic_id' => [Rule::requiredIf($request->user()?->clinic_id === null), 'nullable', 'integer', 'exists:clinics,id'],
            'access_key' => ['required', 'string', 'regex:/^\D*\d(?:\D*\d){43}\D*$/'],
            'create_missing_products' => ['nullable', 'boolean'],
            'create_missing_supplier' => ['nullable', 'boolean'],
        ]);

        try {
            return response()->json($importer->import(
                $validated['access_key'],
                $request->boolean('create_missing_products', true),
                $request->boolean('create_missing_supplier', true),
                $this->selectedClinicId($request, $validated)
            ));
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'found' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (NfeAccessKeyLookupException $exception) {
            Log::error('Falha ao importar NF-e pela chave.', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
                'diagnostics' => $exception->diagnostics(),
            ]);

            return response()->json([
                'found' => false,
                'message' => $exception->getMessage(),
                'diagnostics' => $exception->diagnostics(),
            ], 404);
        } catch (Throwable $exception) {
            Log::error('Falha ao importar NF-e pela chave.', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return response()->json([
                'found' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function update(UpdatePurchaseEntryRequest $request, int $id): RedirectResponse
    {
        $this->service->update($id, $request->validated());

        return redirect()
            ->route('purchase-entries.index')
            ->with('success', 'Entrada de mercadorias atualizada com sucesso.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->service->delete($id);

        return redirect()
            ->route('purchase-entries.index')
            ->with('success', 'Entrada de mercadorias removida com sucesso.');
    }

    private function clinics()
    {
        return Clinic::query()
            ->orderBy('trade_name')
            ->orderBy('corporate_name')
            ->get();
    }

    private function products()
    {
        return Product::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    private function suppliers()
    {
        return Supplier::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    private function selectedClinicId(Request $request, array $validated = []): ?int
    {
        $userClinicId = $request->user()?->clinic_id;

        if ($userClinicId !== null) {
            return (int) $userClinicId;
        }

        $clinicId = $validated['clinic_id'] ?? $request->input('clinic_id');

        return $clinicId !== null && $clinicId !== '' ? (int) $clinicId : null;
    }

    private function rememberNfeXmlInKeyCache(?string $accessKey, string $content): void
    {
        if (! $accessKey || trim($content) === '') {
            return;
        }

        try {
            app(NfeAccessKeyImportService::class)->rememberXml($accessKey, $content);
        } catch (Throwable $exception) {
            Log::warning('XML da NF-e importado, mas nao foi salvo no cache de chave.', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
    }
}
