<?php

/*
|--------------------------------------------------------------------------
| Permanent Super Administrator
|--------------------------------------------------------------------------
|
| Identity values are not secret. The password is NEVER stored here:
| set SUPER_ADMIN_PASSWORD in .env (which must stay out of Git).
| SUPER_ADMIN_INITIAL_PASSWORD is still read as a fallback so an existing
| .env keeps working.
|
*/

return [
    'name' => env('SUPER_ADMIN_NAME', 'Devprosad Mallcick'),
    'email' => env('SUPER_ADMIN_EMAIL', 'mallick.jess@gmail.com'),
    'phone' => env('SUPER_ADMIN_PHONE', '01783057931'),
    'support_email' => env('SUPPORT_EMAIL', 'mnupur37@gmail.com'),
    'password' => env(
        'SUPER_ADMIN_PASSWORD',
        env('SUPER_ADMIN_INITIAL_PASSWORD')
    ),
];