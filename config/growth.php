<?php

return [
    'llm' => [
        'api_key' => env('GROWTH_LLM_API_KEY'),
        'base_url' => env('GROWTH_LLM_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('GROWTH_LLM_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('GROWTH_LLM_TIMEOUT', 20),
    ],
];
