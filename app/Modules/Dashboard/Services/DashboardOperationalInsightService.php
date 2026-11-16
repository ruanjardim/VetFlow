<?php

namespace App\Modules\Dashboard\Services;

class DashboardOperationalInsightService
{
    /**
     * @param  array<string, bool>  $capabilities
     */
    public function priorities(array $stats, array $capabilities): array
    {
        $priorities = [];

        if ($capabilities['financial'] ?? false) {
            $priorities[] = $this->priority(
                'expenses_overdue',
                'Financeiro',
                'Regularizar contas a pagar vencidas',
                'Evite juros e interrupcoes de fornecedores.',
                'danger',
                (float) ($stats['expenses_overdue'] ?? 0),
                'currency',
                'Ver fluxo de caixa',
                route('financial-transactions.cash-flow')
            );
            $priorities[] = $this->priority(
                'financial_overdue',
                'Financeiro',
                'Cobrar recebimentos vencidos',
                'Recupere valores atrasados e proteja o caixa.',
                'danger',
                (float) ($stats['financial_overdue'] ?? 0),
                'currency',
                'Ver fluxo de caixa',
                route('financial-transactions.cash-flow')
            );
        }

        if ($capabilities['inventory'] ?? false) {
            $priorities[] = $this->priority(
                'low_stock',
                'Estoque',
                'Repor produtos abaixo do minimo',
                'Priorize itens que podem faltar no PDV e nos atendimentos.',
                'danger',
                (float) ($stats['low_stock'] ?? 0),
                'count',
                'Ver estoque critico',
                route('inventory-movements.alerts', ['level' => 'critical']).'#low-stock'
            );
        }

        if ($capabilities['serviceOrders'] ?? false) {
            $priorities[] = $this->priority(
                'service_orders_waiting_pickup',
                'Comandas',
                'Liberar pedidos aguardando retirada',
                'Avise os responsáveis e conclua entregas prontas.',
                'warning',
                (float) ($stats['service_orders_waiting_pickup'] ?? 0),
                'count',
                'Ver comandas',
                route('service-orders.index')
            );
        }

        if ($capabilities['sales'] ?? false) {
            $priorities[] = $this->priority(
                'sales_pending_payment',
                'PDV',
                'Resolver vendas com pagamento pendente',
                'Confira recebimentos incompletos antes do fechamento.',
                'warning',
                (float) ($stats['sales_pending_payment'] ?? 0),
                'currency',
                'Ver vendas',
                route('sales.index')
            );
            $priorities[] = $this->priority(
                'sales_drafts',
                'PDV',
                'Revisar vendas em rascunho',
                'Conclua ou descarte atendimentos que ficaram abertos.',
                'warning',
                (float) ($stats['sales_drafts'] ?? 0),
                'count',
                'Ver vendas',
                route('sales.index')
            );
        }

        if ($capabilities['appointments'] ?? false) {
            $priorities[] = $this->priority(
                'appointments_today',
                'Agenda',
                'Acompanhar consultas de hoje',
                'Confira a agenda e prepare os proximos atendimentos.',
                'info',
                (float) ($stats['appointments_today'] ?? 0),
                'count',
                'Ver agenda',
                route('appointments.index')
            );
        }

        return array_values(array_filter($priorities));
    }

    private function priority(
        string $key,
        string $area,
        string $title,
        string $description,
        string $level,
        float $value,
        string $valueType,
        string $action,
        string $url
    ): ?array {
        if ($value <= 0) {
            return null;
        }

        return [
            'key' => $key,
            'area' => $area,
            'title' => $title,
            'description' => $description,
            'level' => $level,
            'value' => $value,
            'value_type' => $valueType,
            'action' => $action,
            'url' => $url,
        ];
    }
}
