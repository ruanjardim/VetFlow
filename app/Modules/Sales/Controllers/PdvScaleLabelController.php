<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Inventory\Services\ProductLotService;
use App\Modules\Printers\Models\Printer;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\PdvScaleSetting;
use App\Modules\Sales\Requests\PrintPdvScaleLabelRequest;
use App\Modules\Sales\Services\PdvScaleLabelService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PdvScaleLabelController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PdvScaleLabelService $labels,
        private readonly ProductLotService $lots
    ) {}

    public function create(Request $request): View
    {
        $clinic = $this->clinic($request, $request->integer('clinic_id'));
        $setting = $this->setting($clinic);
        $weightedProducts = Product::query()
            ->active()
            ->where('clinic_id', $clinic->id)
            ->where('unit', 'kg')
            ->whereNotNull('scale_code')
            ->where('scale_code', '<>', '')
            ->orderBy('name')
            ->get(['id', 'clinic_id', 'name', 'scale_code', 'unit', 'sale_price', 'stock_quantity', 'active']);

        $products = $weightedProducts
            ->filter(fn (Product $product): bool => $this->isReady($product, $setting))
            ->values();

        return view('sales.pdv-scale-label-create', [
            'clinic' => $clinic,
            'clinics' => $this->clinics(),
            'setting' => $setting,
            'requiresClinic' => $this->tenant->isGlobal(),
            'products' => $products,
            'unreadyProductsCount' => $weightedProducts->count() - $products->count(),
            'labelPrinter' => $this->labelPrinter($clinic),
        ]);
    }

    public function render(PrintPdvScaleLabelRequest $request): Response
    {
        $data = $request->validated();
        $clinic = $this->clinic($request, (int) $data['clinic_id']);
        $setting = PdvScaleSetting::query()
            ->where('clinic_id', $clinic->id)
            ->first();

        if (! $setting || ! $setting->enabled) {
            throw ValidationException::withMessages([
                'label' => 'Ative e salve a configuração de etiquetas de peso deste estabelecimento antes de imprimir.',
            ]);
        }

        $product = Product::query()
            ->active()
            ->where('clinic_id', $clinic->id)
            ->findOrFail((int) $data['product_id']);
        $weight = (float) $data['weight'];
        $sellableQuantity = $this->lots->sellableQuantity($product);

        if ($weight > $sellableQuantity + 0.0005) {
            throw ValidationException::withMessages([
                'weight' => 'O peso informado supera o estoque vendável de '.number_format($sellableQuantity, 3, ',', '.').' kg.',
            ]);
        }

        try {
            $ean13 = $this->labels->encode($setting, $product, $weight);
            $modules = $this->labels->barcodeModules($ean13);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['label' => $exception->getMessage()]);
        }

        $unitPrice = (float) $product->sale_price;

        return response()
            ->view('sales.pdv-scale-label-print', [
                'clinic' => $clinic,
                'product' => $product,
                'weight' => $weight,
                'unitPrice' => $unitPrice,
                'totalPrice' => round($weight * $unitPrice, 2),
                'ean13' => $ean13,
                'barcodeModules' => $modules,
                'copies' => (int) $data['copies'],
                'labelWidthMm' => (int) $data['label_width_mm'],
                'labelHeightMm' => (int) $data['label_height_mm'],
                'lot' => $data['lot'] ?? null,
                'expiresOn' => filled($data['expires_on'] ?? null)
                    ? CarbonImmutable::createFromFormat('Y-m-d', $data['expires_on'])
                    : null,
                'autoprint' => (bool) $data['autoprint'],
                'labelPrinter' => $this->labelPrinter($clinic),
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function setting(Clinic $clinic): PdvScaleSetting
    {
        return PdvScaleSetting::query()->firstOrNew(
            ['clinic_id' => $clinic->id],
            [
                'enabled' => false,
                'label_prefix' => '20',
                'product_code_digits' => 5,
                'weight_decimals' => 3,
            ]
        );
    }

    private function isReady(Product $product, PdvScaleSetting $setting): bool
    {
        return strlen((string) $product->scale_code) === (int) $setting->product_code_digits
            && (float) $product->sale_price > 0;
    }

    private function labelPrinter(Clinic $clinic): ?Printer
    {
        return Printer::query()
            ->where('clinic_id', $clinic->id)
            ->where('active', true)
            ->where(fn ($query) => $query->where('type', 'label')->orWhere('purpose', 'label'))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();
    }

    private function clinic(Request $request, ?int $clinicId): Clinic
    {
        $resolved = $request->user()?->clinic_id ?? $clinicId;

        if (! $resolved && $this->tenant->isGlobal()) {
            $resolved = Clinic::query()->active()->orderBy('trade_name')->value('id');
        }

        abort_if(! $resolved, 404, 'Nenhum estabelecimento ativo disponível.');

        return Clinic::query()->active()->findOrFail((int) $resolved);
    }

    private function clinics(): Collection
    {
        return $this->tenant->isGlobal()
            ? Clinic::query()->active()->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
    }
}
