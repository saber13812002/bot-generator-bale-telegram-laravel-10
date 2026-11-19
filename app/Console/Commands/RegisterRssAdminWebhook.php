<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Helpers\WebhookEndpointHelper;
use App\Models\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram;

class RegisterRssAdminWebhook extends Command
{
    protected $signature = 'rss-admin:register-webhook
                            {--token= : Bot token (or use RSS_ADMIN_BOT_TOKEN_BALE / RSS_ADMIN_BOT_TOKEN_TELEGRAM)}
                            {--origin=bale : bale or telegram}
                            {--bot-id= : Existing bot ID in Nova (optional, creates minimal record if omitted)}';

    protected $description = 'Register webhook for RSS Admin bot (webhook-rss-admin)';

    public function handle(): int
    {
        $type = $this->option('origin') === 'telegram' ? 'telegram' : 'bale';
        $token = $this->option('token')
            ?: ($type === 'bale' ? env('RSS_ADMIN_BOT_TOKEN_BALE') : env('RSS_ADMIN_BOT_TOKEN_TELEGRAM'));

        if (!$token) {
            $this->error('Token is required. Pass --token= or set RSS_ADMIN_BOT_TOKEN_BALE / RSS_ADMIN_BOT_TOKEN_TELEGRAM in .env');

            return 1;
        }

        $bot = $this->resolveBot($token, $type);
        if (!$bot) {
            return 1;
        }

        $telegramBot = new Telegram($token, $type);
        $webhookUrl = WebhookEndpointHelper::createWebhookUrl(
            'webhook-rss-admin',
            $bot,
            $type,
            $bot->language_code ?? 'fa',
            $bot->bot_mother_id ?? 1,
        );

        $this->info("Webhook URL: {$webhookUrl}");

        $result = $telegramBot->setWebhook($webhookUrl);
        if (!($result['ok'] ?? false)) {
            $this->error('setWebhook failed: ' . ($result['description'] ?? 'unknown'));
            Log::error('RSS Admin webhook registration failed', ['result' => $result]);

            return 1;
        }

        if ($type === 'bale') {
            $bot->bale_webhook_is_set = 1;
            $bot->bale_bot_status = 'Active';
        } else {
            $bot->telegram_webhook_is_set = 1;
            $bot->telegram_bot_status = 'Active';
        }
        $bot->save();

        $info = BotHelper::checkWebhookInfo($token, $type);
        $this->info('Webhook registered. URL: ' . ($info['result']['url'] ?? 'n/a'));
        $this->info("Bot ID in Nova: {$bot->id} — endpoint_id: {$bot->endpoint_id}");

        return 0;
    }

    private function resolveBot(string $token, string $type): ?Bot
    {
        $botId = $this->option('bot-id');
        if ($botId) {
            $bot = Bot::find($botId);
            if (!$bot) {
                $this->error("Bot #{$botId} not found.");

                return null;
            }

            return $bot;
        }

        $column = $type === 'bale' ? 'bale_bot_token' : 'telegram_bot_token';
        $bot = Bot::where($column, $token)->first();

        if ($bot) {
            $bot->endpoint_id = 'webhook-rss-admin';
            $bot->type = $type;
            if ($type === 'bale') {
                $bot->bale_bot_token = $token;
            } else {
                $bot->telegram_bot_token = $token;
            }
            $bot->save();
            $this->info("Updated existing bot #{$bot->id}.");

            return $bot;
        }

        $bot = new Bot();
        $bot->endpoint_id = 'webhook-rss-admin';
        $bot->type = $type;
        $bot->language_code = 'fa';
        $bot->bot_mother_id = 1;
        if ($type === 'bale') {
            $bot->bale_bot_token = $token;
            $bot->bale_bot_status = 'Active';
        } else {
            $bot->telegram_bot_token = $token;
            $bot->telegram_bot_status = 'Active';
        }
        $bot->save();

        $this->info("Created new bot #{$bot->id} in Nova.");

        return $bot;
    }
}
