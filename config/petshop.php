<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agenda de Banho e Tosa
    |--------------------------------------------------------------------------
    |
    | A agenda interna aceita agendamentos durante as 24 horas. O intervalo
    | controla somente a granularidade da grade e das sugestoes. Os horarios
    | abaixo continuam disponiveis para normalizar configuracoes legadas, mas
    | nao restringem novos agendamentos.
    |
    */
    'grooming' => [
        'opens_at' => env('PETSHOP_GROOMING_OPENS_AT', '08:00'),
        'closes_at' => env('PETSHOP_GROOMING_CLOSES_AT', '18:00'),
        'slot_minutes' => (int) env('PETSHOP_GROOMING_SLOT_MINUTES', 30),
        'max_recurrences' => 12,
    ],
];
