<?php

return [
    'max_xml_bytes' => (int) env('NFE_MAX_XML_BYTES', 5 * 1024 * 1024),
    'max_items' => (int) env('NFE_MAX_ITEMS', 500),
    'max_local_xml_files' => (int) env('NFE_MAX_LOCAL_XML_FILES', 1000),

    'key_lookup' => [
        'url' => env('NFE_KEY_LOOKUP_URL'),
        'token' => env('NFE_KEY_LOOKUP_TOKEN'),
        'timeout_seconds' => (int) env('NFE_KEY_LOOKUP_TIMEOUT_SECONDS', 10),
        'connect_timeout_seconds' => (int) env('NFE_KEY_LOOKUP_CONNECT_TIMEOUT_SECONDS', 3),
        'attempts' => (int) env('NFE_KEY_LOOKUP_ATTEMPTS', 2),
    ],

    'archive_paths' => env('NFE_XML_ARCHIVE_PATH'),
];
