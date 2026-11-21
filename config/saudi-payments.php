<?php

/*
|--------------------------------------------------------------------------
| Saudi payment gateways
|--------------------------------------------------------------------------
|
| Credentials and endpoints for each provider. Endpoints are configurable on
| purpose: providers version their APIs and have separate sandbox hosts.
| Always confirm paths, field names and webhook signing against the
| provider's current API documentation before going live.
|
| Which methods customers actually see is NOT decided here: that lives in the
| payment_methods table so it can change from the admin panel without a
| mobile app release.
|
*/

return [

    'currency' => env('PAYMENTS_CURRENCY', 'SAR'),

    'http' => [
        'timeout' => (int) env('PAYMENTS_HTTP_TIMEOUT', 15),
        'retries' => (int) env('PAYMENTS_HTTP_RETRIES', 2),
        'retry_sleep_ms' => 300,
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => '',
        'middleware' => ['api'],
    ],

    'gateways' => [

        'moyasar' => [
            'driver' => Alsuhaibi\SaudiPayments\Drivers\MoyasarGateway::class,
            'base_url' => env('MOYASAR_BASE_URL', 'https://api.moyasar.com/v1'),
            'secret_key' => env('MOYASAR_SECRET_KEY'),
            'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET'),
            'signature_header' => 'X-Signature',
        ],

        'paytabs' => [
            'driver' => Alsuhaibi\SaudiPayments\Drivers\PayTabsGateway::class,
            'base_url' => env('PAYTABS_BASE_URL', 'https://secure.paytabs.sa'),
            'profile_id' => env('PAYTABS_PROFILE_ID'),
            'server_key' => env('PAYTABS_SERVER_KEY'),
            'webhook_secret' => env('PAYTABS_WEBHOOK_SECRET', env('PAYTABS_SERVER_KEY')),
            'signature_header' => 'Signature',
        ],

        'tabby' => [
            'driver' => Alsuhaibi\SaudiPayments\Drivers\TabbyGateway::class,
            'base_url' => env('TABBY_BASE_URL', 'https://api.tabby.ai'),
            'secret_key' => env('TABBY_SECRET_KEY'),
            'merchant_code' => env('TABBY_MERCHANT_CODE'),
            'webhook_secret' => env('TABBY_WEBHOOK_SECRET'),
            'signature_header' => 'X-Signature',
        ],

        'tamara' => [
            'driver' => Alsuhaibi\SaudiPayments\Drivers\TamaraGateway::class,
            'base_url' => env('TAMARA_BASE_URL', 'https://api-sandbox.tamara.co'),
            'api_token' => env('TAMARA_API_TOKEN'),
            'webhook_secret' => env('TAMARA_WEBHOOK_SECRET'),
            'signature_header' => 'X-Signature',
        ],

        'mispay' => [
            'driver' => Alsuhaibi\SaudiPayments\Drivers\MisPayGateway::class,
            'base_url' => env('MISPAY_BASE_URL', 'https://api.mispay.co/sandbox/v1'),
            'app_id' => env('MISPAY_APP_ID'),
            'app_secret' => env('MISPAY_APP_SECRET'),
            'webhook_secret' => env('MISPAY_WEBHOOK_SECRET'),
            'signature_header' => 'X-Signature',
        ],

    ],

];
