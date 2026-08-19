<?php

return [
    'llm' => [
        'api_key' => env('GROWTH_LLM_API_KEY'),
        'base_url' => env('GROWTH_LLM_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('GROWTH_LLM_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('GROWTH_LLM_TIMEOUT', 20),
    ],
    'pro' => [
        'monthly_price' => (int) env('GROWTH_PRO_MONTHLY_PRICE', 99000),
        'monthly_promo' => (int) env('GROWTH_PRO_MONTHLY_PROMO', 49000),
        'yearly_price' => (int) env('GROWTH_PRO_YEARLY_PRICE', 300000),
        'card' => env('GROWTH_PRO_CARD', ''),
        'admin' => env('ADMIN_CONTACT_USERNAME', '@sabertaba'),
    ],
];
