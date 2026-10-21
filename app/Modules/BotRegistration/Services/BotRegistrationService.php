<?php

namespace App\Modules\BotRegistration\Services;

use App\Helpers\TokenHelper;
use App\Helpers\WebhookEndpointHelper;
use App\Models\Bot;
use App\Models\WebhookEndpoint;
use App\Modules\BotRegistration\Contracts\BotRegistrationServiceInterface;
use App\Services\RubikaBotClient;
use App\Services\SoroushBotClient;
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
        // Phase 4: soroush/rubika registration is now a shared capability
        // (registered via the native Phase 3 clients, not the vendor SDK).
        return ['telegram', 'bale', 'soroush', 'rubika'];
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
        if (!in_array($platform, ['telegram', 'bale', 'soroush', 'rubika'], true)) {
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
            // Phase 4: soroush/rubika use their native clients (Phase 3)
            if (in_array($platform, ['soroush', 'rubika'], true)) {
                return $this->registerNativeClientBot(
                    $token, $endpointId, $platform, $language, $botMotherId, $botOwnerId, $ownerChatId
                );
            }

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

    /**
     * Phase 4 — Soroush Plus / Rubika registration with the native clients.
     *
     * - soroush: Telegram-style envelope {ok,result,...}; getMe -> User,
     *   webhook via setWebhook, verified with getWebhookInfo (read-back).
     * - rubika: response envelope is undocumented (BLOCKED); success is
     *   2xx + a bot_id present in the getMe response. Webhook is registered
     *   with updateBotEndpoints(type=ReceiveUpdate); there is no read-back
     *   API, so webhook verification stays BLOCKED (client throws on >=400,
     *   which is the strongest signal available).
     *
     * @throws RuntimeException from the clients is caught by the caller.
     */
    private function registerNativeClientBot(
        string $token,
        string $endpointId,
        string $platform,
        string $language,
        int $botMotherId,
        ?int $botOwnerId,
        ?string $ownerChatId,
    ): array {
        $client = $platform === 'soroush'
            ? new SoroushBotClient($token)
            : new RubikaBotClient($token);

        // 1) getMe — validates the token and fetches the bot identity
        $getMe = $client->getMe();

        if ($platform === 'soroush') {
            if (!SoroushBotClient::isOk($getMe)) {
                throw new Exception($getMe['description'] ?? 'soroush getMe failed');
            }
            $profile = $getMe['result'] ?? [];
            $botName = $profile['username'] ?? $profile['first_name'] ?? null;
        } else {
            // Rubika: the envelope is unverified — look for the bot object
            // in result (documented shape) or at the top level.
            $profile = is_array($getMe['result'] ?? null) ? $getMe['result'] : $getMe;
            $botId = $profile['bot_id'] ?? $getMe['bot_id'] ?? null;
            if ($botId === null || $botId === '') {
                throw new Exception('rubika getMe returned no bot_id (envelope unverified)');
            }
            $botName = $profile['username'] ?? $profile['bot_title'] ?? null;
        }

        // 2) upsert by the per-platform token column (same pattern as telegram/bale)
        $existingBot = $platform === 'soroush'
            ? Bot::where('soroush_bot_token', $token)->first()
            : Bot::where('rubika_bot_token', $token)->first();

        $botItem = $existingBot ?? new Bot();
        $botItem->bot_mother_id = $botMotherId;
        $botItem->endpoint_id = $endpointId;
        $botItem->language_code = $language;
        $botItem->type = $platform;
        $botItem->bot_owner_id = $botOwnerId;

        if ($platform === 'soroush') {
            $botItem->soroush_owner_chat_id = $ownerChatId;
            $botItem->soroush_bot_name = $botName;
            $botItem->soroush_bot_token = $token;
            $botItem->soroush_get_me_api_response = json_encode($profile);
            $botItem->soroush_bot_status = 'Active';
        } else {
            $botItem->rubika_owner_chat_id = $ownerChatId;
            $botItem->rubika_bot_name = $botName;
            $botItem->rubika_bot_token = $token;
            $botItem->rubika_get_me_api_response = json_encode($profile);
            $botItem->rubika_bot_status = 'Active';
        }

        $botItem->save();

        // 3) set the child webhook
        $webhookUrl = WebhookEndpointHelper::createWebhookUrl($endpointId, $botItem, $platform, $language, $botMotherId);

        if ($platform === 'soroush') {
            $setResult = $client->setWebhook($webhookUrl);
            if (!SoroushBotClient::isOk($setResult)) {
                Log::error('BotRegistration soroush webhook failed', [
                    'bot_id' => $botItem->id,
                    'webhook_url' => $webhookUrl,
                    'result' => $setResult,
                ]);

                return ['success' => false, 'message' => trans('bot-owner.webhook_failed')];
            }

            // 4) verify with getWebhookInfo (the documented read-back)
            try {
                $info = $client->getWebhookInfo();
                if (SoroushBotClient::isOk($info)) {
                    Log::info('BotRegistration soroush webhook verified', [
                        'bot_id' => $botItem->id,
                        'url_matches' => str_contains(
                            json_encode($info['result'] ?? '') ?: '',
                            (string) (parse_url($webhookUrl, PHP_URL_PATH) ?: $webhookUrl)
                        ),
                    ]);
                } else {
                    Log::warning('BotRegistration soroush getWebhookInfo failed (webhook may still be set)', [
                        'bot_id' => $botItem->id,
                        'result' => $info,
                    ]);
                }
            } catch (Exception $e) {
                Log::warning('BotRegistration soroush getWebhookInfo error (webhook may still be set)', [
                    'bot_id' => $botItem->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $botItem->soroush_webhook_is_set = 1;
        } else {
            // Rubika: register the ReceiveUpdate endpoint. No read-back API
            // exists → verification stays BLOCKED; the client throws on >=400.
            $client->updateEndpoint($webhookUrl, 'ReceiveUpdate');
            Log::info('BotRegistration rubika webhook endpoint registered (verification BLOCKED — no read-back API)', [
                'bot_id' => $botItem->id,
                'webhook_url' => $webhookUrl,
            ]);
            $botItem->rubika_webhook_is_set = 1;
        }

        $botItem->save();

        return [
            'success' => true,
            'message' => trans('bot-owner.bot_created', ['name' => $botName ?? $botItem->id]),
            'bot' => $botItem,
        ];
    }
}
