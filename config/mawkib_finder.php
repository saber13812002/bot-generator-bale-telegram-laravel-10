<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mawkib Finder API URLs
    |--------------------------------------------------------------------------
    |
    | When URLs are empty, mock responses are returned automatically.
    | Set these in .env when real endpoints are available.
    |
    */
    'verify_url' => env('MAWKIB_FINDER_VERIFY_URL'),
    'availability_url' => env('MAWKIB_FINDER_AVAILABILITY_URL'),
    'registration_url' => env('MAWKIB_FINDER_REGISTRATION_URL', 'https://example.com/mawkib-register'),

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('MAWKIB_FINDER_HTTP_TIMEOUT', 30),
    'verify_ssl' => env('MAWKIB_FINDER_VERIFY_SSL', true),
];
