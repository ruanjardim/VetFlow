<?php

return [
    // This switch is intentionally false. Selecting a provider in the UI does
    // not enable network traffic; a reviewed deployment must opt in separately.
    'external_http_enabled' => env('VETFLOW_FISCAL_EXTERNAL_HTTP_ENABLED', false),

    // A second, independent switch protects the operational queue. Both flags
    // must be enabled and a concrete provider must be bound before transmission.
    'submission_enabled' => env('VETFLOW_FISCAL_SUBMISSION_ENABLED', false),

    // Production has an independent, fail-closed deployment gate. It never
    // becomes available merely because homologation traffic is enabled or a
    // clinic selects a provider in the interface.
    'production' => [
        'enabled' => env('VETFLOW_FISCAL_PRODUCTION_ENABLED', false),
    ],

    // The adapter and credential driver are separate deployment gates. The
    // default pair cannot resolve credentials or instantiate an HTTP provider.
    'provider_adapter' => env('VETFLOW_FISCAL_PROVIDER_ADAPTER', 'disabled'),

    'credentials' => [
        'driver' => env('VETFLOW_FISCAL_CREDENTIAL_DRIVER', 'disabled'),
        // encrypted_database lets each clinic connect its own Focus account.
        // Laravel encrypts the token with APP_KEY before it reaches storage.
        'external_file' => env('VETFLOW_FISCAL_CREDENTIAL_FILE'),
        'max_file_bytes' => env('VETFLOW_FISCAL_CREDENTIAL_MAX_FILE_BYTES', 65536),
    ],

    'queue' => [
        'connection' => env('VETFLOW_FISCAL_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'name' => env('VETFLOW_FISCAL_QUEUE', 'fiscal'),
        'automatic_reconciliation' => env('VETFLOW_FISCAL_AUTOMATIC_RECONCILIATION', true),
    ],

    'alerts' => [
        'processing_stale_minutes' => env('VETFLOW_FISCAL_PROCESSING_STALE_MINUTES', 180),
        'cancellation_stale_minutes' => env('VETFLOW_FISCAL_CANCELLATION_STALE_MINUTES', 30),
        'max_items' => env('VETFLOW_FISCAL_ALERT_MAX_ITEMS', 20),
    ],

    'recovery' => [
        'webhook_resend_cooldown_minutes' => env('VETFLOW_FISCAL_WEBHOOK_RESEND_COOLDOWN_MINUTES', 5),
        'artifact_max_bytes' => env('VETFLOW_FISCAL_ARTIFACT_MAX_BYTES', 10485760),
        'artifact_redirect_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('VETFLOW_FISCAL_ARTIFACT_REDIRECT_HOSTS', ''))
        ))),
    ],

    'focus_nfe' => [
        'homologation_url' => env('FOCUS_NFE_HOMOLOGATION_URL', 'https://homologacao.focusnfe.com.br'),
        'production_url' => env('FOCUS_NFE_PRODUCTION_URL', 'https://api.focusnfe.com.br'),
        'timeout_seconds' => env('FOCUS_NFE_TIMEOUT_SECONDS', 15),
        'connect_timeout_seconds' => env('FOCUS_NFE_CONNECT_TIMEOUT_SECONDS', 5),
        'webhook' => [
            'enabled' => env('FOCUS_NFE_WEBHOOK_ENABLED', false),
            'authorization_header' => env('FOCUS_NFE_WEBHOOK_AUTHORIZATION_HEADER', 'X-VetFlow-Focus-Webhook'),
            'authorization_token' => env('FOCUS_NFE_WEBHOOK_AUTHORIZATION_TOKEN'),
            'max_payload_bytes' => env('FOCUS_NFE_WEBHOOK_MAX_PAYLOAD_BYTES', 131072),
        ],
    ],

    'nfce' => [
        // A Focus documenta uma janela de 30 minutos para o cancelamento da
        // NFC-e. O provedor e a SEFAZ continuam sendo a autoridade final.
        'cancellation_window_minutes' => env('VETFLOW_NFCE_CANCELLATION_WINDOW_MINUTES', 30),
    ],
];
