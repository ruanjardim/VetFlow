<?php

return [
    'enabled' => env('PRODUCT_LOOKUP_ENABLED', true),
    'timeout_seconds' => (int) env('PRODUCT_LOOKUP_TIMEOUT_SECONDS', 4),
    'connect_timeout_seconds' => (int) env('PRODUCT_LOOKUP_CONNECT_TIMEOUT_SECONDS', 2),
    'attempts' => (int) env('PRODUCT_LOOKUP_ATTEMPTS', 2),
    'negative_cache_days' => (int) env('PRODUCT_LOOKUP_NEGATIVE_CACHE_DAYS', 7),
    'max_image_bytes' => (int) env('PRODUCT_LOOKUP_MAX_IMAGE_BYTES', 5 * 1024 * 1024),
    'image_allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'PRODUCT_LOOKUP_IMAGE_ALLOWED_HOSTS',
            'images.openfoodfacts.org,static.openfoodfacts.org,images.openpetfoodfacts.org,static.openpetfoodfacts.org,images.openproductsfacts.org,static.openproductsfacts.org,openfoodfacts-images.s3.eu-west-3.amazonaws.com'
        ))
    ))),
    'user_agent' => env('PRODUCT_LOOKUP_USER_AGENT', 'VetFlowCommercial/1.0 (contato@vetflow.local)'),

    'providers' => [
        [
            'name' => 'commercial_gtin',
            'label' => 'API GTIN Comercial',
            'driver' => 'commercial_gtin_json',
            'base_url' => env('PRODUCT_LOOKUP_COMMERCIAL_GTIN_URL'),
            'token' => env('PRODUCT_LOOKUP_COMMERCIAL_GTIN_TOKEN'),
            'auth' => env('PRODUCT_LOOKUP_COMMERCIAL_GTIN_AUTH', 'bearer'),
            'query_key_name' => env('PRODUCT_LOOKUP_COMMERCIAL_GTIN_QUERY_KEY', 'api_key'),
            'enabled' => env('PRODUCT_LOOKUP_COMMERCIAL_GTIN_ENABLED', false),
            'tier' => 'commercial',
            'priority' => 30,
            'confidence' => 85,
        ],
        [
            'name' => 'open_pet_food_facts',
            'label' => 'Open Pet Food Facts',
            'driver' => 'open_food_facts_family',
            'base_url' => env('PRODUCT_LOOKUP_OPEN_PET_FOOD_FACTS_URL', 'https://world.openpetfoodfacts.org'),
            'enabled' => env('PRODUCT_LOOKUP_OPEN_PET_FOOD_FACTS_ENABLED', true),
            'tier' => 'free',
            'priority' => 10,
            'confidence' => 65,
        ],
        [
            'name' => 'open_food_facts',
            'label' => 'Open Food Facts',
            'driver' => 'open_food_facts_family',
            'base_url' => env('PRODUCT_LOOKUP_OPEN_FOOD_FACTS_URL', 'https://world.openfoodfacts.org'),
            'enabled' => env('PRODUCT_LOOKUP_OPEN_FOOD_FACTS_ENABLED', true),
            'tier' => 'free',
            'priority' => 20,
            'confidence' => 60,
        ],
        [
            'name' => 'open_products_facts',
            'label' => 'Open Products Facts',
            'driver' => 'open_food_facts_family',
            'base_url' => env('PRODUCT_LOOKUP_OPEN_PRODUCTS_FACTS_URL', 'https://world.openproductsfacts.org'),
            'enabled' => env('PRODUCT_LOOKUP_OPEN_PRODUCTS_FACTS_ENABLED', true),
            'tier' => 'free',
            'priority' => 25,
            'confidence' => 60,
        ],
    ],
];
