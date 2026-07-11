<?php

namespace App\Modules\PetShopServices\Services;

use App\Core\Base\BaseService;
use App\Modules\PetShopServices\Contracts\PetShopServiceRepositoryInterface;

class PetShopServiceService extends BaseService
{
    public function __construct(PetShopServiceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
}
