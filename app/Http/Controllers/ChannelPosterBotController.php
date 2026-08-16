<?php

namespace App\Http\Controllers;

use App\Interfaces\Services\ChannelPosterBotService;
use App\Interfaces\Services\ChannelPosterPublisher;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Helpers\TokenHelper;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\ChannelPosterDestination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChannelPosterBotController extends Controller
{
    public const ENDPOINT_ID = 'webhook-channel-poster';

    public const STATE_AWAITING_BALE_FORWARD = 'cp_awaiting_bale_forward';
    public const STATE_AWAITING_DESTINATION = 'cp_awaiting_destination';
    public const STATE_AWAITING_UNTAGGED_NAME = 'cp_awaiting_untagged_name';
    public const STATE_AWAITING_TAG_NAME = 'cp_awaiting_tag_name';
    public const STATE_AWAITING_PLATFORM = 'cp_awaiting_platform';
    public const STATE_AWAITING_TELEGRAM_TOKEN = 'cp_awaiting_telegram_token';
    public const STATE_AWAITING_TELEGRAM_CHAT_ID = 'cp_awaiting_telegram_chat_id';
    public const STATE_AWAITING_EITAA_TOKEN = 'cp_awaiting_eitaa_token';
    public const STATE_AWAITING_EITAA_CHAT_ID = 'cp_awaiting_eitaa_chat_id';

    public function __construct(
        private ChannelPosterBotService $service,
        private ChannelPosterPublisherFactory $publisherFactory
    ) {
    }

    public function webhook(Request $request): JsonResponse
    {
        Log::info('[ChannelPoster] Webhook received', [
            'origin' => $request->input('origin'),
            'has_token' => $request->has('token'),
            'has_bot_id' => $request->has('bot_id'),
        ]);

        try {
            $type = $request->input('origin', 'bale');
            $resolved = $this->resolveBotAndToken($request, $type);
            if (!$resolved) {
                Log::warning('[ChannelPoster] Bot or token missing');
                return response()->json(['status' => 'error'], 200);
            }

            [$botItem, $token] = $resolved;
            if ($botItem->endpoint_id !== self::ENDPOINT_ID) {
                Log::warning('[ChannelPoster] Wrong endpoint', [
                    'bot_id' => $botItem->id,
                    'endpoint_id' => $botItem->endpoint_id,
                ]);
                return response()->json(['status' => 'error'], 200);
            }

            if ($botItem->language_code) {
                app()->setLocale($botItem->language_code);
            }

            $publisher = $this->publisherFactory->make($token, $type);
            $update = $this->extractUpdate($request);

            Log::info('[ChannelPoster] Bot resolved', [
                'bot_id' => $botItem->id,
                'endpoint_id' => $botItem->endpoint_id,
                'owner' => $type === 'bale' ? $botItem->bale_owner_chat_id : $botItem->telegram_owner_chat_id,
                'update_keys' => array_keys($update),
            ]);

            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($publisher, $update['callback_query'], $botItem, $type);
                return response()->json(['status' => 'ok'], 200);
            }

            $message = $update['message'] ?? $update['edited_message'] ?? null;
            if (!is_array($message)) {
                Log::info('[ChannelPoster] No private message in update', [
                    'update_keys' => array_keys($update),
                ]);
                return response()->json(['status' => 'ok'], 200);
            }

            $chatType = strtolower((string) ($message['chat']['type'] ?? ''));
            $chatId = (string) ($message['chat']['id'] ?? $message['from']['id'] ?? '');
            $isChannelChat = in_array($chatType, ['channel', 'group', 'supergroup', 'groups'], true);
            $isPrivate = !$isChannelChat && (
                in_array($chatType, ['private', 'pv', 'user'], true)
                || ($chatId !== '' && (int) $chatId > 0)
            );
            if (!$isPrivate) {
                Log::info('[ChannelPoster] Ignored non-private chat', [
                    'chat_id' => $chatId,
                    'chat_type' => $chatType,
                    'message_keys' => array_keys($message),
                ]);
                return response()->json(['status' => 'ok'], 200);
            }

            if ($chatId === '') {
                Log::warning('[ChannelPoster] Empty chat id');
                return response()->json(['status' => 'ok'], 200);
            }

            $this->handlePrivateMessage($publisher, $message, $botItem, $type, $chatId);

            return response()->json(['status' => 'ok'], 200);
        } catch (Throwable $e) {
            Log::error('[ChannelPoster] Webhook error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }
    }

    private function resolveBotAndToken(Request $request, string $type): ?array
    {
        $token = $request->input('token');
        $botId = $request->input('bot_id');

        if ($token) {
            $botItem = $type === 'bale'
                ? Bot::where('bale_bot_token', $token)->first()
                : Bot::where('telegram_bot_token', $token)->first();

            if ($botItem) {
                return [$botItem, $token];
            }
        }

        if ($botId) {
            $botItem = Bot::find($botId);
            if (!$botItem) {
                return null;
            }
            $dbToken = $type === 'bale' ? $botItem->bale_bot_token : $botItem->telegram_bot_token;
            if (!$dbToken) {
                return null;
            }

            return [$botItem, $dbToken];
        }

        return null;
    }

    private function extractUpdate(Request $request): array
    {
        $candidates = [];

        $raw = $request->getContent();
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $candidates[] = $decoded;
            }
        }

        $json = $request->json()->all();
        if (is_array($json) && $json !== []) {
            $candidates[] = $json;
        }

        $all = $request->all();
        if (is_array($all) && $all !== []) {
            $candidates[] = $all;
        }

        foreach ($candidates as $data) {
            unset($data['origin'], $data['token'], $data['bot_id'], $data['bot_mother_id'], $data['language']);
            if (isset($data['message']) || isset($data['callback_query']) || isset($data['edited_message'])) {
                return $data;
            }
            if (isset($data['chat']) && (isset($data['message_id']) || isset($data['from']))) {
                return ['message' => $data];
            }
        }

        $fallback = $candidates[0] ?? [];
        unset($fallback['origin'], $fallback['token'], $fallback['bot_id'], $fallback['bot_mother_id'], $fallback['language']);

        return is_array($fallback) ? $fallback : [];
    }

    private function handlePrivateMessage(
        ChannelPosterPublisher $publisher,
        array $message,
        Bot $botItem,
        string $type,
        string $chatId
    ): void {
        if (!$this->service->isOwner($botItem, $chatId, $type)) {
            if ($this->service->claimOwnerIfEmpty($botItem, $chatId, $type)) {
                Log::info('[ChannelPoster] Claimed empty owner', [
                    'bot_id' => $botItem->id,
                    'chat_id' => $chatId,
                ]);
            } else {
                Log::warning('[ChannelPoster] Not owner', [
                    'bot_id' => $botItem->id,
                    'chat_id' => $chatId,
                    'owner' => $type === 'bale' ? $botItem->bale_owner_chat_id : $botItem->telegram_owner_chat_id,
                ]);
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_not_owner'));
                return;
            }
        }

        $text = $message['text'] ?? null;

        if (is_string($text) && ($text === '/start' || str_starts_with($text, '/start '))) {
            $this->clearState($botItem, $chatId, $type);
            $this->handleStart($publisher, $botItem, $type, $chatId);
            return;
        }

        if (is_string($text) && ($text === '/cancel' || str_starts_with($text, '/cancel '))) {
            $this->clearState($botItem, $chatId, $type);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_cancelled'));
            return;
        }

        if (is_string($text) && ($text === '/add' || str_starts_with($text, '/add '))) {
            $this->startAddWizard($publisher, $botItem, $type, $chatId);
            return;
        }

        $state = $this->getState($botItem, $chatId, $type);

        if ($state && is_string($text) && $this->isShortTagName($text)) {
            if ($state->state === self::STATE_AWAITING_UNTAGGED_NAME) {
                $this->saveUntaggedName($publisher, $botItem, $type, $chatId, $text);
                return;
            }
            if ($state->state === self::STATE_AWAITING_TAG_NAME) {
                $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_PLATFORM, ['tag' => trim($text)]);
                $this->sendPlatformKeyboard($publisher, $chatId, trim($text));
                return;
            }
        }

        if ($state && is_string($text) && !str_starts_with($text, '/')) {
            if ($state->state === self::STATE_AWAITING_TELEGRAM_TOKEN) {
                $this->handleTelegramToken($publisher, $botItem, $type, $chatId, $text, $state->data ?? []);
                return;
            }
            if ($state->state === self::STATE_AWAITING_TELEGRAM_CHAT_ID) {
                $this->handleExternalChatId($publisher, $botItem, $type, $chatId, $text, $state->data ?? [], ChannelPosterDestination::PLATFORM_TELEGRAM);
                return;
            }
            if ($state->state === self::STATE_AWAITING_EITAA_TOKEN) {
                $this->handleEitaaToken($publisher, $botItem, $type, $chatId, $text, $state->data ?? []);
                return;
            }
            if ($state->state === self::STATE_AWAITING_EITAA_CHAT_ID) {
                $this->handleExternalChatId($publisher, $botItem, $type, $chatId, $text, $state->data ?? [], ChannelPosterDestination::PLATFORM_EITAA);
                return;
            }
        }

        $forward = $this->service->parseChannelTarget($message);
        $hasDestination = $this->service->hasAnyDestination($botItem->id);

        if ($forward && (!$hasDestination || ($state && $state->state === self::STATE_AWAITING_BALE_FORWARD))) {
            $this->handleBaleForward($publisher, $message, $botItem, $type, $chatId, $state);
            return;
        }

        if ($state && $state->state === self::STATE_AWAITING_BALE_FORWARD) {
            $this->handleBaleForward($publisher, $message, $botItem, $type, $chatId, $state);
            return;
        }

        $media = $this->service->extractMedia($message);
        if (!$media) {
            if (is_string($text) && str_starts_with($text, '/')) {
                return;
            }
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_unsupported'));
            return;
        }

        if (!$this->service->hasAnyDestination($botItem->id)) {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_BALE_FORWARD);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_bale_forward'));
            return;
        }

        $this->offerOrPublishContent($publisher, $botItem, $type, $chatId, $media);
    }

    private function handleStart(ChannelPosterPublisher $publisher, Bot $botItem, string $type, string $chatId): void
    {
        if (!$this->service->hasAnyDestination($botItem->id)) {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_BALE_FORWARD);
            $publisher->sendPrivateMessage(
                $chatId,
                trans('bot.channel_poster_welcome')."\n\n".trans('bot.channel_poster_ask_bale_forward')
            );
            return;
        }

        if ($this->service->hasUntagged($botItem->id)) {
            $untagged = $this->service->resolveByTag($botItem->id, '')->first();
            $title = $untagged?->channel_title ?: trans('bot.channel_poster_untagged');
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_UNTAGGED_NAME);
            $publisher->sendPrivateMessage(
                $chatId,
                trans('bot.channel_poster_ask_tag_for_existing', ['title' => $title])
            );
            return;
        }

        $labels = array_map(fn (array $group) => $group['label'], $this->service->listTagGroups($botItem->id));
        $publisher->sendPrivateMessage(
            $chatId,
            trans('bot.channel_poster_welcome_ready', ['tags' => implode('، ', $labels)])
        );
    }

    private function startAddWizard(ChannelPosterPublisher $publisher, Bot $botItem, string $type, string $chatId): void
    {
        $groups = $this->service->listTagGroups($botItem->id);
        if ($groups === []) {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_TAG_NAME);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_tag_name'));
            return;
        }

        $rows = [];
        foreach ($groups as $index => $group) {
            $rows[] = [[
                'text' => $group['label'],
                'callback_data' => 'cp:addtag:'.$index,
            ]];
        }
        $rows[] = [[
            'text' => trans('bot.channel_poster_btn_new_tag'),
            'callback_data' => 'cp:addtag:new',
        ]];

        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_TAG_NAME, [
            'tag_keys' => array_map(fn (array $group) => $group['key'], $groups),
        ]);
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_new_or_existing_tag'), $rows);
    }

    private function handleBaleForward(
        ChannelPosterPublisher $publisher,
        array $message,
        Bot $botItem,
        string $type,
        string $chatId,
        ?BotUserState $state
    ): void {
        $forward = $this->service->parseChannelTarget($message);
        if (!$forward) {
            Log::info('[ChannelPoster] Channel target not parsed', [
                'chat_id' => $chatId,
                'message_keys' => array_keys($message),
                'forward_from_chat' => $message['forward_from_chat'] ?? null,
                'has_forward_date' => isset($message['forward_date']),
                'has_forward_from' => isset($message['forward_from']),
                'forward_origin' => $message['forward_origin'] ?? null,
                'sender_chat' => $message['sender_chat'] ?? null,
                'text' => isset($message['text']) ? mb_substr((string) $message['text'], 0, 80) : null,
            ]);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_need_channel_forward'));
            return;
        }

        $ok = $publisher->sendTestMessage(
            $forward['id'],
            trans('bot.channel_poster_test_message')
        );

        if (!$ok) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_not_admin'));
            return;
        }

        $tag = is_string($state?->getData('tag')) ? trim((string) $state->getData('tag')) : null;
        if ($tag === '') {
            $tag = null;
        }

        Log::info('[ChannelPoster] Bale channel connected', [
            'bot_id' => $botItem->id,
            'channel_chat_id' => $forward['id'],
            'title' => $forward['title'] ?? null,
            'tag' => $tag,
        ]);

        $this->service->saveBaleDestination($botItem->id, $forward['id'], $forward['title'] ?? null, $tag);
        if ($tag) {
            $this->clearState($botItem, $chatId, $type);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_tag_saved', ['tag' => $tag]));
            return;
        }

        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_UNTAGGED_NAME);
        $publisher->sendPrivateMessage(
            $chatId,
            trans('bot.channel_poster_bale_connected')."\n\n".trans('bot.channel_poster_ask_tag_name')
        );
    }

    private function handleCallbackQuery(
        ChannelPosterPublisher $publisher,
        array $callbackQuery,
        Bot $botItem,
        string $type
    ): void {
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? $callbackQuery['from']['id'] ?? '');
        $data = (string) ($callbackQuery['data'] ?? '');
        $callbackId = $callbackQuery['id'] ?? null;

        if ($chatId === '') {
            return;
        }

        if (!$this->service->isOwner($botItem, $chatId, $type)) {
            $publisher->answerCallback($callbackId, trans('bot.channel_poster_not_owner'));
            return;
        }

        $publisher->answerCallback($callbackId);

        if ($data === 'cp:add') {
            $this->startAddWizard($publisher, $botItem, $type, $chatId);
            return;
        }

        if ($data === 'cp:addtag:new') {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_TAG_NAME);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_tag_name'));
            return;
        }

        if (str_starts_with($data, 'cp:addtag:')) {
            $index = (int) substr($data, 10);
            $state = $this->getState($botItem, $chatId, $type);
            $keys = $state?->getData('tag_keys', []) ?? [];
            $tag = is_array($keys) ? (string) ($keys[$index] ?? '') : '';
            if ($tag === '') {
                $groups = $this->service->listTagGroups($botItem->id);
                $tag = $groups[$index]['key'] ?? '';
            }
            if ($tag === '') {
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_tag_name'));
                $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_TAG_NAME);
                return;
            }
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_PLATFORM, ['tag' => $tag]);
            $this->sendPlatformKeyboard($publisher, $chatId, $tag);
            return;
        }

        if (str_starts_with($data, 'cp:plat:')) {
            $platform = substr($data, 8);
            $state = $this->getState($botItem, $chatId, $type);
            $tag = (string) ($state?->getData('tag') ?? '');
            $this->handlePlatformChoice($publisher, $botItem, $type, $chatId, $platform, $tag);
            return;
        }

        $state = $this->getState($botItem, $chatId, $type);
        if (!$state || $state->state !== self::STATE_AWAITING_DESTINATION) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_no_pending'));
            return;
        }

        $tagKey = null;
        if ($data === 'cp:to:bale' || $data === 'cp:to:all') {
            $tagKey = (string) ($state->getData('tag_keys')[0] ?? '');
            if ($this->service->listTagGroups($botItem->id) === []) {
                $tagKey = '';
            } elseif (count($this->service->listTagGroups($botItem->id)) === 1) {
                $tagKey = $this->service->listTagGroups($botItem->id)[0]['key'];
            }
        } elseif (str_starts_with($data, 'cp:tag:')) {
            $index = (int) substr($data, 7);
            $keys = $state->getData('tag_keys', []);
            $tagKey = is_array($keys) ? (string) ($keys[$index] ?? '') : null;
        }

        if ($tagKey === null) {
            return;
        }

        $this->publishPendingToTag($publisher, $botItem, $type, $chatId, $state, $tagKey);
    }

    private function handlePlatformChoice(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $platform,
        string $tag
    ): void {
        if ($platform === ChannelPosterDestination::PLATFORM_SOROUSH) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_soroush_soon'));
            return;
        }

        if ($platform === ChannelPosterDestination::PLATFORM_BALE) {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_BALE_FORWARD, ['tag' => $tag]);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_bale_forward'));
            return;
        }

        if ($platform === ChannelPosterDestination::PLATFORM_TELEGRAM) {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_TELEGRAM_TOKEN, ['tag' => $tag]);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_telegram_token'));
            return;
        }

        if ($platform === ChannelPosterDestination::PLATFORM_EITAA) {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_EITAA_TOKEN, ['tag' => $tag]);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_eitaa_token'));
        }
    }

    private function handleTelegramToken(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $token,
        array $stateData
    ): void {
        $token = trim($token);
        if (!TokenHelper::isToken($token, 'telegram')) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_invalid_token'));
            return;
        }

        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_TELEGRAM_CHAT_ID, [
            'tag' => $stateData['tag'] ?? '',
            'bot_token' => $token,
        ]);
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_telegram_chat_id'));
    }

    private function handleEitaaToken(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $token,
        array $stateData
    ): void {
        $token = preg_replace('/\s+/', '', $token) ?? '';
        if (strlen($token) < 16) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_invalid_token'));
            return;
        }

        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_EITAA_CHAT_ID, [
            'tag' => $stateData['tag'] ?? '',
            'bot_token' => $token,
        ]);
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_eitaa_chat_id'));
    }

    private function handleExternalChatId(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $channelChatId,
        array $stateData,
        string $platform
    ): void {
        $channelChatId = trim($channelChatId);
        if ($channelChatId === '' || str_starts_with($channelChatId, '/')) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_invalid_chat_id'));
            return;
        }

        $token = (string) ($stateData['bot_token'] ?? '');
        $tag = (string) ($stateData['tag'] ?? '');
        $ok = $publisher->sendTestMessage(
            $channelChatId,
            trans('bot.channel_poster_test_message'),
            $platform,
            $token
        );
        if (!$ok) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_not_admin'));
            return;
        }

        $this->service->saveDestination($botItem->id, $platform, $channelChatId, null, $tag !== '' ? $tag : null, $token);
        $this->clearState($botItem, $chatId, $type);
        $key = $platform === ChannelPosterDestination::PLATFORM_TELEGRAM
            ? 'bot.channel_poster_telegram_connected'
            : 'bot.channel_poster_eitaa_connected';
        $publisher->sendPrivateMessage($chatId, trans($key, ['tag' => $tag !== '' ? $tag : trans('bot.channel_poster_untagged')]));
    }

    private function saveUntaggedName(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $tag
    ): void {
        $tag = trim($tag);
        $this->service->assignTagToUntagged($botItem->id, $tag);
        $this->clearState($botItem, $chatId, $type);
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_tag_saved', ['tag' => $tag]));
    }

    private function offerOrPublishContent(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $media
    ): void {
        $groups = $this->service->listTagGroups($botItem->id);
        $pending = [
            'content_type' => $media['type'],
            'text' => $media['text'],
            'file_id' => $media['file_id'],
            'tag_keys' => array_map(fn (array $group) => $group['key'], $groups),
        ];

        if (count($groups) <= 1) {
            $tagKey = $groups[0]['key'] ?? '';
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $pending);
            $state = $this->getState($botItem, $chatId, $type);
            if ($state) {
                $this->publishPendingToTag($publisher, $botItem, $type, $chatId, $state, $tagKey);
            }
            return;
        }

        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $pending);
        $rows = [];
        foreach ($groups as $index => $group) {
            $rows[] = [[
                'text' => $group['label'],
                'callback_data' => 'cp:tag:'.$index,
            ]];
        }
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_which_tag'), $rows);
    }

    private function publishPendingToTag(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        BotUserState $state,
        string $tagKey
    ): void {
        $contentType = (string) $state->getData('content_type', 'text');
        $text = $state->getData('text');
        $fileId = $state->getData('file_id');

        $destinations = $this->service->resolveByTag($botItem->id, $tagKey);
        if ($destinations->isEmpty()) {
            $destinations = $this->service->resolveDestinations($botItem->id, 'all');
        }
        if ($destinations->isEmpty()) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_no_destination'));
            return;
        }

        $allOk = true;
        foreach ($destinations as $destination) {
            $ok = $publisher->publish(
                $destination->channel_chat_id,
                $contentType,
                is_string($text) ? $text : null,
                is_string($fileId) ? $fileId : null,
                $destination->platform,
                $destination->bot_token
            );
            if (!$ok) {
                $allOk = false;
            }
        }

        $label = $this->service->displayTagLabel(
            $tagKey !== '' ? $tagKey : ($destinations->first()?->tag),
            $destinations->first()?->channel_title
        );
        $this->clearState($botItem, $chatId, $type);
        $publisher->sendPrivateMessage(
            $chatId,
            $allOk
                ? trans('bot.channel_poster_published', ['tag' => $label])
                : trans('bot.channel_poster_publish_failed')
        );
    }

    private function sendPlatformKeyboard(ChannelPosterPublisher $publisher, string $chatId, string $tag): void
    {
        $rows = [
            [[
                'text' => trans('bot.channel_poster_btn_bale'),
                'callback_data' => 'cp:plat:bale',
            ]],
            [[
                'text' => trans('bot.channel_poster_btn_telegram'),
                'callback_data' => 'cp:plat:telegram',
            ]],
            [[
                'text' => trans('bot.channel_poster_btn_eitaa'),
                'callback_data' => 'cp:plat:eitaa',
            ]],
            [[
                'text' => trans('bot.channel_poster_btn_soroush'),
                'callback_data' => 'cp:plat:soroush',
            ]],
        ];
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_platform', ['tag' => $tag]), $rows);
    }

    private function isShortTagName(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || str_starts_with($text, '/')) {
            return false;
        }

        return mb_strlen($text) <= 64;
    }

    private function resolveBotUser(Bot $botItem, string $chatId, string $type): BotUsers
    {
        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botItem->id)
            ->first();

        if (!$botUser) {
            $botUser = BotUsers::create([
                'chat_id' => $chatId,
                'bot_id' => $botItem->id,
                'origin' => $type,
                'status' => 'active',
            ]);
        }

        return $botUser;
    }

    private function motherId(Bot $botItem): int
    {
        return (int) ($botItem->bot_mother_id ?? $botItem->id);
    }

    private function getState(Bot $botItem, string $chatId, string $type): ?BotUserState
    {
        $botUser = $this->resolveBotUser($botItem, $chatId, $type);

        return BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $this->motherId($botItem))
            ->active()
            ->latest('id')
            ->first();
    }

    private function setState(Bot $botItem, string $chatId, string $type, string $state, array $data = []): void
    {
        $botUser = $this->resolveBotUser($botItem, $chatId, $type);
        $motherId = $this->motherId($botItem);

        BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $motherId)
            ->delete();

        BotUserState::create([
            'bot_user_id' => $botUser->id,
            'bot_mother_id' => $motherId,
            'state' => $state,
            'data' => $data,
            'expires_at' => now()->addHours(6),
        ]);
    }

    private function clearState(Bot $botItem, string $chatId, string $type): void
    {
        $botUser = $this->resolveBotUser($botItem, $chatId, $type);
        BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $this->motherId($botItem))
            ->delete();
    }
}
