<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Sales\Models\Sale;

class DashboardSalesService
{
    public function todayTotal(): float
    {
        return (float) Sale::where('status', 'completed')
            ->whereDate('sold_at', today())
            ->sum('total');
    }

    public function monthTotal(): float
    {
        return (float) Sale::where('status', 'completed')
            ->whereBetween('sold_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');
    }

    public function drafts(): int
    {
        return Sale::where('status', 'draft')->count();
    }

    public function pendingPayment(): float
    {
        return (float) Sale::where('status', 'completed')
            ->whereIn('payment_status', ['pending', 'partial'])
            ->sum('total');
    }
}
