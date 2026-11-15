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

    /*
    |--------------------------------------------------------------------------
    | Catálogo local do PDV
    |--------------------------------------------------------------------------
    |
    | Mantém o snapshot offline previsível em hospedagem compartilhada. Se uma
    | clínica superar este limite, o endpoint sinaliza que o catálogo está
    | incompleto e o navegador não libera novas vendas offline.
    |
    */
    'offline_catalog_limit' => max(100, (int) env('SALES_OFFLINE_CATALOG_LIMIT', 5000)),
];
