<?php

namespace App\Services;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Models\Bot;
use App\Models\BotAdminKieRequest;
use App\Models\BotUsers;
use App\Models\LibraryBotConfig;
use App\Models\PsychologyTestBot;
use App\Models\PsychologyTestBotAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;

class BotAdminKieService
{
    public function __construct(
        private readonly BotAdminKieNotificationService $notificationService,
    ) {
    }

    public function isAdminkieCommand(?string $text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        $command = config('bot.hidden_admin_kie_command', '/adminkie');

        return mb_strtolower(trim($text)) === mb_strtolower($command);
    }

    /**
     * @return \Illuminate\Http\Response|null null = not handled, continue to controller
     */
    public function tryHandleFromRequest(Request $request): ?\Illuminate\Http\Response
    {
        $update = $request->json()->all() ?? $request->all();
        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if (!$message || empty($message['text'])) {
            return null;
        }

        $text = trim($message['text']);
        if (!$this->isAdminkieCommand($text)) {
            return null;
        }

        $origin = $request->input('origin', $request->query('origin', 'bale'));
        if (!in_array($origin, ['bale', 'telegram', 'gap', 'soroosh'], true)) {
            return null;
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        if ($chatId === '') {
            return null;
        }

        if (AdminHelper::isAdmin($chatId)) {
            return response('', 200);
        }

        $bot = $this->createBotFromRequest($request, $origin);
        if (!$bot) {
            Log::warning('[AdminKie] Could not create bot instance', ['origin' => $origin]);
            return response('', 200);
        }

        $rawBotId = $this->resolveBotId($request, $origin);
        $webhookEndpoint = $request->segment(2);
        $webhookToken = $request->input('token') ?? $request->query('token');
        $ownerBot = $this->resolveOwnerTargetBot($rawBotId, $webhookEndpoint, $webhookToken);

        if (!$ownerBot) {
            Log::warning('[AdminKie] Cannot resolve owner bot for request', [
                'raw_bot_id' => $rawBotId,
                'webhook_endpoint' => $webhookEndpoint,
                'has_token' => (bool) $webhookToken,
                'chat_id' => $chatId,
            ]);
            BotHelper::sendMessage($bot, trans('bot.admin_kie_bot_not_registered'));
            return response('', 200);
        }

        $ownerBotId = $ownerBot->id;
        $from = $message['from'] ?? [];
        $botUser = $this->findBotUser($chatId, $rawBotId ?? $ownerBotId, $origin);

        if ($this->isAlreadyBotOwner($ownerBot, $chatId, $origin)) {
            BotHelper::sendMessage($bot, trans('bot.admin_kie_already_owner'));
            return response('', 200);
        }

        $pending = BotAdminKieRequest::pending()
            ->where('chat_id', $chatId)
            ->where('origin', $origin)
            ->first();

        if ($pending) {
            BotHelper::sendMessage($bot, trans('bot.admin_kie_pending', ['id' => $pending->id]));
            return response('', 200);
        }

        $kieRequest = BotAdminKieRequest::create([
            'bot_id' => $ownerBotId,
            'chat_id' => $chatId,
            'origin' => $origin,
            'bot_user_id' => $botUser?->id,
            'first_name' => $from['first_name'] ?? null,
            'last_name' => $from['last_name'] ?? null,
            'username' => $from['username'] ?? null,
            'alias_name' => $botUser?->alias_name,
            'email' => $botUser?->email,
            'webhook_endpoint' => $webhookEndpoint,
            'status' => 'pending',
            'notes' => $this->buildRequestNotes($rawBotId, $webhookToken),
        ]);

        $this->notificationService->notifySuperAdmins($kieRequest);
        BotHelper::sendMessage($bot, trans('bot.admin_kie_submitted'));

        return response('', 200);
    }

    public function confirmRequest(int $requestId, int $approvedByChatId, ?int $overrideMainBotId = null): bool
    {
        $kieRequest = BotAdminKieRequest::pending()->find($requestId);
        if (!$kieRequest) {
            return false;
        }

        $bot = $this->resolveBotForConfirm($kieRequest, $overrideMainBotId);
        if (!$bot) {
            Log::error('[AdminKie] Bot not found for confirm', [
                'request_id' => $requestId,
                'bot_id' => $kieRequest->bot_id,
                'webhook_endpoint' => $kieRequest->webhook_endpoint,
                'override_main_bot_id' => $overrideMainBotId,
            ]);

            throw new \RuntimeException(trans('bot.admin_kie_bot_not_found', [
                'id' => $kieRequest->bot_id,
                'request_id' => $requestId,
            ]));
        }

        $chatId = (string) $kieRequest->chat_id;
        $origin = $kieRequest->origin;

        if ($origin === 'bale') {
            $bot->bale_owner_chat_id = $chatId;
        } elseif ($origin === 'telegram') {
            $bot->telegram_owner_chat_id = $chatId;
        }

        $bot->save();

        $this->syncPsychologyTestAdmin($bot, $chatId, $origin);

        $kieRequest->update([
            'status' => 'confirmed',
            'approved_by' => $approvedByChatId,
            'approved_at' => now(),
            'bot_id' => $bot->id,
        ]);

        $this->notifyUserApproved($bot, $kieRequest);

        return true;
    }

    private function syncPsychologyTestAdmin(Bot $bot, string $chatId, string $origin): void
    {
        if ($bot->endpoint_id !== 'webhook-psychology-test') {
            return;
        }

        $psychBot = PsychologyTestBot::where('bot_id', $bot->id)->first();
        if (!$psychBot) {
            return;
        }

        $exists = PsychologyTestBotAdmin::where('psychology_test_bot_id', $psychBot->id)
            ->where('chat_id', $chatId)
            ->where('origin', $origin)
            ->exists();

        if ($exists) {
            return;
        }

        $adminCount = PsychologyTestBotAdmin::where('psychology_test_bot_id', $psychBot->id)->count();
        if ($adminCount >= 3) {
            return;
        }

        PsychologyTestBotAdmin::create([
            'psychology_test_bot_id' => $psychBot->id,
            'chat_id' => $chatId,
            'origin' => $origin,
            'is_creator' => $adminCount === 0,
        ]);
    }

    private function notifyUserApproved(Bot $bot, BotAdminKieRequest $kieRequest): void
    {
        $token = $this->getStoredWebhookToken($kieRequest);
        if (!$token) {
            $token = $kieRequest->origin === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
        }

        if (!$token) {
            return;
        }

        try {
            $userBot = $kieRequest->origin === 'bale'
                ? new Telegram($token, 'bale')
                : new Telegram($token);

            BotHelper::sendMessageByChatId(
                $userBot,
                $kieRequest->chat_id,
                trans('bot.admin_kie_approved_user')
            );
        } catch (\Throwable $e) {
            Log::warning('[AdminKie] Failed to notify user', ['error' => $e->getMessage()]);
        }
    }

    private function createBotFromRequest(Request $request, string $origin): ?Telegram
    {
        $token = $request->input('token') ?? $request->query('token');
        if (!$token) {
            $botId = $this->resolveBotId($request, $origin);
            if ($botId) {
                $botModel = Bot::find($botId);
                if ($botModel) {
                    $token = $origin === 'bale' ? $botModel->bale_bot_token : $botModel->telegram_bot_token;
                }
            }
        }

        if (!$token) {
            return null;
        }

        return $origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }

    private function resolveBotId(Request $request, string $origin): ?int
    {
        $token = $request->input('token') ?? $request->query('token');
        if ($token) {
            $column = $origin === 'bale' ? 'bale_bot_token' : 'telegram_bot_token';
            $bot = Bot::where($column, $token)->first();
            if ($bot) {
                return $bot->id;
            }
        }

        $botId = $request->input('bot_id') ?? $request->query('bot_id');
        if ($botId && Bot::find((int) $botId)) {
            return (int) $botId;
        }

        return null;
    }

    private function findBotUser(string $chatId, ?int $botId, string $origin): ?BotUsers
    {
        if (!$botId) {
            return BotUsers::where('chat_id', $chatId)->where('origin', $origin)->first();
        }

        return BotUsers::where('chat_id', $chatId)
            ->where('bot_id', $botId)
            ->where('origin', $origin)
            ->first();
    }

    private function isAlreadyBotOwner(Bot $bot, string $chatId, string $origin): bool
    {
        if ($origin === 'bale') {
            return (string) $bot->bale_owner_chat_id === $chatId;
        }

        if ($origin === 'telegram') {
            return (string) $bot->telegram_owner_chat_id === $chatId;
        }

        return false;
    }

    /**
     * مالکیت ادمین باید روی ربات اصلی (مثلاً book-library) ثبت شود، نه reader.
     */
    private function resolveOwnerTargetBot(?int $botId, ?string $webhookEndpoint, ?string $webhookToken = null): ?Bot
    {
        if ($webhookToken) {
            $tokenBot = Bot::where('bale_bot_token', $webhookToken)
                ->orWhere('telegram_bot_token', $webhookToken)
                ->first();
            if ($tokenBot) {
                $botId = $tokenBot->id;
            }
        }

        if (!$botId) {
            return null;
        }

        $bot = Bot::find($botId);
        $isReaderContext = $this->isBookLibraryReaderContext($bot, $webhookEndpoint);

        if ($isReaderContext) {
            $mainBotId = LibraryBotConfig::where('reader_bot_id', $botId)->value('bot_id');
            if ($mainBotId) {
                $mainBot = Bot::find($mainBotId);
                if ($mainBot) {
                    return $mainBot;
                }
            }

            if ($webhookToken) {
                $mainBotId = LibraryBotConfig::query()
                    ->whereIn('reader_bot_id', Bot::where('bale_bot_token', $webhookToken)
                        ->orWhere('telegram_bot_token', $webhookToken)
                        ->pluck('id'))
                    ->value('bot_id');
                if ($mainBotId) {
                    return Bot::find($mainBotId);
                }
            }
        }

        return $bot;
    }

    private function resolveBotForConfirm(BotAdminKieRequest $kieRequest, ?int $overrideMainBotId = null): ?Bot
    {
        if ($overrideMainBotId) {
            $forced = Bot::find($overrideMainBotId);
            if ($forced) {
                return $forced;
            }
        }

        $webhookToken = $this->getStoredWebhookToken($kieRequest);
        $bot = $this->resolveOwnerTargetBot($kieRequest->bot_id, $kieRequest->webhook_endpoint, $webhookToken);
        if ($bot) {
            return $bot;
        }

        if ($this->isBookLibraryReaderContext(null, $kieRequest->webhook_endpoint)) {
            $mainBotId = LibraryBotConfig::where('reader_bot_id', $kieRequest->bot_id)->value('bot_id');
            if ($mainBotId) {
                return Bot::find($mainBotId);
            }
        }

        return null;
    }

    private function buildRequestNotes(?int $rawBotId, ?string $webhookToken): ?string
    {
        $meta = array_filter([
            'raw_bot_id' => $rawBotId,
            'webhook_token' => $webhookToken,
        ], fn ($value) => $value !== null && $value !== '');

        return $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE);
    }

    private function getStoredWebhookToken(BotAdminKieRequest $request): ?string
    {
        if (!$request->notes) {
            return null;
        }

        $meta = json_decode($request->notes, true);

        return is_array($meta) ? ($meta['webhook_token'] ?? null) : null;
    }

    private function isBookLibraryReaderContext(?Bot $bot, ?string $webhookEndpoint): bool
    {
        if ($bot && $bot->endpoint_id === 'book-library-reader') {
            return true;
        }

        if (!$webhookEndpoint) {
            return false;
        }

        return str_contains($webhookEndpoint, 'book-library-reader');
    }
}
