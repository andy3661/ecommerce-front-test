<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Shipping Carrier
    |--------------------------------------------------------------------------
    |
    | This option controls the default shipping carrier that will be used
    | when no specific carrier is requested.
    |
    */
    'default' => env('SHIPPING_DEFAULT_CARRIER', 'coordinadora'),

    /*
    |--------------------------------------------------------------------------
    | Shipping Carriers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the shipping carriers for your application.
    | Each carrier has its own configuration and API credentials.
    |
    */
    'carriers' => [
        'coordinadora' => [
            'name' => 'Coordinadora',
            'enabled' => env('COORDINADORA_ENABLED', true),
            'api_url' => env('COORDINADORA_API_URL', 'https://sandbox.coordinadora.com/agencia-virtual/ws'),
            'api_key' => env('COORDINADORA_API_KEY'),
            'username' => env('COORDINADORA_USERNAME'),
            'password' => env('COORDINADORA_PASSWORD'),
            'nit' => env('COORDINADORA_NIT'),
            'account' => env('COORDINADORA_ACCOUNT'),
            'sender_nit' => env('COORDINADORA_SENDER_NIT'),
            'test_mode' => env('COORDINADORA_TEST_MODE', true),
            'webhook_secret' => env('COORDINADORA_WEBHOOK_SECRET'),
            'max_weight' => 70, // kg
            'max_dimensions' => [
                'length' => 150, // cm
                'width' => 150,
                'height' => 150
            ],
            'supported_countries' => ['CO'],
            'services' => [
                'express' => 'Express',
                'standard' => 'Estándar',
                'economy' => 'Económico'
            ]
        ],

        'servientrega' => [
            'name' => 'Servientrega',
            'enabled' => env('SERVIENTREGA_ENABLED', true),
            'api_url' => env('SERVIENTREGA_API_URL', 'https://api.servientrega.com'),
            'api_key' => env('SERVIENTREGA_API_KEY'),
            'username' => env('SERVIENTREGA_USERNAME'),
            'password' => env('SERVIENTREGA_PASSWORD'),
            'billing_code' => env('SERVIENTREGA_BILLING_CODE'),
            'test_mode' => env('SERVIENTREGA_TEST_MODE', true),
            'webhook_secret' => env('SERVIENTREGA_WEBHOOK_SECRET'),
            'max_weight' => 50, // kg
            'max_dimensions' => [
                'length' => 120,
                'width' => 120,
                'height' => 120
            ],
            'supported_countries' => ['CO'],
            'services' => [
                'express' => 'Express',
                'standard' => 'Estándar',
                'economy' => 'Económico'
            ]
        ],

        'interrapidisimo' => [
            'name' => 'Interrapidísimo',
            'enabled' => env('INTERRAPIDISIMO_ENABLED', true),
            'api_url' => env('INTERRAPIDISIMO_API_URL', 'https://api.interrapidisimo.com'),
            'api_key' => env('INTERRAPIDISIMO_API_KEY'),
            'username' => env('INTERRAPIDISIMO_USERNAME'),
            'password' => env('INTERRAPIDISIMO_PASSWORD'),
            'account_number' => env('INTERRAPIDISIMO_ACCOUNT'),
            'test_mode' => env('INTERRAPIDISIMO_TEST_MODE', true),
            'webhook_secret' => env('INTERRAPIDISIMO_WEBHOOK_SECRET'),
            'max_weight' => 30, // kg
            'max_dimensions' => [
                'length' => 100,
                'width' => 100,
                'height' => 100
            ],
            'supported_countries' => ['CO'],
            'services' => [
                'express' => 'Express',
                'standard' => 'Estándar'
            ]
        ],

        'tcc' => [
            'name' => 'TCC',
            'enabled' => env('TCC_ENABLED', true),
            'api_url' => env('TCC_API_URL', 'https://api.tcc.com.co'),
            'api_key' => env('TCC_API_KEY'),
            'username' => env('TCC_USERNAME'),
            'password' => env('TCC_PASSWORD'),
            'client_code' => env('TCC_CLIENT_CODE'),
            'test_mode' => env('TCC_TEST_MODE', true),
            'webhook_secret' => env('TCC_WEBHOOK_SECRET'),
            'max_weight' => 40, // kg
            'max_dimensions' => [
                'length' => 120,
                'width' => 120,
                'height' => 120
            ],
            'supported_countries' => ['CO'],
            'services' => [
                'express' => 'Express',
                'standard' => 'Estándar',
                'economy' => 'Económico'
            ]
        ],

        'fedex' => [
            'name' => 'FedEx',
            'enabled' => env('FEDEX_ENABLED', false),
            'api_url' => env('FEDEX_API_URL', 'https://apis-sandbox.fedex.com'),
            'api_key' => env('FEDEX_API_KEY'),
            'secret_key' => env('FEDEX_SECRET_KEY'),
            'account_number' => env('FEDEX_ACCOUNT_NUMBER'),
            'meter_number' => env('FEDEX_METER_NUMBER'),
            'test_mode' => env('FEDEX_TEST_MODE', true),
            'webhook_secret' => env('FEDEX_WEBHOOK_SECRET'),
            'max_weight' => 68, // kg
            'max_dimensions' => [
                'length' => 274,
                'width' => 274,
                'height' => 274
            ],
            'supported_countries' => ['CO', 'US', 'CA', 'MX'],
            'services' => [
                'overnight' => 'FedEx Overnight',
                'express' => 'FedEx Express',
                'ground' => 'FedEx Ground',
                'international' => 'FedEx International'
            ]
        ],

        'dhl' => [
            'name' => 'DHL',
            'enabled' => env('DHL_ENABLED', false),
            'api_url' => env('DHL_API_URL', 'https://api-sandbox.dhl.com'),
            'api_key' => env('DHL_API_KEY'),
            'secret' => env('DHL_SECRET'),
            'account_number' => env('DHL_ACCOUNT_NUMBER'),
            'test_mode' => env('DHL_TEST_MODE', true),
            'webhook_secret' => env('DHL_WEBHOOK_SECRET'),
            'max_weight' => 70, // kg
            'max_dimensions' => [
                'length' => 300,
                'width' => 300,
                'height' => 300
            ],
            'supported_countries' => ['CO', 'US', 'CA', 'MX', 'BR', 'AR'],
            'services' => [
                'express' => 'DHL Express',
                'economy' => 'DHL Economy',
                'domestic' => 'DHL Domestic'
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Origin
    |--------------------------------------------------------------------------
    |
    | Default shipping origin for calculating shipping costs and creating labels.
    |
    */
    'default_origin' => [
        'name' => env('SHIPPING_ORIGIN_NAME', 'Mi Tienda'),
        'company' => env('SHIPPING_ORIGIN_COMPANY', 'Mi Empresa'),
        'address' => env('SHIPPING_ORIGIN_ADDRESS', 'Calle 123 #45-67'),
        'city' => env('SHIPPING_ORIGIN_CITY', 'Medellín'),
        'city_code' => env('SHIPPING_ORIGIN_CITY_CODE', '05001'),
        'state' => env('SHIPPING_ORIGIN_STATE', 'Antioquia'),
        'state_code' => env('SHIPPING_ORIGIN_STATE_CODE', '05'),
        'postal_code' => env('SHIPPING_ORIGIN_POSTAL_CODE', '050001'),
        'country' => env('SHIPPING_ORIGIN_COUNTRY', 'CO'),
        'phone' => env('SHIPPING_ORIGIN_PHONE', '+57 300 123 4567'),
        'email' => env('SHIPPING_ORIGIN_EMAIL', 'envios@mitienda.com'),
        'document' => env('SHIPPING_ORIGIN_DOCUMENT', '900123456-1')
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping Zones
    |--------------------------------------------------------------------------
    |
    | Define shipping zones with different rates and carriers.
    |
    */
    'zones' => [
        'local' => [
            'name' => 'Local',
            'description' => 'Misma ciudad',
            'countries' => ['CO'],
            'states' => ['05'], // Antioquia
            'cities' => ['05001'], // Medellín
            'base_cost' => 8000,
            'free_shipping_threshold' => 100000,
            'carriers' => ['coordinadora', 'servientrega', 'interrapidisimo']
        ],
        'national' => [
            'name' => 'Nacional',
            'description' => 'Todo Colombia',
            'countries' => ['CO'],
            'states' => ['all'],
            'cities' => ['all'],
            'base_cost' => 15000,
            'free_shipping_threshold' => 150000,
            'carriers' => ['coordinadora', 'servientrega', 'interrapidisimo', 'tcc']
        ],
        'international' => [
            'name' => 'Internacional',
            'description' => 'Fuera de Colombia',
            'countries' => ['US', 'CA', 'MX', 'BR', 'AR'],
            'states' => ['all'],
            'cities' => ['all'],
            'base_cost' => 50000,
            'free_shipping_threshold' => 500000,
            'carriers' => ['fedex', 'dhl']
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | General Settings
    |--------------------------------------------------------------------------
    |
    | General shipping configuration options.
    |
    */
    'settings' => [
        // Default package dimensions when not specified
        'default_dimensions' => [
            'length' => 10, // cm
            'width' => 10,  // cm
            'height' => 5   // cm
        ],

        // Default package weight when not specified
        'default_weight' => 0.5, // kg

        // Default declared value percentage of order total
        'default_declared_value_percentage' => 100,

        // Minimum declared value
        'min_declared_value' => 20000, // COP

        // Maximum declared value
        'max_declared_value' => 5000000, // COP

        // Enable automatic carrier selection based on best price/time
        'auto_carrier_selection' => env('SHIPPING_AUTO_CARRIER_SELECTION', true),

        // Carrier selection criteria: 'price', 'time', 'reliability'
        'carrier_selection_criteria' => env('SHIPPING_CARRIER_CRITERIA', 'price'),

        // Enable shipping insurance
        'insurance_enabled' => env('SHIPPING_INSURANCE_ENABLED', true),

        // Insurance percentage of declared value
        'insurance_percentage' => env('SHIPPING_INSURANCE_PERCENTAGE', 1.5),

        // Enable signature confirmation
        'signature_confirmation' => env('SHIPPING_SIGNATURE_CONFIRMATION', false),

        // Enable Saturday delivery
        'saturday_delivery' => env('SHIPPING_SATURDAY_DELIVERY', false),

        // Business days for delivery calculation
        'business_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],

        // Holidays (dates when no delivery occurs)
        'holidays' => [
            '2024-01-01', // New Year
            '2024-01-08', // Epiphany
            '2024-03-25', // Saint Joseph
            '2024-03-28', // Maundy Thursday
            '2024-03-29', // Good Friday
            '2024-05-01', // Labor Day
            '2024-05-13', // Ascension Day
            '2024-06-03', // Corpus Christi
            '2024-06-10', // Sacred Heart
            '2024-07-01', // Saint Peter and Paul
            '2024-07-20', // Independence Day
            '2024-08-07', // Battle of Boyacá
            '2024-08-19', // Assumption of Mary
            '2024-10-14', // Columbus Day
            '2024-11-04', // All Saints Day
            '2024-11-11', // Independence of Cartagena
            '2024-12-08', // Immaculate Conception
            '2024-12-25'  // Christmas
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for receiving shipping status updates via webhooks.
    |
    */
    'webhooks' => [
        'enabled' => env('SHIPPING_WEBHOOKS_ENABLED', true),
        'verify_signature' => env('SHIPPING_WEBHOOKS_VERIFY_SIGNATURE', true),
        'timeout' => env('SHIPPING_WEBHOOKS_TIMEOUT', 30), // seconds
        'retry_attempts' => env('SHIPPING_WEBHOOKS_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('SHIPPING_WEBHOOKS_RETRY_DELAY', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for caching shipping rates and carrier information.
    |
    */
    'cache' => [
        'enabled' => env('SHIPPING_CACHE_ENABLED', true),
        'ttl' => env('SHIPPING_CACHE_TTL', 3600), // seconds (1 hour)
        'prefix' => 'shipping:',
        'tags' => ['shipping', 'rates']
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for logging shipping operations and API calls.
    |
    */
    'logging' => [
        'enabled' => env('SHIPPING_LOGGING_ENABLED', true),
        'level' => env('SHIPPING_LOG_LEVEL', 'info'),
        'channel' => env('SHIPPING_LOG_CHANNEL', 'daily'),
        'log_api_requests' => env('SHIPPING_LOG_API_REQUESTS', true),
        'log_api_responses' => env('SHIPPING_LOG_API_RESPONSES', false)
    ]
];