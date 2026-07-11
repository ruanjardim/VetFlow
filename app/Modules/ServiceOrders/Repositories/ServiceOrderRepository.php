<?php

namespace App\Modules\ServiceOrders\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\ServiceOrders\Contracts\ServiceOrderRepositoryInterface;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class ServiceOrderRepository extends BaseRepository implements ServiceOrderRepositoryInterface
{
    public function __construct(ServiceOrder $serviceOrder)
    {
        $this->model = $serviceOrder;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->with(['tutor', 'patient'])
            ->latest('opened_at')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->model
            ->with(['tutor', 'patient', 'items.product', 'items.petShopService'])
            ->findOrFail($id);
    }
}
