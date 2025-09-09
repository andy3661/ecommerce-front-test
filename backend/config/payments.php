<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway that will be used
    | by the payment service. You may set this to any of the gateways
    | which are defined in the "gateways" array below.
    |
    */

    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Here you may configure the payment gateways for your application.
    | Each gateway has its own configuration options.
    |
    */

    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', false),
        'public_key' => env('STRIPE_PUBLIC_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
        'test_mode' => env('STRIPE_TEST_MODE', true),
    ],

    'paypal' => [
        'enabled' => env('PAYPAL_ENABLED', false),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
        'sandbox' => env('PAYPAL_SANDBOX', true),
        'base_url' => env('PAYPAL_SANDBOX', true) 
            ? 'https://api.sandbox.paypal.com'
            : 'https://api.paypal.com',
    ],

    'payu' => [
        'enabled' => env('PAYU_ENABLED', false),
        'merchant_id' => env('PAYU_MERCHANT_ID'),
        'account_id' => env('PAYU_ACCOUNT_ID'),
        'api_key' => env('PAYU_API_KEY'),
        'api_login' => env('PAYU_API_LOGIN'),
        'currencies' => ['COP', 'USD', 'BRL', 'MXN', 'ARS', 'PEN'],
        'test_mode' => env('PAYU_TEST_MODE', true),
        'base_url' => env('PAYU_TEST_MODE', true)
            ? 'https://sandbox.api.payulatam.com'
            : 'https://api.payulatam.com',
    ],

    'wompi' => [
        'enabled' => env('WOMPI_ENABLED', false),
        'public_key' => env('WOMPI_PUBLIC_KEY'),
        'private_key' => env('WOMPI_PRIVATE_KEY'),
        'webhook_secret' => env('WOMPI_WEBHOOK_SECRET'),
        'currencies' => ['COP'],
        'test_mode' => env('WOMPI_TEST_MODE', true),
        'base_url' => env('WOMPI_TEST_MODE', true)
            ? 'https://sandbox.wompi.co'
            : 'https://production.wompi.co',
    ],

    'mercadopago' => [
        'enabled' => env('MERCADOPAGO_ENABLED', false),
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'currencies' => ['ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'UYU'],
        'test_mode' => env('MERCADOPAGO_TEST_MODE', true),
        'base_url' => 'https://api.mercadopago.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Settings
    |--------------------------------------------------------------------------
    |
    | General payment configuration options.
    |
    */

    'currency' => env('PAYMENT_CURRENCY', 'USD'),
    'locale' => env('PAYMENT_LOCALE', 'en'),
    
    // Timeout for payment gateway requests (in seconds)
    'timeout' => env('PAYMENT_TIMEOUT', 30),
    
    // Maximum number of retry attempts for failed payments
    'max_retries' => env('PAYMENT_MAX_RETRIES', 3),
    
    // Payment session timeout (in minutes)
    'session_timeout' => env('PAYMENT_SESSION_TIMEOUT', 30),
    
    // Refund policy (in days)
    'refund_period' => env('PAYMENT_REFUND_PERIOD', 30),
    
    // Minimum and maximum payment amounts
    'min_amount' => env('PAYMENT_MIN_AMOUNT', 1.00),
    'max_amount' => env('PAYMENT_MAX_AMOUNT', 10000.00),
    
    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for payment webhook handling.
    |
    */
    
    'webhooks' => [
        'enabled' => env('PAYMENT_WEBHOOKS_ENABLED', true),
        'verify_signature' => env('PAYMENT_WEBHOOKS_VERIFY_SIGNATURE', true),
        'log_events' => env('PAYMENT_WEBHOOKS_LOG_EVENTS', true),
        'retry_failed' => env('PAYMENT_WEBHOOKS_RETRY_FAILED', true),
        'max_retry_attempts' => env('PAYMENT_WEBHOOKS_MAX_RETRIES', 3),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related payment configuration.
    |
    */
    
    'security' => [
        'encrypt_sensitive_data' => env('PAYMENT_ENCRYPT_DATA', true),
        'log_sensitive_data' => env('PAYMENT_LOG_SENSITIVE_DATA', false),
        'rate_limit_enabled' => env('PAYMENT_RATE_LIMIT_ENABLED', true),
        'rate_limit_attempts' => env('PAYMENT_RATE_LIMIT_ATTEMPTS', 5),
        'rate_limit_decay_minutes' => env('PAYMENT_RATE_LIMIT_DECAY', 1),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for payment notifications.
    |
    */
    
    'notifications' => [
        'email_enabled' => env('PAYMENT_EMAIL_NOTIFICATIONS', true),
        'sms_enabled' => env('PAYMENT_SMS_NOTIFICATIONS', false),
        'admin_notifications' => env('PAYMENT_ADMIN_NOTIFICATIONS', true),
        'customer_notifications' => env('PAYMENT_CUSTOMER_NOTIFICATIONS', true),
    ],

];