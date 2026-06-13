<?php

namespace App\Modules\BotOwner\Services;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Modules\BotOwner\Models\BotOwnerProRequest;
use Illuminate\Support\Facades\Log;
use Telegram;

class BotOwnerProNotificationService
{
    public function notifySuperAdmins(BotOwnerProRequest $request): void
    {
        $owner = $request->botOwner;
        if (!$owner) {
            return;
        }

        $message = "💳 درخواست Pro مالک ربات\n\n";
        $message .= "📱 Phone: {$owner->phone}\n";
        $message .= "🆔 Owner ID: {$owner->id}\n";
        $message .= "🆔 Request ID: {$request->id}\n";
        $message .= "📅 Created: " . $request->created_at->format('Y-m-d H:i') . "\n\n";
        $message .= "تایید: /owner_pro_confirm {$request->id}\n";
        $message .= "Nova: " . url('/nova/resources/bot-owner-pro-requests/' . $request->id) . "\n";
        $message .= "Web: " . route('admin.bot-owner-pro.approve', $request->id);

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
                    Log::warning('Failed to notify admin via Bot Mother', ['error' => $e->getMessage()]);
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
                Log::warning('Failed to notify admin via Admin Bots', ['error' => $e->getMessage()]);
            }
        }
    }
}
