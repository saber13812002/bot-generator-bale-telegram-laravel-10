<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Helpers\ProductLinkMessageHelper;
use App\Models\AdminDailyChannelConfig;
use App\Services\BotHealthRecorder;
use App\Services\DailyChannelContentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram;

class PostDailyVerseToChannels extends Command
{
    protected $signature = 'daily-channel:post
                            {--slot= : اسلات ۱–۴ (۱=۰۰:۰۰، ۲=۰۶:۰۰، ۳=۱۲:۰۰، ۴=۱۸:۰۰). اگر ندهی از ساعت فعلی محاسبه می‌شود}
                            {--dry-run : فقط نمایش، بدون ارسال}';

    protected $description = 'ارسال آیه/حدیث/نهج/شراب بهشتی به کانال‌های ثبت‌شده؛ فرکانس بر اساس posts_per_day (۱/۲/۴ بار در روز)';

    public function __construct(
        private DailyChannelContentService $contentService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $slot = $this->resolveSlot();
        Log::info('[PostDailyVerseToChannels] Job started (diagnostic marker)', ['slot' => $slot]);
        $configs = AdminDailyChannelConfig::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (AdminDailyChannelConfig $c) => $c->shouldRunInSlot($slot));

        if ($configs->isEmpty()) {
            $this->info("هیچ کانال فعالی برای اسلات {$slot} ثبت نشده است.");
            return 0;
        }

        $dryRun = $this->option('dry-run');
        $baleToken = env('DAILY_CHANNEL_BOT_TOKEN_BALE') ?: env('QURAN_HEFZ_BOT_TOKEN_BALE');
        $telegramToken = env('DAILY_CHANNEL_BOT_TOKEN_TELEGRAM') ?: env('QURAN_HEFZ_BOT_TOKEN_TELEGRAM');
        $eitaaToken = env('BOT_EITAA_TOKEN_SABER') ?: env('EITAA_BOT_TOKEN');

        foreach ($configs as $config) {
            $contentType = $config->content_type;
            $effectiveType = $contentType;

            if ($contentType === 'sequential') {
                $effectiveType = $this->getNextSequentialType($config->last_sent_content_type);
            }

            $text = $this->getTextForConfig($config, $effectiveType, 'messenger');
            if (!$text || trim($text) === '') {
                Log::warning('[PostDailyVerseToChannels] No content for config', [
                    'config_id' => $config->id,
                    'content_type' => $contentType,
                ]);
                continue;
            }

            if ($dryRun) {
                $this->line("Config #{$config->id} ({$contentType}" . ($contentType === 'sequential' ? " → {$effectiveType}" : '') . "): " . mb_substr($text, 0, 80) . '...');
                continue;
            }

            if ($config->hasBale() && $baleToken) {
                $this->sendAndRecordHealth($config, $effectiveType, 'bale', function () use ($config, $effectiveType, $baleToken) {
                    $baleText = $this->getTextForConfig($config, $effectiveType, 'bale');
                    $bot = new Telegram($baleToken, 'bale');

                    return BotHelper::sendMessageByChatId($bot, (string) $config->bale_channel_chat_id, $baleText);
                });
            }

            if ($config->hasTelegram() && $telegramToken) {
                $this->sendAndRecordHealth($config, $effectiveType, 'telegram', function () use ($config, $effectiveType, $telegramToken) {
                    $telegramText = $this->getTextForConfig($config, $effectiveType, 'telegram');
                    $bot = new Telegram($telegramToken);

                    return BotHelper::sendMessageByChatId($bot, (string) $config->telegram_channel_chat_id, $telegramText);
                });
            }

            if ($config->hasEitaa() && $eitaaToken) {
                $this->sendAndRecordHealth($config, $effectiveType, 'eitaa', function () use ($config, $effectiveType, $eitaaToken) {
                    $eitaaText = $this->getTextForConfig($config, $effectiveType, 'eitaa');

                    return BotHelper::sendMessageEitaaSupport(
                        $eitaaText,
                        $eitaaToken,
                        $config->eitaa_channel_chat_id,
                        'eitaa',
                        ProductLinkMessageHelper::parseModeForPlatform('eitaa')
                    );
                });
            }

            if ($contentType === 'sequential') {
                $config->update(['last_sent_content_type' => $effectiveType]);
            }
        }

        $this->info("ارسال به کانال‌ها (اسلات {$slot}) انجام شد.");
        return 0;
    }

    /**
     * اسلات ۱–۴ را از گزینه یا از ساعت فعلی برمی‌گرداند.
     * اسلات ۱=۰۰:۰۰، ۲=۰۶:۰۰، ۳=۱۲:۰۰، ۴=۱۸:۰۰
     */
    private function resolveSlot(): int
    {
        $opt = $this->option('slot');
        if ($opt !== null && $opt !== '') {
            $s = (int) $opt;
            if ($s >= 1 && $s <= 4) {
                return $s;
            }
        }
        $hour = (int) now()->format('G');
        if ($hour >= 0 && $hour < 6) {
            return 1;
        }
        if ($hour >= 6 && $hour < 12) {
            return 2;
        }
        if ($hour >= 12 && $hour < 18) {
            return 3;
        }
        return 4;
    }

    /**
     * برای content_type=sequential: نوبت بعدی را بر اساس last_sent برمی‌گرداند.
     * ترتیب: verse → hadith → nahj → sharabe_beheshti → verse → …
     */
    private function getNextSequentialType(?string $lastSent): string
    {
        $order = AdminDailyChannelConfig::SEQUENTIAL_ORDER;
        if ($lastSent === null || $lastSent === '') {
            return $order[0];
        }
        $idx = array_search($lastSent, $order, true);
        if ($idx === false) {
            return $order[0];
        }
        $next = $idx + 1;

        return $order[$next % count($order)];
    }

    private function getTextForConfig(AdminDailyChannelConfig $config, string $effectiveType, string $platformSlug): ?string
    {
        if ($effectiveType === AdminDailyChannelConfig::CONTENT_TYPE_SHARABE_BEHESHTI) {
            return $this->contentService->getRandomSharabeBeheshtiTextForPlatform($platformSlug);
        }

        return $this->contentService->getTextForContentType($effectiveType);
    }

    private function sendAndRecordHealth(AdminDailyChannelConfig $config, string $featureKey, string $platform, callable $sender): void
    {
        try {
            $response = $sender();
            $ok = BotHealthRecorder::isMessengerOk($response);
            $failDetail = $ok ? null : $this->describeMessengerResponse($response);
            BotHealthRecorder::record([
                'feature_key' => $featureKey,
                'platform' => $platform,
                'event_type' => 'channel_post',
                'status' => $ok ? 'ok' : 'fail',
                'message' => $ok ? null : $failDetail,
                'meta' => [
                    'config_id' => $config->id,
                    'content_type' => $config->content_type,
                ],
            ]);
            if (!$ok) {
                $failText = (string) $failDetail;
                Log::warning("[PostDailyVerseToChannels] {$platform} send not ok", [
                    'config_id' => $config->id,
                    'feature_key' => $featureKey,
                    'chat_id' => match ($platform) {
                        'bale' => $config->bale_channel_chat_id,
                        'telegram' => $config->telegram_channel_chat_id,
                        'eitaa' => $config->eitaa_channel_chat_id,
                        default => null,
                    },
                    'bot_id' => null,
                    'diagnosis' => $this->diagnoseSendFailure($failText),
                    'api_response' => $failDetail,
                ]);
            }
        } catch (\Throwable $e) {
            BotHealthRecorder::record([
                'feature_key' => $featureKey,
                'platform' => $platform,
                'event_type' => 'channel_post',
                'status' => 'fail',
                'message' => $e->getMessage(),
                'meta' => [
                    'config_id' => $config->id,
                    'content_type' => $config->content_type,
                ],
            ]);
            Log::warning("[PostDailyVerseToChannels] {$platform} send failed", [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * تشخیص ساده‌ی خطاهای رایج ارسال برای راهنمایی عملیاتی در لاگ.
     */
    private function diagnoseSendFailure(string $failDetail): ?string
    {
        $lower = mb_strtolower($failDetail);

        if (str_contains($lower, 'chat not found')
            || str_contains($lower, 'chat_id is invalid')
            || str_contains($lower, 'peers not found')
            || str_contains($lower, 'group chat not found')) {
            return 'channel chat_id is invalid or the bot was removed/kicked from the channel — fix the *_channel_chat_id in AdminDailyChannelConfig or re-add the bot';
        }

        if (str_contains($lower, 'token_hash_invalid')
            || str_contains($lower, 'invalid token')
            || str_contains($lower, 'unauthorized')) {
            return 'bot token invalid — check the channel bot token env vars';
        }

        if (str_contains($lower, 'blocked') || str_contains($lower, 'permission_denied')) {
            return 'chat has blocked the bot or the bot lacks permission for this chat';
        }

        return null;
    }

    /**
     * خلاصه‌ی پاسخ API (Telegram/Bale/Eitaa) برای لاگ خطا: دلیل دقیق رد شدن (chat not found / TOKEN_HASH_INVALID / ...).
     */
    private function describeMessengerResponse(mixed $response): string
    {
        if (is_array($response)) {
            $str = json_encode($response, JSON_UNESCAPED_UNICODE);
            return mb_substr($str, 0, 500);
        }

        if (is_object($response)) {
            $parts = [];
            foreach (['ok', 'error_code', 'description', 'error'] as $key) {
                if (isset($response->$key) && is_scalar($response->$key)) {
                    $parts[$key] = $response->$key;
                }
            }
            if ($parts) {
                $str = json_encode($parts, JSON_UNESCAPED_UNICODE);
                return mb_substr($str, 0, 500);
            }
            return get_class($response);
        }

        return is_string($response) ? mb_substr($response, 0, 500) : gettype($response);
    }
}
