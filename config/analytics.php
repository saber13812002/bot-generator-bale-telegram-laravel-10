<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Analytics (GA4)
    |--------------------------------------------------------------------------
    |
    | تنظیمات Google Analytics 4
    | برای دریافت Measurement ID به https://analytics.google.com مراجعه کنید
    |
    */
    'google_analytics' => [
        'enabled' => env('ANALYTICS_GA_ENABLED', false),
        'measurement_id' => env('ANALYTICS_GA_MEASUREMENT_ID', 'G-XXXXXXXXXX'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft Clarity
    |--------------------------------------------------------------------------
    |
    | تنظیمات Microsoft Clarity برای تحلیل رفتار کاربر
    | برای دریافت Project ID به https://clarity.microsoft.com مراجعه کنید
    |
    */
    'clarity' => [
        'enabled' => env('ANALYTICS_CLARITY_ENABLED', false),
        'project_id' => env('ANALYTICS_CLARITY_PROJECT_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hotjar
    |--------------------------------------------------------------------------
    |
    | تنظیمات Hotjar برای تحلیل رفتار کاربر و Heatmaps
    | برای دریافت Site ID به https://www.hotjar.com مراجعه کنید
    |
    */
    'hotjar' => [
        'enabled' => env('ANALYTICS_HOTJAR_ENABLED', false),
        'site_id' => env('ANALYTICS_HOTJAR_SITE_ID', ''),
    ],
];
