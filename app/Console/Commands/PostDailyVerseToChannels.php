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
                            {--dry-run : فقط نمایش، بدون ارسال}';

    protected $description = 'ارسال روزانه یک آیه/حدیث/نهج/شراب بهشتی به کانال‌های ثبت‌شده در admin_daily_channel_configs';

    public function __construct(
        private DailyChannelContentService $contentService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $configs = AdminDailyChannelConfig::query()->where('is_active', true)->get();
        if ($configs->isEmpty()) {
            $this->info('هیچ کانال فعالی برای ارسال روزانه ثبت نشده است.');
            return 0;
        }

        $dryRun = $this->option('dry-run');
        $baleToken = env('DAILY_CHANNEL_BOT_TOKEN_BALE') ?: env('QURAN_HEFZ_BOT_TOKEN_BALE');
        $telegramToken = env('DAILY_CHANNEL_BOT_TOKEN_TELEGRAM') ?: env('QURAN_HEFZ_BOT_TOKEN_TELEGRAM');
        $eitaaToken = env('BOT_EITAA_TOKEN_SABER') ?: env('EITAA_BOT_TOKEN');

        foreach ($configs as $config) {
            $text = $this->contentService->getTextForContentType($config->content_type);
            if (!$text || trim($text) === '') {
                Log::warning('[PostDailyVerseToChannels] No content for config', [
                    'config_id' => $config->id,
                    'content_type' => $config->content_type,
                ]);
                continue;
            }

            if ($dryRun) {
                $this->line("Config #{$config->id} ({$config->content_type}): " . mb_substr($text, 0, 80) . '...');
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
        }

        $this->info('ارسال روزانه به کانال‌ها انجام شد.');
        return 0;
    }
}
