<?php

namespace App\Modules\ProductIntelligence\Services;

use App\Modules\ProductIntelligence\Models\GlobalProduct;
use Illuminate\Http\Request;

class GlobalProductCatalogService
{
    public function indexData(Request $request): array
    {
        $query = GlobalProduct::query()
            ->withCount(['sources', 'products'])
            ->latest('updated_at');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('gtin', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('manufacturer', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return [
            'globalProducts' => $query->paginate(15)->withQueryString(),
            'stats' => $this->stats(),
            'statuses' => $this->statuses(),
        ];
    }

    public function statuses(): array
    {
        return [
            GlobalProduct::STATUS_PENDING => 'Pendente',
            GlobalProduct::STATUS_VERIFIED => 'Verificado',
            GlobalProduct::STATUS_CONFLICT => 'Conflito',
        ];
    }

    public function updateStatus(GlobalProduct $globalProduct, string $status): void
    {
        $globalProduct->update(['status' => $status]);
    }

    private function stats(): array
    {
        return [
            'total' => GlobalProduct::query()->count(),
            'pending' => GlobalProduct::query()->where('status', GlobalProduct::STATUS_PENDING)->count(),
            'verified' => GlobalProduct::query()->where('status', GlobalProduct::STATUS_VERIFIED)->count(),
            'conflict' => GlobalProduct::query()->where('status', GlobalProduct::STATUS_CONFLICT)->count(),
        ];
    }
}
