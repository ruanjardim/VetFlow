<?php

namespace App\Modules\Inventory\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Inventory\Contracts\InventoryMovementRepositoryInterface;
use App\Modules\Inventory\Models\InventoryMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryMovementRepository extends BaseRepository implements InventoryMovementRepositoryInterface
{
    public function __construct(InventoryMovement $inventoryMovement)
    {
        $this->model = $inventoryMovement;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->with(['product', 'clinic'])
            ->latest('occurred_at')
            ->paginate($perPage);
    }
}
