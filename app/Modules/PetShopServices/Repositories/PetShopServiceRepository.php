<?php

namespace App\Modules\PetShopServices\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\PetShopServices\Contracts\PetShopServiceRepositoryInterface;
use App\Modules\PetShopServices\Models\PetShopService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PetShopServiceRepository extends BaseRepository implements PetShopServiceRepositoryInterface
{
    public function __construct(PetShopService $petShopService)
    {
        $this->model = $petShopService;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->orderBy('category')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
