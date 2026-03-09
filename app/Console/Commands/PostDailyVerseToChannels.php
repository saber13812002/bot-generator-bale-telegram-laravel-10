<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Models\AdminDailyChannelConfig;
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

            $text = $this->contentService->getTextForContentType($effectiveType);
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
                try {
                    $bot = new Telegram($baleToken, 'bale');
                    BotHelper::sendMessageByChatId($bot, (string) $config->bale_channel_chat_id, $text);
                } catch (\Throwable $e) {
                    Log::warning('[PostDailyVerseToChannels] Bale send failed', [
                        'config_id' => $config->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($config->hasTelegram() && $telegramToken) {
                try {
                    $bot = new Telegram($telegramToken);
                    BotHelper::sendMessageByChatId($bot, (string) $config->telegram_channel_chat_id, $text);
                } catch (\Throwable $e) {
                    Log::warning('[PostDailyVerseToChannels] Telegram send failed', [
                        'config_id' => $config->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($config->hasEitaa() && $eitaaToken) {
                try {
                    BotHelper::sendMessageEitaaSupport(
                        $text,
                        $eitaaToken,
                        $config->eitaa_channel_chat_id,
                        'eitaa'
                    );
                } catch (\Throwable $e) {
                    Log::warning('[PostDailyVerseToChannels] Eitaa send failed', [
                        'config_id' => $config->id,
                        'error' => $e->getMessage(),
                    ]);
                }
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
}
