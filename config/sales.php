<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Orçamentos do PDV
    |--------------------------------------------------------------------------
    |
    | Validade sugerida (em dias) para um orçamento novo. O operador pode
    | alterar a data em cada orçamento. Sempre leia com valor padrão
    | (config('sales.quote_validity_days', 7)), porque a produção mantém o
    | config em cache.
    |
    */
    'quote_validity_days' => (int) env('SALES_QUOTE_VALIDITY_DAYS', 7),
];
