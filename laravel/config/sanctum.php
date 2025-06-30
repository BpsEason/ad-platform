<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sanctum Stateful Domains
    |--------------------------------------------------------------------------
    |
    | This is the list of domains for which the Sanctum middleware will
    | authenticate stateful sessions. This allows your front-end to make
    | requests to your API without having to send a token every time.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guard
    |--------------------------------------------------------------------------
    |
    | This is the guard that the Sanctum middleware will use to authenticate
    | requests. This guard is configured in your `config/auth.php` file.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Sanctum Expiration
    |--------------------------------------------------------------------------
    |
    | This is the number of minutes after which a session token will expire.
    | If you don't want your tokens to expire, set this value to `null`.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Personal Access Tokens
    |--------------------------------------------------------------------------
    |
    | You may configure the `Sanctum` personal access token to expire after
    | a certain number of minutes. This is useful for security purposes.
    |
    */

    'personal_access_token_expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticated, Sanctum will check for a valid session token. This
    | token is stored in the `sanctum_token` cookie. You may configure the
    | cookie name and path here.
    |
    */

    'middleware' => [
        'api' => 'auth:sanctum',
    ],
];
