<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Financial\Models\FinancialTransaction;

class DashboardFinancialService
{
    public function paidIncomeTotal(): float
    {
        return (float) FinancialTransaction::where('type', 'income')
            ->where('status', 'paid')
            ->sum('amount');
    }

    public function monthIncome(): float
    {
        return (float) FinancialTransaction::where('type', 'income')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->sum('amount');
    }

    public function pendingIncome(): float
    {
        return (float) FinancialTransaction::where('type', 'income')
            ->where('status', 'pending')
            ->sum('amount');
    }

    public function overdueIncome(): float
    {
        return (float) FinancialTransaction::where('type', 'income')
            ->where(function ($query) {
                $query
                    ->where('status', 'overdue')
                    ->orWhere(function ($query) {
                        $query
                            ->where('status', 'pending')
                            ->whereDate('due_date', '<', today());
                    });
            })
            ->sum('amount');
    }

    public function pendingExpense(): float
    {
        return (float) FinancialTransaction::where('type', 'expense')
            ->where('status', 'pending')
            ->sum('amount');
    }

    public function overdueExpense(): float
    {
        return (float) FinancialTransaction::where('type', 'expense')
            ->where(function ($query) {
                $query
                    ->where('status', 'overdue')
                    ->orWhere(function ($query) {
                        $query
                            ->where('status', 'pending')
                            ->whereDate('due_date', '<', today());
                    });
            })
            ->sum('amount');
    }

    public function monthExpense(): float
    {
        return (float) FinancialTransaction::where('type', 'expense')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->sum('amount');
    }
}
