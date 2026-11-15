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
    'growth_companion_model' => env('AI_GROWTH_COMPANION_MODEL'),

    // دستور OCR پیش‌فرض برای VisionAdapter
    'vision_ocr_prompt' => env('AI_VISION_OCR_PROMPT', 'متن این تصویر را دقیق و کامل استخراج کن. ترتیب سطرها و پاراگراف‌ها را حفظ کن. فقط متن خروجی بده.'),

    // پلتفرم نوتیفیکیشن پیش‌فرض (bale|telegram)
    'notify_type' => env('AI_HEALTH_NOTIFY_TYPE', 'bale'),

    // WS-J: strict L5 — اگر در ai_providers هیچ row فعالی نباشد، AiFeatureGate
    // رد می‌کند (reason 'provider_missing') به‌جای safe-by-default pass.
    // پیش‌فرض true (production)؛ در phpunit.xml false است تا تست‌های
    // موجود بدون provider row دست‌نخورده بمانند (strict جدا test شده).
    'require_active_provider' => env('AI_REQUIRE_ACTIVE_PROVIDER', true),

    // توکن و چت آیدی اختصاصی — اگر ست شد روی مقادیر پیش‌فرض می‌نویسد
    'custom_bot_token' => env('AI_CUSTOM_BOT_TOKEN', env('BOT_MOTHER_TOKEN_BALE')),
    'custom_chat_id'   => env('AI_CUSTOM_CHAT_ID', env('SUPER_ADMIN_CHAT_ID_BALE')),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags (WS-E) — کلیدهای روشن/خاموش سراسری
    |--------------------------------------------------------------------------
    |
    | لایه‌ی اول AiFeatureGate. هر capability مستقل است (decision owner #3).
    | همه پیش‌فرض خاموش → rollout تدریجی (staged): هر bot/capability جدا
    | روشن می‌شود. override هر bot در bots.ai_settings (لایه دوم، Nova).
    |
    */
    'flags' => [
        'ai_global_enabled'           => env('AI_FLAG_GLOBAL', false),
        'ai_llm_enabled'              => env('AI_FLAG_LLM', false),
        'ai_ocr_enabled'              => env('AI_FLAG_OCR', false),
        'ai_asr_enabled'              => env('AI_FLAG_ASR', false),
        'ai_rag_enabled'              => env('AI_FLAG_RAG', false),
        'ai_growth_companion_enabled' => env('AI_FLAG_GROWTH', false),
        'ai_tts_enabled'              => env('AI_FLAG_TTS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rollout تدریجی (AI-PILOT-ROLLOUT-05)
    |--------------------------------------------------------------------------
    |
    | Stage سراسری rollout (L2.5 دروازه):
    |  0 = internal  — فقط حساب‌های داخلی/تستی (fail-closed: بدون allowlist هیچ)
    |  1 = pilot     — فقط customerهای allowlist صریح (fail-closed)
    |  2 = all-Pro   — فقط کاربران Pro (یا pro-override ثبت‌شده)
    |  3 = public    — عمومی
    |
    | پیش‌فرض 0 (fail-safe production): تا وقتی owner stage را صریح set نکند،
    | AI به هیچ customer جدیدی دسترسی نمی‌دهد.
    | override هر bot در bots.ai_settings['ai_rollout_stage'] (Nova).
    | در phpunit.xml برابر 3 است تا رفتار تست‌های WS-E دست‌نخورده بماند.
    |
    */
    'rollout' => [
        'stage' => env('AI_ROLLOUT_STAGE', 0),
    ],

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

    /*
    |--------------------------------------------------------------------------
    | AI Queue (WS-D)
    |--------------------------------------------------------------------------
    |
    | صف مستقل و پایدار برای کارهای هوش مصنوعی (LLM/OCR/ASR/...):
    | - اتصال صریح 'database' روی queue 'ai' — مستقل از QUEUE_CONNECTION=sync
    |   (بقیه اپ هنوز sync می‌ماند؛ ریسک صفر برای فرآیندهای فعلی)
    | - worker: cron دقیقه‌ای cPanel → queue:work --queue=ai --stop-when-empty
    |   (Supervisor در هاستینگ اشتراکی نیست؛ docs/QUEUE_AI_WORKER.md)
    | - سیاست زمان (Asia/Tehran): عادی 00–09 و 14–24 فوری، 09–14 DEFERRED؛
    |   Pro ۲۴ ساعته. flush روزانه 14:00 (UTC 10:30) + flush هر ۱۰ دقیقه.
    |
    */
    'queue' => [
        'connection'      => 'database',
        'queue'           => 'ai',
        'concurrency'     => 1,          // هم‌زمانی worker (cron → یک instance در هر دقیقه)
        'max_time'        => 50,         // ثانیه؛ worker قبل از timeout cron (60s) تمام می‌شود
        'max_jobs'        => 200,        // سقف کار در هر اجرای worker
        'timeout'         => 180,        // ثانیه؛ سقف عمر هر job (job-level timeout)
        'tries'           => 3,          // تعداد تلاش‌های کل
        'backoff'         => [30, 120, 300], // ثانیه؛ فاصله بین تلاش‌ها
        'deferred_max_age_hours' => 48,  // DEFERRED قدیمی‌تر از این → EXPIRED
        'stale_after_minutes'    => 20,  // PROCESSING با heartbeat کهنه‌تر از این → recover
        'max_attempts'     => 5,         // سقف attempt قبل از FAILED (dead letter)
        'expires_after_hours' => 24,     // سقف کلی عمر job از moment خلق
        // سقف هم‌زمانی PROCESSING بر حسب bot (جلوگیری از انحصار یک bot)
        'max_processing_per_bot' => 5,
    ],
];
