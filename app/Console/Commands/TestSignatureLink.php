<?php

namespace App\Console\Commands;

use App\Models\ChannelPosterDestination;
use App\Services\TestPostPreviewService;
use Illuminate\Console\Command;

/**
 * تست اختصاصی امضای لینک‌دار (Linked Signature) — دو روش مستقل:
 *   ۱) Inline Link : لینک داخل متن با نگارش مخصوص همان پیام‌رسان
 *   ۲) URL Button  : دکمه لینک‌دار زیر پیام (فقط telegram/eitaa/bale)
 *
 * از مسیرهای production ارسال می‌شود (TestPostPreviewService::sendSignatureLinkPreview).
 *
 * نمونه‌ها:
 *   php artisan app:test-signature-link --platform=telegram --token=QURAN_TELEGRAM_TOKEN --chat-id=151370482
 *   php artisan app:test-signature-link --platform=bale --token=BOTMOTHER_BALE_TOKEN --chat-id=485750575
 *   php artisan app:test-signature-link --platform=eitaa            (پیش‌فرض از .env)
 *   php artisan app:test-signature-link --platform=telegram --link=https://t.me/mychannel --label="ورود به کانال"
 *
 * پیش‌فرض‌ها از .env:
 *   TEST_PREVIEW_PLATFORM / TEST_PREVIEW_BOT_TOKEN / TEST_PREVIEW_CHAT_ID
 */
class TestSignatureLink extends Command
{
    protected $signature = 'app:test-signature-link
                            {--platform= : پیام‌رسان: eitaa|bale|telegram (پیش‌فرض از .env)}
                            {--token= : توکن ربات (پیش‌فرض TEST_PREVIEW_BOT_TOKEN)}
                            {--chat-id= : chat id مقصد (پیش‌فرض TEST_PREVIEW_CHAT_ID)}
                            {--link= : آدرس لینک کانال (پیش‌فرض https://example.com)}
                            {--label= : متن لیبل/دکمه (پیش‌فرض: برچسب پلتفرم از lang)}
                            {--base= : متن پایه بالای لینک}';

    protected $description = 'تست اختصاصی امضای لینک‌دار — ارسال لینک داخل متن + دکمه URL (مستقل برای هر پیام‌رسان)';

    public function handle(TestPostPreviewService $service): int
    {
        $platform = (string) ($this->option('platform') ?: env('TEST_PREVIEW_PLATFORM', 'eitaa'));
        $link = (string) ($this->option('link') ?: 'https://example.com');

        $labels = [
            ChannelPosterDestination::PLATFORM_BALE => trans('bot.channel_poster_sig_bale'),
            ChannelPosterDestination::PLATFORM_TELEGRAM => trans('bot.channel_poster_sig_telegram'),
            ChannelPosterDestination::PLATFORM_EITAA => trans('bot.channel_poster_sig_eitaa'),
            ChannelPosterDestination::PLATFORM_SOROUSH => trans('bot.channel_poster_sig_soroush'),
            ChannelPosterDestination::PLATFORM_RUBIKA => trans('bot.channel_poster_sig_rubika'),
        ];
        $label = (string) ($this->option('label') ?: ($labels[$platform] ?? $platform));

        $this->info("platform: {$platform} | chat: " . ($this->option('chat-id') ?: env('TEST_PREVIEW_CHAT_ID', '(unset)')) . " | link: {$link}");

        $result = $service->sendSignatureLinkPreview(
            $label,
            $link,
            $platform,
            $this->option('token') ? (string) $this->option('token') : null,
            $this->option('chat-id') ? (string) $this->option('chat-id') : null,
            (string) ($this->option('base') ?: '🔗 تست اختصاصی امضای لینک‌دار')
        );

        $sent = $result['sent'];
        $button = $result['button_supported'] ? ($sent['button'] ? '✅ دکمه ارسال شد' : '❌ دکمه ارسال نشد') : '— (این پیام‌رسان دکمه URL ندارد)';

        if ($result['success']) {
            $this->info("✅ ارسال شد — message_id: " . ($result['message_id'] ?? '—'));
            $this->line('   روش ۱ (لینک داخل متن): ' . ($sent['inline'] ? '✅' : '❌'));
            $this->line('   روش ۲ (دکمه URL)     : ' . $button);

            return self::SUCCESS;
        }

        $this->error('❌ ارسال ناموفق: ' . ($result['error'] ?? 'unknown error'));
        $this->comment('نکات: ربات باید عضو چت مقصد باشد؛ برای ایتا باید admin باشد.');

        return self::FAILURE;
    }
}
