<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agenda de Banho e Tosa
    |--------------------------------------------------------------------------
    |
    | Horario de funcionamento usado para montar a grade do dia e sugerir
    | horarios livres por profissional. Premissa inicial ajustavel por
    | ambiente ate existir configuracao por clinica.
    |
    */
    'grooming' => [
        'opens_at' => env('PETSHOP_GROOMING_OPENS_AT', '08:00'),
        'closes_at' => env('PETSHOP_GROOMING_CLOSES_AT', '18:00'),
        'slot_minutes' => (int) env('PETSHOP_GROOMING_SLOT_MINUTES', 30),
        'max_recurrences' => 12,
    ],
];
