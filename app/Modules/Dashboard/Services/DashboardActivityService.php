<?php

namespace App\Modules\Dashboard\Services;

class DashboardActivityService
{
    public function latest(): array
    {
        return [
            [
                'icon' => 'bi-person-plus',
                'title' => 'Novo responsável cadastrado',
                'description' => 'Cadastro realizado com sucesso.',
                'time' => 'Agora',
            ],
            [
                'icon' => 'bi-heart-pulse',
                'title' => 'Paciente cadastrado',
                'description' => 'Novo paciente incluído no sistema.',
                'time' => '5 min',
            ],
            [
                'icon' => 'bi-calendar-check',
                'title' => 'Consulta agendada',
                'description' => 'Novo atendimento registrado.',
                'time' => '12 min',
            ],
            [
                'icon' => 'bi-cash-coin',
                'title' => 'Pagamento recebido',
                'description' => 'Receita lançada no financeiro.',
                'time' => '30 min',
            ],
        ];
    }
}
