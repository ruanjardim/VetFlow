<?php

namespace App\Modules\ServiceOrders\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Patients\Models\Patient;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\ServiceOrders\Requests\StoreServiceOrderRequest;
use App\Modules\ServiceOrders\Requests\UpdateServiceOrderRequest;
use App\Modules\ServiceOrders\Services\ServiceOrderService;
use App\Modules\Tutors\Models\Tutor;

class ServiceOrderController extends BaseCrudController
{
    public function __construct(ServiceOrderService $service)
    {
        $this->service = $service;
        $this->viewPath = 'service-orders';
        $this->routeName = 'service-orders';
        $this->viewVariable = 'serviceOrders';
    }

    public function create()
    {
        return view("{$this->viewPath}.create", $this->formData());
    }

    public function edit(int $id)
    {
        return view("{$this->viewPath}.edit", array_merge($this->formData(), [
            'item' => $this->service->findOrFail($id),
        ]));
    }

    protected function storeRequest(): string
    {
        return StoreServiceOrderRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateServiceOrderRequest::class;
    }

    private function formData(): array
    {
        return [
            'tutors' => Tutor::query()->orderBy('name')->get(),
            'patients' => Patient::query()->orderBy('name')->get(),
            'products' => Product::query()->active()->orderBy('name')->get(),
            'petShopServices' => PetShopService::query()->active()->orderBy('name')->get(),
        ];
    }
}
