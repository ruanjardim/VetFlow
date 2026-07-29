<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Inventory\Services\StockAlertService;

class DashboardAlertService
{
    public function __construct(private readonly StockAlertService $stockAlertService) {}

    public function latest(): array
    {
        $data = $this->stockAlertService->data();

        return [
            ...$this->alertsFromData($data),
            ...$this->financialAlerts(),
        ];
    }

    public function summary(): array
    {
        $data = $this->stockAlertService->data();
        $financialAlerts = $this->financialAlerts();
        $financialTotal = array_sum(array_column($financialAlerts, 'count'));
        $financialCritical = array_sum(array_column(
            array_filter($financialAlerts, fn (array $alert) => $alert['level'] === 'danger'),
            'count'
        ));

        return [
            'stats' => [
                'total' => ($data['stats']['total'] ?? 0) + $financialTotal,
                'critical' => ($data['stats']['critical'] ?? 0) + $financialCritical,
                'attention' => ($data['stats']['attention'] ?? 0) + ($financialTotal - $financialCritical),
                'cadastro' => $data['stats']['cadastro'] ?? 0,
                'inventory' => $data['stats']['total'] ?? 0,
                'financial' => $financialTotal,
            ],
            'latest' => array_slice([
                ...$financialAlerts,
                ...$this->alertsFromData($data),
            ], 0, 6),
        ];
    }

    private function alertsFromData(array $data): array
    {
        return array_values(array_filter([
            $this->alert(
                $data['lowStockProducts']->count(),
                'Produtos abaixo do minimo',
                'Repor estoque antes de faltar no PDV e nos atendimentos.',
                'danger',
                route('inventory-movements.alerts', ['level' => 'critical']).'#low-stock'
            ),
            $this->alert(
                $data['expiredLots']->count(),
                'Lotes vencidos',
                'Separar produtos vencidos do estoque vendavel.',
                'danger',
                route('inventory-movements.alerts', ['level' => 'critical']).'#expired-lots'
            ),
            $this->alert(
                $data['expiringLots']->count(),
                'Lotes proximos de vencer',
                'Priorizar venda, uso ou conferencia desses itens.',
                'warning',
                route('inventory-movements.alerts', ['level' => 'attention']).'#expiring-lots'
            ),
            $this->alert(
                $data['untrackedProducts']->count(),
                'Estoque sem lote',
                'Vincular lote e validade para melhorar o controle.',
                'warning',
                route('inventory-movements.alerts', ['level' => 'attention']).'#untracked-stock'
            ),
            $this->alert(
                $data['withoutPriceProducts']->count(),
                'Produtos sem preco',
                'Definir preco de venda para liberar o PDV.',
                'danger',
                route('inventory-movements.alerts', ['level' => 'critical']).'#without-price'
            ),
            $this->alert(
                $data['withoutImageProducts']->count(),
                'Produtos sem imagem',
                'Adicionar foto para facilitar conferencia visual.',
                'info',
                route('inventory-movements.alerts', ['level' => 'cadastro']).'#without-image'
            ),
        ]));
    }

    private function financialAlerts(): array
    {
        return array_values(array_filter([
            $this->alert(
                $this->overdueFinancialCount('expense'),
                'Contas a pagar vencidas',
                'Pagamentos pendentes com vencimento atrasado.',
                'danger',
                route('financial-transactions.cash-flow')
            ),
            $this->alert(
                $this->overdueFinancialCount('income'),
                'Contas a receber vencidas',
                'Recebimentos pendentes com vencimento atrasado.',
                'danger',
                route('financial-transactions.cash-flow')
            ),
            $this->alert(
                $this->dueSoonFinancialCount('expense'),
                'Pagamentos proximos',
                'Contas a pagar vencendo nos proximos 7 dias.',
                'warning',
                route('financial-transactions.cash-flow')
            ),
            $this->alert(
                $this->dueSoonFinancialCount('income'),
                'Recebimentos proximos',
                'Contas a receber vencendo nos proximos 7 dias.',
                'warning',
                route('financial-transactions.cash-flow')
            ),
        ]));
    }

    private function overdueFinancialCount(string $type): int
    {
        return FinancialTransaction::query()
            ->where('type', $type)
            ->where(function ($query) {
                $query
                    ->where('status', 'overdue')
                    ->orWhere(function ($query) {
                        $query
                            ->where('status', 'pending')
                            ->whereDate('due_date', '<', today());
                    });
            })
            ->count();
    }

    private function dueSoonFinancialCount(string $type): int
    {
        return FinancialTransaction::query()
            ->where('type', $type)
            ->where('status', 'pending')
            ->whereBetween('due_date', [today(), today()->addDays(7)])
            ->count();
    }

    private function alert(int $count, string $title, string $description, string $level, ?string $url = null): ?array
    {
        if ($count === 0) {
            return null;
        }

        return [
            'count' => $count,
            'title' => $title,
            'description' => $description,
            'level' => $level,
            'url' => $url,
        ];
    }
}
