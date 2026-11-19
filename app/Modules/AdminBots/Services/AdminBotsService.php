<?php

namespace App\Modules\AdminBots\Services;

use App\Helpers\WebhookEndpointHelper;
use App\Models\Bot;
use App\Modules\BotOwner\Contracts\BotOwnerProServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerRepositoryInterface;
use App\Modules\BotOwner\Models\BotOwner;
use App\Modules\BotRegistration\Contracts\BotRegistrationServiceInterface;
use App\Modules\BaleOtp\Support\PhoneNormalizer;

class AdminBotsService
{
    public function __construct(
        private readonly BotOwnerRepositoryInterface $ownerRepository,
        private readonly BotOwnerProServiceInterface $proService,
        private readonly BotRegistrationServiceInterface $registrationService,
    ) {
    }

    public function findOwnerByChatId(string $chatId): ?BotOwner
    {
        return $this->ownerRepository->findByBaleChatId($chatId);
    }

    public function linkOwnerByPhone(string $chatId, string $phone): array
    {
        $normalized = PhoneNormalizer::normalize($phone);
        if ($normalized === null) {
            return ['success' => false, 'message' => trans('bot-owner.invalid_phone')];
        }

        $owner = $this->ownerRepository->findByPhone($normalized);
        if (!$owner) {
            return ['success' => false, 'message' => trans('bot-owner.link_login_first')];
        }

        $owner->bale_chat_id = $chatId;
        $owner->save();

        return ['success' => true, 'message' => trans('bot-owner.link_success'), 'owner' => $owner];
    }

    public function listBots(BotOwner $owner): string
    {
        $bots = Bot::where('bot_owner_id', $owner->id)->orderByDesc('id')->get();

        if ($bots->isEmpty()) {
            return trans('bot-owner.no_bots_yet');
        }

        $message = trans('bot-owner.my_bots') . ":\n\n";
        foreach ($bots as $bot) {
            $name = $bot->bale_bot_name ?? $bot->telegram_bot_name ?? ('Bot #' . $bot->id);
            $message .= "• {$name} ({$bot->endpoint_id})\n";
        }

        return $message;
    }

    public function requestPro(BotOwner $owner): array
    {
        return $this->proService->requestPro($owner->id);
    }

    public function getEndpointsList(): array
    {
        return array_values(array_filter(
            WebhookEndpointHelper::getAvailableEndpoints(),
            fn ($e) => !in_array($e['id'], ['admin-bots', 'get-chat-id'], true)
        ));
    }

    public function registerBotFromMessenger(
        BotOwner $owner,
        string $chatId,
        string $token,
        string $endpointId,
        string $platform,
        int $botMotherId,
    ): array {
        return $this->registrationService->registerBot(
            $token,
            $endpointId,
            $platform,
            'fa',
            $botMotherId,
            $owner->id,
            $chatId,
        );
    }
}
