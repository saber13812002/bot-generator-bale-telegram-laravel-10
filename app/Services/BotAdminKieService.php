<?php

namespace App\Services;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Models\Bot;
use App\Models\BotAdminKieRequest;
use App\Models\BotUsers;
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

        $botId = $this->resolveBotId($request, $origin);
        $from = $message['from'] ?? [];
        $botUser = $this->findBotUser($chatId, $botId, $origin);

        if ($botId) {
            $botModel = Bot::find($botId);
            if ($botModel && $this->isAlreadyBotOwner($botModel, $chatId, $origin)) {
                BotHelper::sendMessage($bot, trans('bot.admin_kie_already_owner'));
                return response('', 200);
            }
        }

        $pending = BotAdminKieRequest::pending()
            ->where('chat_id', $chatId)
            ->where('origin', $origin)
            ->when($botId, fn ($q) => $q->where('bot_id', $botId))
            ->first();

        if ($pending) {
            BotHelper::sendMessage($bot, trans('bot.admin_kie_pending'));
            return response('', 200);
        }

        $kieRequest = BotAdminKieRequest::create([
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'origin' => $origin,
            'bot_user_id' => $botUser?->id,
            'first_name' => $from['first_name'] ?? null,
            'last_name' => $from['last_name'] ?? null,
            'username' => $from['username'] ?? null,
            'alias_name' => $botUser?->alias_name,
            'email' => $botUser?->email,
            'webhook_endpoint' => $request->segment(2),
            'status' => 'pending',
        ]);

        $this->notificationService->notifySuperAdmins($kieRequest);
        BotHelper::sendMessage($bot, trans('bot.admin_kie_submitted'));

        return response('', 200);
    }

    public function confirmRequest(int $requestId, int $approvedByChatId): bool
    {
        $kieRequest = BotAdminKieRequest::pending()->find($requestId);
        if (!$kieRequest) {
            return false;
        }

        if (!$kieRequest->bot_id) {
            throw new \RuntimeException('ربات مشخص نیست؛ bot_id در درخواست خالی است.');
        }

        $bot = Bot::find($kieRequest->bot_id);
        if (!$bot) {
            throw new \RuntimeException('ربات یافت نشد.');
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
        $token = $kieRequest->origin === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
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
        $botId = $request->input('bot_id') ?? $request->query('bot_id');
        if ($botId) {
            return (int) $botId;
        }

        $token = $request->input('token') ?? $request->query('token');
        if (!$token) {
            return null;
        }

        $column = $origin === 'bale' ? 'bale_bot_token' : 'telegram_bot_token';
        $bot = Bot::where($column, $token)->first();

        return $bot?->id;
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
}
