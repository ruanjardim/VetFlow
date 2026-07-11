<?php

namespace App\Modules\PetShopServices\Controllers;

use App\Core\Base\BaseCrudController;
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

    protected function storeRequest(): string
    {
        return StorePetShopServiceRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdatePetShopServiceRequest::class;
    }
}
