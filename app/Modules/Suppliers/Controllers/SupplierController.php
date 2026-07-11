<?php

namespace App\Modules\Suppliers\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Suppliers\Requests\StoreSupplierRequest;
use App\Modules\Suppliers\Requests\UpdateSupplierRequest;
use App\Modules\Suppliers\Services\SupplierService;

class SupplierController extends BaseCrudController
{
    public function __construct(SupplierService $service)
    {
        $this->service = $service;
        $this->viewPath = 'suppliers';
        $this->routeName = 'suppliers';
        $this->viewVariable = 'suppliers';
    }

    protected function storeRequest(): string
    {
        return StoreSupplierRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateSupplierRequest::class;
    }
}
