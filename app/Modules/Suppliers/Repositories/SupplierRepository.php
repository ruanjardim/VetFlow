<?php

namespace App\Modules\Suppliers\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Suppliers\Contracts\SupplierRepositoryInterface;
use App\Modules\Suppliers\Models\Supplier;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    public function __construct(Supplier $model)
    {
        $this->model = $model;
    }
}
