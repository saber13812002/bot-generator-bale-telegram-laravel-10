<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Models\AdminChannelMediaQueueConfig;
use App\Models\MediaQueueItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram;

class PostMediaQueueToChannels extends Command
{
    protected $signature = 'media-queue:post
                            {--dry-run : فقط نمایش، بدون ارسال}';

    protected $description = 'ارسال نوبتی آیتم صف رسانه به کانال‌های ثبت‌شده در admin_channel_media_queue_configs';

    public function handle(): int
    {
        $configs = AdminChannelMediaQueueConfig::query()->where('is_active', true)->get();
        if ($configs->isEmpty()) {
            $this->info('هیچ config فعالی برای صف رسانه ثبت نشده است.');
            return 0;
        }

        $dryRun = $this->option('dry-run');
        $baleToken = env('DAILY_CHANNEL_BOT_TOKEN_BALE') ?: env('QURAN_HEFZ_BOT_TOKEN_BALE');
        $telegramToken = env('DAILY_CHANNEL_BOT_TOKEN_TELEGRAM') ?: env('QURAN_HEFZ_BOT_TOKEN_TELEGRAM');
        $eitaaToken = env('BOT_EITAA_TOKEN_SABER') ?: env('EITAA_BOT_TOKEN');

        foreach ($configs as $config) {
            $queue = $config->mediaQueue;
            if (!$queue) {
                continue;
            }
            $items = $queue->items;
            if ($items->isEmpty()) {
                Log::warning('[PostMediaQueueToChannels] Queue has no items', ['config_id' => $config->id]);
                continue;
            }
            $index = (int) $config->last_sent_item_index;
            $item = $items->get($index);
            if (!$item) {
                $item = $items->first();
                $index = 0;
            }

            if ($dryRun) {
                $this->line("Config #{$config->id} queue «{$queue->name}» item #{$index} ({$item->content_type}): " . mb_substr($item->content_text ?? '', 0, 50) . '...');
                continue;
            }

            $textForEitaa = $item->content_text ?? '(متن)';

            if ($config->hasBale() && $baleToken) {
                $this->sendItemToChat($item, (string) $config->bale_channel_chat_id, $baleToken, 'bale', $config->id);
            }
            if ($config->hasTelegram() && $telegramToken) {
                $this->sendItemToChat($item, (string) $config->telegram_channel_chat_id, $telegramToken, 'telegram', $config->id);
            }
            if ($config->hasEitaa() && $eitaaToken) {
                try {
                    BotHelper::sendMessageEitaaSupport($textForEitaa, $eitaaToken, $config->eitaa_channel_chat_id, 'eitaa');
                } catch (\Throwable $e) {
                    Log::warning('[PostMediaQueueToChannels] Eitaa send failed', ['config_id' => $config->id, 'error' => $e->getMessage()]);
                }
            }

            $nextIndex = ($index + 1) % $items->count();
            $config->update(['last_sent_item_index' => $nextIndex]);
        }

        $this->info('ارسال صف رسانه به کانال‌ها انجام شد.');
        return 0;
    }

    private function sendItemToChat(MediaQueueItem $item, string $chatId, string $token, string $platform, int $configId): void
    {
        try {
            $bot = $platform === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
            $text = $item->content_text ?? '(متن خالی)';
            $fileId = $platform === 'bale' ? $item->file_id_bale : $item->file_id_telegram;

            if ($item->content_type === MediaQueueItem::TYPE_PHOTO && $fileId) {
                $bot->sendPhoto([
                    'chat_id' => $chatId,
                    'photo' => $fileId,
                    'caption' => $text,
                ]);
            } elseif ($item->content_type === MediaQueueItem::TYPE_VIDEO && $fileId) {
                $bot->sendVideo([
                    'chat_id' => $chatId,
                    'video' => $fileId,
                    'caption' => $text,
                ]);
            } else {
                BotHelper::sendMessageByChatId($bot, $chatId, $text);
            }
        } catch (\Throwable $e) {
            Log::warning('[PostMediaQueueToChannels] Send failed', [
                'config_id' => $configId,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
