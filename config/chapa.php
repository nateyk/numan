<?php

return [
    'secretKey' => env('CHAPA_SECRET_KEY'),
    'publicKey' => env('CHAPA_PUBLIC_KEY'),
    'webhookSecret' => env('CHAPA_WEBHOOK_SECRET'),
    'baseUrl' => env('CHAPA_BASE_URL', 'https://api.chapa.co/v1'),
    'callbackRoute' => env('CHAPA_CALLBACK_ROUTE', 'chapa.callback'),
    'returnRoute' => env('CHAPA_RETURN_ROUTE', 'billing.index'),
    'mode' => env('CHAPA_MODE', 'test'), // test or live
];
