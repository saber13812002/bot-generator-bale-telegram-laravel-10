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

    // ── مدل‌های توانایی‌های اضافی (WS-B) ─────────────────────────
    // خالی = از settings provider در دیتابیس، سپس مقدار پیش‌فرض آداپتور
    'vision_model'    => env('AI_VISION_MODEL'),
    'asr_model'       => env('AI_ASR_MODEL'),
    'embedding_model' => env('AI_EMBEDDING_MODEL'),
    'tts_model'       => env('AI_TTS_MODEL'),

    // دستور OCR پیش‌فرض برای VisionAdapter
    'vision_ocr_prompt' => env('AI_VISION_OCR_PROMPT', 'متن این تصویر را دقیق و کامل استخراج کن. ترتیب سطرها و پاراگراف‌ها را حفظ کن. فقط متن خروجی بده.'),

    // پلتفرم نوتیفیکیشن پیش‌فرض (bale|telegram)
    'notify_type' => env('AI_HEALTH_NOTIFY_TYPE', 'bale'),

    // توکن و چت آیدی اختصاصی — اگر ست شد روی مقادیر پیش‌فرض می‌نویسد
    'custom_bot_token' => env('AI_CUSTOM_BOT_TOKEN', env('BOT_MOTHER_TOKEN_BALE')),
    'custom_chat_id'   => env('AI_CUSTOM_CHAT_ID', env('SUPER_ADMIN_CHAT_ID_BALE')),

    // تنظیمات HTTP
    // verify: اعتبارسنجی SSL — پیش‌فرض فعال (Strict TLS)
    //   ۱/true: اعتبارسنجی با CA bundle — پیش‌فرض bundle خود رپوزیتری
    //           (storage/certs/ismc-ai-ca-bundle.pem) چون bundle پیش‌فرض
    //           cURL/PHP در همه محیط‌ها زنجیره Certum را کامل ندارد.
    //   ۰/false: غیرفعال (فقط برای دیباگ — معادل -k در curl)
    //   env AI_CA_BUNDLE_PATH: مسیر bundle سفارشی (اولویت بالاتر)
    'http_options' => [
        'verify'          => env('AI_VERIFY_SSL', true)
            ? (env('AI_CA_BUNDLE_PATH') ?: base_path('storage/certs/ismc-ai-ca-bundle.pem'))
            : false,
        'timeout'         => 60,     // حداکثر زمان کل درخواست (ثانیه)
        'connect_timeout' => 30,     // حداکثر زمان اتصال (ثانیه)
        'proxy'           => [       // معادل --noproxy "*" در curl
            'no' => ['*'],
        ],
    ],
];
