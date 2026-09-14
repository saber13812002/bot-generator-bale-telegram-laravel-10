<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Module Configuration
    |--------------------------------------------------------------------------
    |
    | تنظیمات ماژول مشترک سرویس‌دهنده هوش مصنوعی
    | اگر دیتابیس خالی بود، از این مقادیر استفاده می‌شود
    |
    */

    'default_base_url' => env('AI_DEFAULT_BASE_URL', 'https://ai.ismc.ir/api'),
    'default_api_key'  => env('AI_DEFAULT_API_KEY', ''),
    'default_model'    => env('AI_DEFAULT_MODEL', 'qwen38'),
    'default_prompt'   => env('AI_DEFAULT_PROMPT', 'سلام! جواب سلام بده و در ۴ کاراکتر'),

    // پلتفرم نوتیفیکیشن پیش‌فرض (bale|telegram)
    'notify_type' => env('AI_HEALTH_NOTIFY_TYPE', 'bale'),

    // توکن و چت آیدی اختصاصی — اگر ست شد روی مقادیر پیش‌فرض می‌نویسد
    'custom_bot_token' => env('AI_CUSTOM_BOT_TOKEN', env('BOT_MOTHER_TOKEN_BALE')),
    'custom_chat_id'   => env('AI_CUSTOM_CHAT_ID', env('SUPER_ADMIN_CHAT_ID_BALE')),

    // تنظیمات HTTP (معادل curl -k --noproxy "*")
    'http_options' => [
        'verify'          => false,  // نادیده گرفتن SSL — معادل -k در curl
        'timeout'         => 60,     // حداکثر زمان کل درخواست (ثانیه)
        'connect_timeout' => 30,     // حداکثر زمان اتصال (ثانیه)
        'proxy'           => [       // معادل --noproxy "*" در curl
            'no' => ['*'],
        ],
    ],
];
