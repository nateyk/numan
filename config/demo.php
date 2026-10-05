<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Demo account password
    |--------------------------------------------------------------------------
    |
    | The password every demo account is seeded with, used only by the seeders
    | and only outside production -- DatabaseSeeder and DemoDataSeeder abort
    | when app()->environment('production').
    |
    | There is deliberately no default here. An empty value makes the seeders
    | generate a random password and print it to the console once. A shared
    | default would be published in this repository alongside a superadmin,
    | which is not a secret.
    |
    */

    'password' => env('DEMO_PASSWORD', ''),
];
