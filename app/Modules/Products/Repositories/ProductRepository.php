<?php

namespace App\Modules\Products\Repositories;

use App\Core\Base\BaseRepository;
use App\Modules\Products\Contracts\ProductRepositoryInterface;
use App\Modules\Products\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $product)
    {
        $this->model = $product;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->orderBy('name')
            ->paginate($perPage);
    }
}
