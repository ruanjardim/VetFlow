<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\PetShopServices\Models\PetShopService;

class DashboardPetShopServiceService
{
    public function total(): int
    {
        return PetShopService::count();
    }

    public function active(): int
    {
        return PetShopService::query()
            ->active()
            ->count();
    }
}
