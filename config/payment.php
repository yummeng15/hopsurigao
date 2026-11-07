<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Receiving accounts
    |--------------------------------------------------------------------------
    |
    | Platform receiving account for GCash. Online checkout uses PayMongo
    | GCash only — Maya and bank transfer are not offered.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | PayMongo
    |--------------------------------------------------------------------------
    |
    | The secret key stays on the server. Tourists pay on PayMongo's page.
    | A booking is marked paid only after PayMongo reports a paid payment
    | for the same amount. Leave the key empty until it is pasted into .env.
    |
    */

    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'checkout_url' => 'https://api.paymongo.com/v2/checkout_sessions',
        'retrieve_url' => 'https://api.paymongo.com/v1/checkout_sessions',
        'ca_bundle' => storage_path('app/cacert.pem'),
    ],

    'accounts' => [
        'gcash' => [
            'label' => 'GCash',
            'account_name' => env('PAYMENT_GCASH_NAME', 'Hop Surigao'),
            'number' => env('PAYMENT_GCASH_NUMBER', '0917 555 0101'),
            'bank' => null,
        ],
    ],

];
