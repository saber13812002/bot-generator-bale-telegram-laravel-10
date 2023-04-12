<?php

return [
    'key' => env('TRANSLATIONIO_KEY'),
    'source_locale' => 'en',
    'target_locales' => ['ar-IQ', 'zh-CN', 'fr', 'de-DE', 'fa', 'ru', 'es', 'tr', 'ur'],

    /* Directories to scan for Gettext strings */
    'gettext_parse_paths' => ['app', 'resources']
];
