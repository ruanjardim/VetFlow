<?php

namespace App\Modules\Sales\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Sales\Contracts\SaleRepositoryInterface;
use App\Modules\Sales\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class SaleRepository extends BaseRepository implements SaleRepositoryInterface
{
    public function __construct(Sale $sale)
    {
        $this->model = $sale;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['tutor', 'patient', 'serviceOrder'])
            ->latest('sold_at')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()
            ->with(['tutor', 'patient', 'serviceOrder', 'items.product', 'items.petShopService', 'payments'])
            ->findOrFail($id);
    }
}
