<?php

namespace App\Services;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Models\Bot;
use App\Models\BotAdminKieRequest;
use Illuminate\Support\Facades\Log;
use Telegram;

class BotAdminKieNotificationService
{
    public function notifySuperAdmins(BotAdminKieRequest $request): void
    {
        $bot = $request->bot_id ? Bot::find($request->bot_id) : null;
        $botLabel = $bot
            ? ($bot->bale_bot_name ?? $bot->telegram_bot_name ?? "Bot #{$request->bot_id}")
            : ($request->webhook_endpoint ?? 'نامشخص');

        $id = $request->id;
        $message = "🔐 درخواست ادمین ربات (adminkie)\n\n";
        $message .= "🆔 Request ID: {$id}\n";
        $message .= "🤖 ربات: {$botLabel}\n";
        $message .= "🆔 Bot ID: " . ($request->bot_id ?? '—') . "\n";
        $message .= "👤 نام: {$request->displayName()}\n";
        $message .= "💬 Chat ID: {$request->chat_id}\n";
        $message .= "📱 Origin: {$request->origin}\n";

        if ($request->username) {
            $message .= "🔗 Username: @{$request->username}\n";
        }
        if ($request->alias_name) {
            $message .= "🏷 Alias: {$request->alias_name}\n";
        }
        if ($request->email) {
            $message .= "📧 Email: {$request->email}\n";
        }
        if ($request->webhook_endpoint) {
            $message .= "📡 Endpoint: {$request->webhook_endpoint}\n";
        }

        $message .= "\n📅 " . $request->created_at->format('Y-m-d H:i') . "\n\n";
        $message .= "✅ تایید ادمین:\n";
        $message .= "/adminbot_confirm {$id}\n";
        $message .= "/adminbot_confirm{$id}\n";

        if ($this->isBookLibraryReaderEndpoint($request->webhook_endpoint)) {
            $mainBots = Bot::where('endpoint_id', 'book-library')->orderByDesc('id')->get();
            if ($mainBots->isNotEmpty()) {
                $message .= "\n\n📚 تایید دستی (ربات اصلی کتابخانه):\n";
                foreach ($mainBots as $mainBot) {
                    $name = $mainBot->bale_bot_name ?? $mainBot->telegram_bot_name ?? ('Bot #' . $mainBot->id);
                    $message .= "/adminbot_confirm {$id} {$mainBot->id} — {$name}\n";
                }
            }
        }

        $message .= "\n❌ رد:\n";
        $message .= "/adminbot_reject {$id}\n";
        $message .= "/adminbot_reject{$id}";

        $this->sendToBotMother($message);
        $this->sendToAdminBots($message);
    }

    private function sendToBotMother(string $message): void
    {
        $configs = [
            ['token' => env('BOT_MOTHER_TOKEN_BALE'), 'type' => 'bale'],
            ['token' => env('BOT_MOTHER_TOKEN_TELEGRAM'), 'type' => 'telegram'],
        ];

        foreach (AdminHelper::getAdmins() as $chatId) {
            if (empty($chatId)) {
                continue;
            }

            foreach ($configs as $config) {
                if (empty($config['token'])) {
                    continue;
                }

                try {
                    $bot = $config['type'] === 'bale'
                        ? new Telegram($config['token'], 'bale')
                        : new Telegram($config['token']);
                    BotHelper::sendMessageByChatId($bot, $chatId, $message);
                } catch (\Throwable $e) {
                    Log::warning('[AdminKie] Failed to notify via Bot Mother', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    private function sendToAdminBots(string $message): void
    {
        $token = env('ADMIN_BOTS_TOKEN_BALE');
        if (empty($token)) {
            return;
        }

        foreach (AdminHelper::getAdmins() as $chatId) {
            if (empty($chatId)) {
                continue;
            }

            try {
                $bot = new Telegram($token, 'bale');
                BotHelper::sendMessageByChatId($bot, $chatId, $message);
            } catch (\Throwable $e) {
                Log::warning('[AdminKie] Failed to notify via Admin Bots', ['error' => $e->getMessage()]);
            }
        }
    }

    private function isBookLibraryReaderEndpoint(?string $endpoint): bool
    {
        return $endpoint && str_contains($endpoint, 'book-library-reader');
    }
}
