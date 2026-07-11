<?php

namespace App\Modules\Suppliers\Services;

use App\Core\Base\BaseService;
use App\Modules\Suppliers\Contracts\SupplierRepositoryInterface;

class SupplierService extends BaseService
{
    public function __construct(SupplierRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
}
