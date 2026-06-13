<?php

namespace App\Modules\BotRegistration\Services;

use App\Helpers\TokenHelper;
use App\Helpers\WebhookEndpointHelper;
use App\Models\Bot;
use App\Models\WebhookEndpoint;
use App\Modules\BotRegistration\Contracts\BotRegistrationServiceInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class BotRegistrationService implements BotRegistrationServiceInterface
{
    public function getEndpoint(string $endpointId): ?WebhookEndpoint
    {
        return WebhookEndpoint::where('endpoint_id', $endpointId)
            ->where('is_active', true)
            ->first();
    }

    public function getSupportedPlatforms(WebhookEndpoint $endpoint): array
    {
        return ['telegram', 'bale'];
    }

    public function registerBot(
        string $token,
        string $endpointId,
        string $platform,
        string $language,
        int $botMotherId,
        ?int $botOwnerId = null,
        ?string $ownerChatId = null,
    ): array {
        if (!in_array($platform, ['telegram', 'bale'], true)) {
            return ['success' => false, 'message' => trans('bot-owner.invalid_platform')];
        }

        if (!TokenHelper::isToken($token, $platform)) {
            return ['success' => false, 'message' => trans('bot-owner.invalid_token')];
        }

        $endpoint = $this->getEndpoint($endpointId);
        if (!$endpoint) {
            return ['success' => false, 'message' => trans('bot-owner.endpoint_not_found')];
        }

        try {
            $newBot = new Telegram($token, $platform);
            $getMe = $newBot->getMe();

            if (!($getMe['ok'] ?? false)) {
                throw new Exception($getMe['description'] ?? 'getMe failed');
            }

            $existingBot = $platform === 'bale'
                ? Bot::where('bale_bot_token', $token)->first()
                : Bot::where('telegram_bot_token', $token)->first();

            $botItem = $existingBot ?? new Bot();
            $botItem->bot_mother_id = $botMotherId;
            $botItem->endpoint_id = $endpointId;
            $botItem->language_code = $language;
            $botItem->type = $platform;
            $botItem->bot_owner_id = $botOwnerId;

            if ($platform === 'bale') {
                $botItem->bale_owner_chat_id = $ownerChatId;
                $botItem->bale_bot_name = $getMe['result']['username'] ?? null;
                $botItem->bale_bot_token = $token;
                $botItem->bale_get_me_api_response = json_encode($getMe['result']);
                $botItem->bale_bot_status = 'Active';
            } else {
                $botItem->telegram_owner_chat_id = $ownerChatId;
                $botItem->telegram_bot_name = $getMe['result']['username'] ?? null;
                $botItem->telegram_bot_token = $token;
                $botItem->telegram_get_me_api_response = json_encode($getMe['result']);
                $botItem->telegram_bot_status = 'Active';
            }

            $botItem->save();

            $webhookUrl = WebhookEndpointHelper::createWebhookUrl($endpointId, $botItem, $platform, $language, $botMotherId);
            $setWebhookResult = $newBot->setWebhook($webhookUrl);

            if (!($setWebhookResult['ok'] ?? false)) {
                Log::error('BotRegistration webhook failed', [
                    'bot_id' => $botItem->id,
                    'webhook_url' => $webhookUrl,
                    'result' => $setWebhookResult,
                ]);

                return ['success' => false, 'message' => trans('bot-owner.webhook_failed')];
            }

            if ($platform === 'bale') {
                $botItem->bale_webhook_is_set = 1;
            } else {
                $botItem->telegram_webhook_is_set = 1;
            }
            $botItem->save();

            $botName = $platform === 'bale' ? $botItem->bale_bot_name : $botItem->telegram_bot_name;

            return [
                'success' => true,
                'message' => trans('bot-owner.bot_created', ['name' => $botName ?? $botItem->id]),
                'bot' => $botItem,
            ];
        } catch (Exception $e) {
            Log::error('BotRegistration failed', [
                'endpoint_id' => $endpointId,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => trans('bot-owner.bot_creation_failed')];
        }
    }
}
