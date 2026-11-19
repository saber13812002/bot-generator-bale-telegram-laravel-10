<?php

return [
    'key' => env('TRANSLATIONIO_KEY'),

    /*
     | Driver ترجمه/انتقال RSS
     |
     | one_api : فقط one-api.ir (اپی‌های google/microsoft/targoman/faraazin)
     | llm     : فقط LLM محلی (AiProviderService — سرور ISMC)
     | auto    : اول one-api.ir، در صورت شکست LLM محلی (پیش‌فرض)
     */
    'driver' => env('TRANSLATION_DRIVER', 'auto'),

    'source_locale' => 'en',
    'target_locales' => ['ar-IQ', 'az', 'bs', 'zh-CN', 'fr', 'de-DE', 'he', 'fa', 'pt-BR', 'pt-PT', 'ru', 'es', 'tr', 'ur'],
    /* Directories to scan for Gettext strings */
    'gettext_parse_paths' => ['app', 'resources']
];
