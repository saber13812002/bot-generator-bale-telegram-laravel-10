<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Secret path for the log viewer
    |--------------------------------------------------------------------------
    |
    | Full URL: {APP_URL}/api/{LOG_VIEWER_SECRET}
    | Leave empty to disable the route. Use a long random string (32+ chars).
    |
    */
    'viewer_secret' => env('LOG_VIEWER_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Secret for Prometheus /api/metrics
    |--------------------------------------------------------------------------
    |
    | Leave empty to disable the route. Scrape with ?token= or /api/metrics/{secret}
    | or Authorization: Bearer.
    |
    */
    'metrics_secret' => env('METRICS_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Secret for JSON /api/health
    |--------------------------------------------------------------------------
    |
    | Leave empty to disable the route. Same token styles as metrics.
    |
    */
    'health_secret' => env('HEALTH_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Max rows kept in app_log_entries
    |--------------------------------------------------------------------------
    */
    'log_max_rows' => (int) env('APP_LOG_MAX_ROWS', 5000),

    /*
    |--------------------------------------------------------------------------
    | Health event retention (days)
    |--------------------------------------------------------------------------
    */
    'health_retention_days' => (int) env('BOT_HEALTH_RETENTION_DAYS', 30),
];
