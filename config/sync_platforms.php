<?php

/*
|--------------------------------------------------------------------------
| Sync Platforms Registry (Phase 5 — engine generalization)
|--------------------------------------------------------------------------
|
| این فایل «منبع حقیقت» رفتار پلتفرم‌های مبدأ (webhook origin) برای موتور
| کانال‌پست‌ر و همگام‌سازی محتوایی است. هر کد سخت‌کدشده‌ی if/switch بر
| اساس origin باید به‌جای مقایسه‌ی رشته‌ها، این رجیستری را بخواند.
|
| کلیدها:
|  - token_column  : ستون bots که توکن ربات مبدأ را نگه می‌دارد
|  - owner_column  : ستون bots که chat_id مالک ربات مبدأ را نگه می‌دارد
|  - publisher     : 'vendor'  = SDK vendor (Telegram/Bale)
|                    'native'  = کلاینت داخلی (SoroushBotClient / RubikaBotClient)
|  - download_media_from_origin:
|      true  → ChannelPosterMediaStore می‌تواند فایل رسانه را از API مبدأ
|              دانلود کند (getFile مستندشده است: بله/تلگرام)
|      false → دانلود رسانه از API مبدأ «تأییدنشده» است (BLOCKED) و موتور
|              به‌صورت data-driven از دانلود صرف‌نظر می‌کند؛ file_id فقط روی
|              خودِ مبدأ قابل استفاده است (publish هم‌پلتفرم).
|
| پلتفرم‌های ناشناخته روی رفتار موروثی telegram (vendor + دانلود) fall back
| می‌کنند — منطق fall back در SyncPlatformRegistry.
|
| وضعیت LIVE_TESTED: هیچ کندی این رجیستری تا تست زنده (Phase 7) LIVE_TESTED
| نیست؛ همه MOCK_TESTED.
|
*/

return [

    'bale' => [
        'label' => 'Bale',
        'token_column' => 'bale_bot_token',
        'owner_column' => 'bale_owner_chat_id',
        'publisher' => 'vendor',
        'download_media_from_origin' => true,
    ],

    'telegram' => [
        'label' => 'Telegram',
        'token_column' => 'telegram_bot_token',
        'owner_column' => 'telegram_owner_chat_id',
        'publisher' => 'vendor',
        'download_media_from_origin' => true,
    ],

    'soroush' => [
        'label' => 'Soroush Plus',
        'token_column' => 'soroush_bot_token',
        'owner_column' => 'soroush_owner_chat_id',
        'publisher' => 'native',
        // getFile / دانلود رسانه از api.splus.ir مستند و تأییدنشده است
        // (docs/features/platform-api-research.md) — BLOCKED، پس false.
        'download_media_from_origin' => false,
    ],

    'rubika' => [
        'label' => 'Rubika',
        'token_column' => 'rubika_bot_token',
        'owner_column' => 'rubika_owner_chat_id',
        'publisher' => 'native',
        // دانلود رسانه از botapi.rubika.ir مستند نیست — BLOCKED، پس false.
        'download_media_from_origin' => false,
    ],

];
