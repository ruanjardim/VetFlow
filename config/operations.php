<?php

return [
    'backup' => [
        'evidence_max_age_days' => (int) env('VETFLOW_BACKUP_EVIDENCE_MAX_AGE_DAYS', 30),
    ],

    'queue' => [
        'mode' => env('VETFLOW_QUEUE_MODE', 'worker'),

        'cron' => [
            'enabled' => (bool) env('VETFLOW_QUEUE_CRON_ENABLED', false),
            'token' => env('VETFLOW_QUEUE_CRON_TOKEN'),
            'header' => env('VETFLOW_QUEUE_CRON_HEADER', 'X-Cron-Auth'),
            'max_jobs' => (int) env('VETFLOW_QUEUE_CRON_MAX_JOBS', 25),
            'max_time' => (int) env('VETFLOW_QUEUE_CRON_MAX_TIME', 45),
            'timeout' => (int) env('VETFLOW_QUEUE_CRON_TIMEOUT', 30),
            'tries' => (int) env('VETFLOW_QUEUE_CRON_TRIES', 3),
        ],
    ],
];
