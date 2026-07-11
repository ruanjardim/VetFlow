<?php

namespace App\Modules\Dashboard\Services;

class DashboardWidgetRegistry
{
    /**
     * Lista de widgets disponíveis no Dashboard.
     */
    public function widgets(): array
    {
        return [
            'schedule',
            'latestPatients',
            'latestTutors',
            'recentActivities',
            'alerts',
        ];
    }

    /**
     * Verifica se um widget está registrado.
     */
    public function has(string $widget): bool
    {
        return in_array($widget, $this->widgets(), true);
    }

    /**
     * Retorna todos os widgets registrados.
     */
    public function all(): array
    {
        return $this->widgets();
    }
}