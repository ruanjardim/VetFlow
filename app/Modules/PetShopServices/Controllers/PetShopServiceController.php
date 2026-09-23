<?php

namespace App\Modules\PetShopServices\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\PetShopServices\Requests\StorePetShopServiceRequest;
use App\Modules\PetShopServices\Requests\UpdatePetShopServiceRequest;
use App\Modules\PetShopServices\Services\PetShopServiceService;

class PetShopServiceController extends BaseCrudController
{
    public function __construct(PetShopServiceService $service)
    {
        $this->service = $service;
        $this->viewPath = 'petshop-services';
        $this->routeName = 'petshop-services';
        $this->viewVariable = 'petShopServices';
    }

    public function index()
    {
        $petShopServices = $this->service->paginate();
        $petShopServices->getCollection()->load('clinic');

        return view("{$this->viewPath}.index", compact('petShopServices'));
    }

    public function create()
    {
        return view("{$this->viewPath}.create", [
            'clinics' => $this->clinics(),
        ]);
    }

    public function edit(int $id)
    {
        return view("{$this->viewPath}.edit", [
            'item' => $this->service->findOrFail($id),
            'clinics' => $this->clinics(),
        ]);
    }

    protected function storeRequest(): string
    {
        return StorePetShopServiceRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdatePetShopServiceRequest::class;
    }

    private function clinics()
    {
        return Clinic::query()
            ->active()
            ->orderBy('trade_name')
            ->orderBy('corporate_name')
            ->get();
    }
}
