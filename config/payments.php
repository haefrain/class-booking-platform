<?php

declare(strict_types=1);

return [

    // ISO currency for all class prices (integer cents everywhere).
    'currency' => env('PAYMENTS_CURRENCY', 'usd'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    ],

];
