<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\ServiceOrders\Models\ServiceOrder;

class DashboardServiceOrderService
{
    public function open(): int
    {
        return ServiceOrder::where('status', 'open')->count();
    }

    public function inService(): int
    {
        return ServiceOrder::where('status', 'in_service')->count();
    }

    public function waitingPickup(): int
    {
        return ServiceOrder::where('status', 'waiting_pickup')->count();
    }

    public function dayTotal(): float
    {
        return (float) ServiceOrder::whereDate('opened_at', today())
            ->whereNotIn('status', ['cancelled'])
            ->sum('total');
    }
}
