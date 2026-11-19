<?php

namespace App\Console\Commands;

use App\Helpers\ProductLinkMessageHelper;
use App\Models\ChannelPosterDestination;
use App\Services\TestPostPreviewService;
use Illuminate\Console\Command;

/**
 * تست امضای لینک‌دار (Channel Signature) — ارسال پیش‌نمایش امضا با نگارش مخصوص هر پلتفرم
 * از طریق همان متدهای production (TestPostPreviewService → BotHelper/Telegram SDK).
 *
 * نمونه‌ها:
 *   php artisan app:test-signature --platform=eitaa
 *   php artisan app:test-signature --platform=bale --chat-id=6877922777 --token=TOKEN
 *   php artisan app:test-signature --platform=telegram --link=https://t.me/mychannel
 *
 * پیش‌فرض‌ها از .env:
 *   TEST_PREVIEW_PLATFORM / TEST_PREVIEW_BOT_TOKEN / TEST_PREVIEW_CHAT_ID
 */
class TestSignature extends Command
{
    protected $signature = 'app:test-signature
                            {--platform= : پلتفرم: eitaa|bale|telegram|soroush|rubika (پیش‌فرض از .env)}
                            {--token= : توکن ربات (پیش‌فرض TEST_PREVIEW_BOT_TOKEN)}
                            {--chat-id= : chat id مقصد (پیش‌فرض TEST_PREVIEW_CHAT_ID)}
                            {--link= : لینک کانال (پیش‌فرض https://example.com)}
                            {--base= : متن پایه قبل از امضا}';

    protected $description = 'ارسال پیش‌نمایش امضای لینک‌دار به چت تستی — از همان متدهای production';

    public function handle(TestPostPreviewService $service): int
    {
        $platform = (string) ($this->option('platform') ?: env('TEST_PREVIEW_PLATFORM', 'eitaa'));

        $labels = [
            ChannelPosterDestination::PLATFORM_BALE => trans('bot.channel_poster_sig_bale'),
            ChannelPosterDestination::PLATFORM_TELEGRAM => trans('bot.channel_poster_sig_telegram'),
            ChannelPosterDestination::PLATFORM_EITAA => trans('bot.channel_poster_sig_eitaa'),
            ChannelPosterDestination::PLATFORM_SOROUSH => trans('bot.channel_poster_sig_soroush'),
            ChannelPosterDestination::PLATFORM_RUBIKA => trans('bot.channel_poster_sig_rubika'),
        ];
        $label = $labels[$platform] ?? $platform;
        $link = (string) ($this->option('link') ?: 'https://example.com');

        // دقیقاً همان ساختاری که buildSignature($botId, $tag, $platform) در production می‌سازد
        $signatureLine = $label . ":\n" . ProductLinkMessageHelper::signatureLink($link, $platform);
        $parseMode = ProductLinkMessageHelper::parseModeForPlatform($platform);

        $base = (string) ($this->option('base') ?: '📢 تست امضای لینک‌دار');
        $message = $base . "\n\n" . $signatureLine;

        $this->info("platform: {$platform} | parse_mode: " . ($parseMode ?? 'null') . " | chat: " . ($this->option('chat-id') ?: env('TEST_PREVIEW_CHAT_ID', '(unset)')));
        $this->line('--- متن ارسالی ---');
        $this->line($message);
        $this->line('-------------------');

        $result = $service->sendPreview(
            'text',
            $message,
            null,
            $platform,
            $this->option('token') ? (string) $this->option('token') : null,
            $this->option('chat-id') ? (string) $this->option('chat-id') : null,
            null,
            null,
            $parseMode
        );

        if ($result['success']) {
            $this->info("✅ امضای لینک‌دار ({$platform}) ارسال شد — message_id: " . ($result['message_id'] ?? '—'));

            return self::SUCCESS;
        }

        $this->error('❌ ارسال ناموفق: ' . ($result['error'] ?? 'unknown error'));
        $this->comment('نکات: ربات باید عضو چت مقصد باشد؛ برای ایتا باید admin باشد.');

        return self::FAILURE;
    }
}
