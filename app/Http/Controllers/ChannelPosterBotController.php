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
use App\Models\ChannelPosterQueue;
use App\Models\ContentSyncAdmin;
use App\Models\ContentSyncAdminPrefs;
use App\Models\ContentSyncContent;
use App\Services\ChannelPosterMediaStore;
use App\Services\ChannelPosterQueuePublishService;
use App\Services\ContentSync\ContentSyncService;
use App\Services\ContentSync\PostUrlParser;
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
    public const STATE_AWAITING_SIGN_TAG = 'cp_awaiting_sign_tag';
    public const STATE_AWAITING_SIGN_CHANNEL = 'cp_awaiting_sign_channel';
    public const STATE_AWAITING_SIGN_LINK = 'cp_awaiting_sign_link';

    // === Content Sync (همگام‌سازی چندکاناله) ===
    public const STATE_SYNC_AWAITING_TEXT = 'cp_sync_awaiting_text';
    public const STATE_SYNC_AWAITING_TAG = 'cp_sync_awaiting_tag';
    public const STATE_SYNC_AWAITING_SOURCE = 'cp_sync_awaiting_source';

    public function __construct(
        private ChannelPosterBotService $service,
        private ChannelPosterPublisherFactory $publisherFactory,
        private ChannelPosterQueuePublishService $queuePublishService,
        private ContentSyncService $contentSync,
        private ChannelPosterMediaStore $mediaStore
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
        if (!$this->isOwnerOrAdmin($botItem, $chatId, $type)) {
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

        if (is_string($text) && ($text === '/sign' || str_starts_with($text, '/sign '))) {
            $this->startSignWizard($publisher, $botItem, $type, $chatId);
            return;
        }

        if (is_string($text) && preg_match('#^/queue\b#', $text)) {
            $this->showQueueList($publisher, $botItem, $type, $chatId);
            return;
        }

        // === دستورات همگام‌سازی (Content Sync) ===
        if (is_string($text) && preg_match('#^/(new|reset)\b#', $text)) {
            $this->clearState($botItem, $chatId, $type);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_ready') . "\n" . $this->contentSync->helpFooter());
            return;
        }

        if (is_string($text) && preg_match('#^/(mode|queue_mode|now_mode)\b#', $text) && $this->isAdmin($botItem, $chatId, $type)) {
            $this->handleSyncModeCommand($publisher, $botItem, $type, $chatId);
            return;
        }

        if (is_string($text) && preg_match('#^/(status|report)\b#', $text) && $this->isAdmin($botItem, $chatId, $type)) {
            $this->handleSyncStatusCommand($publisher, $botItem, $type, $chatId, $text);
            return;
        }

        if (is_string($text) && preg_match('#^/retry\b#', $text) && $this->isAdmin($botItem, $chatId, $type)) {
            $this->handleSyncRetryCommand($publisher, $botItem, $type, $chatId);
            return;
        }

        if (is_string($text) && preg_match('#^/last\b#', $text) && $this->isAdmin($botItem, $chatId, $type)) {
            $this->handleSyncLastCommand($publisher, $botItem, $type, $chatId);
            return;
        }

        if (is_string($text) && preg_match('#^/sync\b#', $text) && $this->isAdmin($botItem, $chatId, $type)) {
            $this->handleSyncCommand($publisher, $botItem, $type, $chatId, $text);
            return;
        }

        if (is_string($text) && preg_match('#^/tags\b#', $text) && $this->isAdmin($botItem, $chatId, $type)) {
            $this->handleSyncTagsCommand($publisher, $botItem, $type, $chatId);
            return;
        }

        if (is_string($text) && preg_match('#^/admin\b#', $text) && $this->isAdmin($botItem, $chatId, $type)) {
            $this->handleSyncAdminCommand($publisher, $botItem, $type, $chatId, $text);
            return;
        }

        if (is_string($text) && preg_match('#^/channels\b#', $text) && $this->isAdmin($botItem, $chatId, $type)) {
            $this->handleChannelsCommand($publisher, $botItem, $type, $chatId);
            return;
        }

        if (is_string($text) && preg_match('#^/settings\b#', $text) && $this->isAdmin($botItem, $chatId, $type)) {
            $this->handleSettingsCommand($publisher, $botItem, $type, $chatId);
            return;
        }

        if (is_string($text) && preg_match('#^/help\b#', $text)) {
            $this->handleHelpCommand($publisher, $botItem, $type, $chatId);
            return;
        }

        $state = $this->getState($botItem, $chatId, $type);

        // === حالت همگام‌سازی: انتظار متن بعد از لینک ===
        if ($state && $state->state === self::STATE_SYNC_AWAITING_TEXT && is_string($text) && !str_starts_with($text, '/')) {
            // اگر به‌جای متن، لینک پیام جدیدی آمده باشد، آن را «متن» قلمداد نکن؛
            // لینک جدید یک محتوای جدید می‌سازد.
            $pendingLink = $this->isAdmin($botItem, $chatId, $type)
                ? PostUrlParser::extractFromText($text)
                : null;
            if ($pendingLink) {
                $this->handleSyncLink($publisher, $botItem, $type, $chatId, $text, $pendingLink);
                return;
            }
            $this->handleSyncText($publisher, $botItem, $type, $chatId, $text, $state);
            return;
        }

        // === حالت همگام‌سازی: انتخاب تگ از دکمه‌ها ===
        if ($state && $state->state === self::STATE_SYNC_AWAITING_TAG && is_string($text)
            && preg_match('#^/sync:tag:\d+$#', trim($text))
        ) {
            $this->handleSyncTagChoice($publisher, $botItem, $type, $chatId, $state, (int) substr(trim($text), 10));
            return;
        }

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
            if ($state->state === self::STATE_AWAITING_SIGN_LINK) {
                $this->handleSignLink($publisher, $botItem, $type, $chatId, $text, $state->data ?? []);
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

        // === ورودی سریع fast mode: ادمین فقط یک لینک پیام می‌فرستد ===
        $parsedLink = $this->isAdmin($botItem, $chatId, $type)
            ? PostUrlParser::extractFromText($text)
            : null;
        if ($parsedLink) {
            $this->handleSyncLink($publisher, $botItem, $type, $chatId, (string) $text, $parsedLink);
            return;
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
        $msg = trans('bot.channel_poster_welcome_ready', ['tags' => implode('، ', $labels)]);
        $rows = null;

        $pendingCount = ChannelPosterQueue::where('bot_id', $botItem->id)
            ->where('status', ChannelPosterQueue::STATUS_PENDING)
            ->count();
        if ($pendingCount > 0) {
            $rows = [[
                'text' => trans('bot.channel_poster_btn_queue_list', ['count' => $pendingCount]),
                'callback_data' => 'cp:q:list',
            ]];
        }

        $publisher->sendPrivateMessage($chatId, $msg, $rows);
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

        // دکمه‌های تنظیمات و تگ‌های همگام‌سازی برای ادمین‌های غیرمالک هم باز است
        // (ناوبری منوی /settings + دکمه‌های انتخاب مقدار؛ cps:default:* فقط مالک)
        $adminSyncCallback = (str_starts_with($data, 'cps:dedup:')
            || str_starts_with($data, 'cps:fast:')
            || str_starts_with($data, 'cps:mode:')
            || str_starts_with($data, 'cps:attr:')
            || str_starts_with($data, 'cps:src:')
            || str_starts_with($data, 'cps:tag:')
            || str_starts_with($data, 'cps:menu')
            || str_starts_with($data, 'cps:sub:'))
            && $this->isAdmin($botItem, $chatId, $type);

        if (!$this->service->isOwner($botItem, $chatId, $type) && !$adminSyncCallback) {
            $publisher->answerCallback($callbackId, trans('bot.channel_poster_not_owner'));
            return;
        }

        // callbackهای همگام‌سازی پاسخ خود را مدیریت می‌کنند (ناوبری منوی /settings یا toast ثبت)
        if (str_starts_with($data, 'cps:')) {
            $this->handleContentSyncCallback($publisher, $callbackId, $botItem, $type, $chatId, $data);
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

        // Handle signature callbacks
        if ($data === 'cp:sig:yes' || $data === 'cp:sig:no') {
            $state = $this->getState($botItem, $chatId, $type);
            if (!$state || $state->state !== self::STATE_AWAITING_DESTINATION) {
                return;
            }
            $stateData = $state->data ?? [];
            $stateData['signature_enabled'] = ($data === 'cp:sig:yes');
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
            return;
        }

        // Handle now/queue callbacks
        if ($data === 'cp:now' || $data === 'cp:queue') {
            $state = $this->getState($botItem, $chatId, $type);
            if (!$state || $state->state !== self::STATE_AWAITING_DESTINATION) {
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_no_pending'));
                return;
            }
            $stateData = $state->data ?? [];
            $stateData['enqueue'] = ($data === 'cp:queue');
            $tagKey = (string) ($stateData['selected_tag'] ?? '');
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
            $state = $this->getState($botItem, $chatId, $type);
            if ($state) {
                $this->publishPendingToTag($publisher, $botItem, $type, $chatId, $state, $tagKey);
            }
            return;
        }

        // Handle publish-queue control callbacks
        if ($data === 'cp:q:list') {
            $this->showQueueList($publisher, $botItem, $type, $chatId);
            return;
        }

        if (str_starts_with($data, 'cp:q:item:')) {
            [$itemId, $action] = array_pad(explode(':', substr($data, 10)), 2, '');
            $this->handleQueueItemCallback($publisher, $botItem, $type, $chatId, (int) $itemId, $action);
            return;
        }

        // Handle sign wizard callbacks
        if (str_starts_with($data, 'cp:signtag:')) {
            $this->handleSignTagCallback($publisher, $botItem, $type, $chatId, $data);
            return;
        }
        if (str_starts_with($data, 'cp:signchn:')) {
            $this->handleSignChannelCallback($publisher, $botItem, $type, $chatId, $data);
            return;
        }

        // Handle content-sync callbacks — delegated to handleContentSyncCallback
        // (ناوبری منوی /settings + انتخاب مقدار + انتخاب تگ)

        // حذف کانال از لیست مقاصد (قطع ارتباط کانال با تگ)
        if (str_starts_with($data, 'cp:unlink:')) {
            $this->handleUnlinkCallback($publisher, $botItem, $type, $chatId, (int) substr($data, 10));
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
            if ($tagKey !== null) {
                $stateData = $state->data ?? [];
                $stateData['selected_tag'] = $tagKey;
                $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
                $this->askSignatureAndQueue($publisher, $botItem, $type, $chatId, $tagKey);
                return;
            }
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
        $input = trim($channelChatId);
        if ($input === '' || str_starts_with($input, '/')) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_invalid_chat_id'));
            return;
        }

        // §6: اگر ورودی خود یک لینک کانال/پیام باشد (مثل t.me/saberblog/42 یا
        // ble.ir/saberblog/123/4)، username و chat id هر دو را نگه می‌داریم —
        // username قابل‌اعتماد (channel_username) فقط از لینک واقعی پارس می‌شود.
        $parsed = PostUrlParser::parse($input);
        $channelChatId = $parsed !== null ? (string) $parsed['channel_identifier'] : $input;
        $channelUsername = $parsed !== null ? trim((string) ($parsed['channel_username'] ?? '')) : '';
        $channelTitle = $channelUsername !== '' ? $channelUsername : $channelChatId;
        $channelLink = $parsed !== null ? (string) $parsed['canonical_url'] : null;

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

        $this->service->saveDestination($botItem->id, $platform, $channelChatId, $channelTitle, $tag !== '' ? $tag : null, $token);
        $this->clearState($botItem, $chatId, $type);
        if ($channelLink !== null && preg_match('#^https?://#i', (string) $input) === 1) {
            $destination = ChannelPosterDestination::where('bot_id', $botItem->id)
                ->where('platform', $platform)
                ->where('channel_chat_id', $channelChatId)
                ->first();
            $update = ['channel_link' => $channelLink];
            if ($channelUsername !== '') {
                $update['channel_username'] = $channelUsername;
            }
            if ($destination && $update !== []) {
                $destination->forceFill($update)->save();
            }
        }
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

        // دانلود فایل رسانه به دیسک — file_id فقط برای پلتفرم مبدأ اعتبار دارد؛
        // برای انتشار چندکاناله (بله/تلگرام/ایتا) فایل اصلی لازم است.
        // در صورت خطای دانلود، با همان file_id قدیمی ادامه می‌دهیم (degrade gracefully).
        $mediaPath = null;
        if (!empty($media['file_id'])) {
            $originToken = $type === 'bale'
                ? (string) $botItem->bale_bot_token
                : (string) $botItem->telegram_bot_token;
            if ($originToken !== '') {
                $mediaPath = $this->mediaStore->fetchAndStore(
                    $originToken,
                    (string) $media['file_id'],
                    $type,
                    (string) $media['type']
                );
            }
        }

        $pending = [
            'content_type' => $media['type'],
            'text' => $media['text'],
            'file_id' => $media['file_id'],
            'media_path' => $mediaPath,
            'tag_keys' => array_map(fn (array $group) => $group['key'], $groups),
        ];

        if (count($groups) <= 1) {
            $tagKey = $groups[0]['key'] ?? '';
            $pending['selected_tag'] = $tagKey;
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $pending);
            $this->askSignatureAndQueue($publisher, $botItem, $type, $chatId, $tagKey);
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

    private function askSignatureAndQueue(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $tagKey
    ): void {
        $hasLinks = $this->service->resolveByTag($botItem->id, $tagKey)
            ->filter(fn ($d) => !empty($d->channel_link))
            ->isNotEmpty();

        $pendingCount = $this->service->countPending($botItem->id, $tagKey);
        $nextSlot = $this->service->getNextSlot($botItem->id, $tagKey);
        $nextTimeLabel = $nextSlot->setTimezone('Asia/Tehran')->format('H:i');

        $rows = [];

        // Signature option (only if any channel has a link)
        if ($hasLinks) {
            $rows[] = [
                ['text' => trans('bot.channel_poster_btn_sig_yes'), 'callback_data' => 'cp:sig:yes'],
                ['text' => trans('bot.channel_poster_btn_sig_no'), 'callback_data' => 'cp:sig:no'],
            ];
        }

        // Queue option
        $rows[] = [
            ['text' => trans('bot.channel_poster_btn_now'), 'callback_data' => 'cp:now'],
            ['text' => trans('bot.channel_poster_btn_queue', ['time' => $nextTimeLabel]), 'callback_data' => 'cp:queue'],
        ];

        $msg = trans('bot.channel_poster_ask_publish_options');
        if ($pendingCount > 0) {
            $msg .= "\n" . trans('bot.channel_poster_queue_count', ['count' => $pendingCount]);
        }

        $publisher->sendPrivateMessage($chatId, $msg, $rows);
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
        $mediaPath = $state->getData('media_path');
        $signatureEnabled = (bool) $state->getData('signature_enabled', false);
        $enqueue = (bool) $state->getData('enqueue', false);

        $destinations = $this->service->resolveByTag($botItem->id, $tagKey);
        if ($destinations->isEmpty()) {
            $destinations = $this->service->resolveDestinations($botItem->id, 'all');
        }
        if ($destinations->isEmpty()) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_no_destination'));
            return;
        }

        // Append signature if enabled
        $finalText = is_string($text) ? $text : null;
        if ($signatureEnabled && $finalText !== null) {
            $sig = $this->service->buildSignature($botItem->id, $tagKey);
            if ($sig !== '') {
                $finalText .= $sig;
            }
        }

        // Enqueue instead of immediate publish
        if ($enqueue) {
            $nextSlot = $this->service->getNextSlot($botItem->id, $tagKey);
            $this->service->enqueue(
                $botItem->id,
                $tagKey,
                $contentType,
                $finalText,
                is_string($fileId) ? $fileId : null,
                $signatureEnabled,
                $nextSlot,
                $chatId,
                $type,
                $state?->getData('content_id') !== null ? (int) $state->getData('content_id') : null,
                is_string($mediaPath) && $mediaPath !== '' ? $mediaPath : null
            );
            $this->clearState($botItem, $chatId, $type);
            $publisher->sendPrivateMessage(
                $chatId,
                trans('bot.channel_poster_queued', [
                    'time' => $nextSlot->setTimezone('Asia/Tehran')->format('H:i'),
                    'date' => $nextSlot->setTimezone('Asia/Tehran')->format('Y-m-d'),
                ])
            );
            return;
        }

        // Immediate publish with logging and report
        $results = [];
        $allOk = true;
        $localPath = is_string($mediaPath) && $mediaPath !== '' ? $mediaPath : null;
        foreach ($destinations as $destination) {
            $result = $publisher->publish(
                $destination->channel_chat_id,
                $contentType,
                $finalText,
                is_string($fileId) ? $fileId : null,
                $destination->platform,
                $destination->bot_token,
                $localPath
            );
            $ok = $result['success'] ?? false;
            $messageId = $result['message_id'] ?? null;

            $this->service->logPublish(
                $botItem->id,
                $destination->id,
                $destination->platform,
                $ok,
                $messageId,
                isset($result['error']) ? $result['error'] : null,
                null,
                null,
                null,
                $state?->getData('content_id') !== null ? (int) $state->getData('content_id') : null
            );

            $results[] = [
                'destination_id' => $destination->id,
                'platform' => $destination->platform,
                'success' => $ok,
                'error' => $result['error'] ?? null,
            ];

            if (!$ok) {
                $allOk = false;
            }
        }

        $this->clearState($botItem, $chatId, $type);

        // انتشار فوری = بدون ردیف صف؛ پس فایل رسانه پس از تکمیل پاک می‌شود
        $this->mediaStore->delete($localPath);
        $report = $this->service->buildPublishReport($results, $destinations);
        $publisher->sendPrivateMessage($chatId, $report);
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

    private function startSignWizard(ChannelPosterPublisher $publisher, Bot $botItem, string $type, string $chatId): void
    {
        $groups = $this->service->listTagGroups($botItem->id);
        if ($groups === []) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_sign_no_tag'));
            return;
        }

        if (count($groups) === 1) {
            $this->showSignChannels($publisher, $botItem, $type, $chatId, $groups[0]['key']);
            return;
        }

        $rows = [];
        foreach ($groups as $index => $group) {
            $rows[] = [['text' => $group['label'], 'callback_data' => 'cp:signtag:' . $index]];
        }
        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_SIGN_TAG, [
            'tag_keys' => array_map(fn (array $g) => $g['key'], $groups),
        ]);
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_sign_ask_tag'), $rows);
    }

    private function handleSignTagCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $data
    ): void {
        $index = (int) substr($data, 11);
        $state = $this->getState($botItem, $chatId, $type);
        $keys = $state?->getData('tag_keys', []) ?? [];
        $tag = is_array($keys) ? (string) ($keys[$index] ?? '') : '';

        if ($tag === '') {
            $groups = $this->service->listTagGroups($botItem->id);
            $tag = $groups[$index]['key'] ?? '';
        }

        $this->showSignChannels($publisher, $botItem, $type, $chatId, $tag);
    }

    private function showSignChannels(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $tag
    ): void {
        $destinations = $this->service->resolveByTag($botItem->id, $tag);
        if ($destinations->isEmpty()) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_sign_no_channels'));
            return;
        }

        $platformLabels = [
            ChannelPosterDestination::PLATFORM_BALE => trans('bot.channel_poster_sig_bale'),
            ChannelPosterDestination::PLATFORM_TELEGRAM => trans('bot.channel_poster_sig_telegram'),
            ChannelPosterDestination::PLATFORM_EITAA => trans('bot.channel_poster_sig_eitaa'),
            ChannelPosterDestination::PLATFORM_SOROUSH => trans('bot.channel_poster_sig_soroush'),
        ];

        $rows = [];
        $destIds = [];
        foreach ($destinations as $dest) {
            $label = $platformLabels[$dest->platform] ?? $dest->platform;
            $title = $dest->channel_title ?? $dest->channel_chat_id;
            $hasLink = !empty($dest->channel_link) ? '✅' : '❌';
            $rows[] = [['text' => "{$label} — {$title} {$hasLink}", 'callback_data' => 'cp:signchn:' . $dest->id]];
            $destIds[] = $dest->id;
        }

        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_SIGN_CHANNEL, [
            'tag' => $tag,
            'dest_ids' => $destIds,
        ]);
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_sign_pick_channel'), $rows);
    }

    private function handleSignChannelCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $data
    ): void {
        $destId = (int) substr($data, 11);
        $dest = ChannelPosterDestination::find($destId);
        if (!$dest || $dest->bot_id !== $botItem->id) {
            return;
        }

        $currentLink = $dest->channel_link ?? '';
        $msg = $currentLink !== ''
            ? trans('bot.channel_poster_sign_current_link', ['link' => $currentLink])
            : trans('bot.channel_poster_sign_no_link');

        $state = $this->getState($botItem, $chatId, $type);
        $tag = $state?->getData('tag') ?? '';
        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_SIGN_LINK, [
            'destination_id' => $destId,
            'tag' => $tag,
        ]);
        $publisher->sendPrivateMessage($chatId, $msg);
    }

    private function handleSignLink(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $link,
        array $stateData
    ): void {
        $link = trim($link);
        if ($link === '' || (!str_starts_with($link, 'http://') && !str_starts_with($link, 'https://'))) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_sign_invalid_link'));
            return;
        }

        $destId = (int) ($stateData['destination_id'] ?? 0);
        if ($destId <= 0) {
            return;
        }

        $this->service->updateChannelLink($destId, $link);
        $this->clearState($botItem, $chatId, $type);
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_sign_link_saved', ['link' => $link]));
    }

    /**
     * نمایش لیست آیتم‌های pending صف ارسال با دکمه‌های «ارسال الان» و «لغو»
     * (دستور /queue یا callback cp:q:list)
     */
    private function showQueueList(ChannelPosterPublisher $publisher, Bot $botItem, string $type, string $chatId): void
    {
        $items = ChannelPosterQueue::where('bot_id', $botItem->id)
            ->where('status', ChannelPosterQueue::STATUS_PENDING)
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get();

        if ($items->isEmpty()) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_queue_empty'));
            return;
        }

        $tz = 'Asia/Tehran';
        $now = now();
        $parts = [];
        $rows = [];
        foreach ($items as $item) {
            $when = $item->scheduled_at->copy()->setTimezone($tz)->format('Y-m-d H:i');
            $overdue = $item->scheduled_at->lte($now);
            $tag = ($item->tag !== null && $item->tag !== '') ? "[{$item->tag}] " : '';
            $preview = $item->text !== null
                ? mb_substr($item->text, 0, 40)
                : trans('bot.channel_poster_queue_media');
            $marker = $overdue ? '⚠️ ' : '';

            $parts[] = "{$marker}#{$item->id} {$tag}— {$when}\n{$preview}";
            $rows[] = [
                ['text' => trans('bot.channel_poster_btn_send_now'), 'callback_data' => "cp:q:item:{$item->id}:send"],
                ['text' => trans('bot.channel_poster_btn_cancel_item'), 'callback_data' => "cp:q:item:{$item->id}:cancel"],
            ];
        }

        $msg = trans('bot.channel_poster_queue_list_header', ['count' => $items->count()]) . "\n\n" . implode("\n\n", $parts);
        $publisher->sendPrivateMessage($chatId, $msg, $rows);
    }

    /**
     * اقدام روی یک آیتم صف: ارسال فوری یا لغو (فقط owner — guard در handleCallbackQuery)
     */
    private function handleQueueItemCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        int $itemId,
        string $action
    ): void {
        $item = ChannelPosterQueue::where('id', $itemId)
            ->where('bot_id', $botItem->id)
            ->first();

        if (!$item || $item->status !== ChannelPosterQueue::STATUS_PENDING) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_queue_item_gone'));
            return;
        }

        if ($action === 'cancel') {
            $item->update(['status' => ChannelPosterQueue::STATUS_CANCELLED]);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_queue_item_cancelled', ['id' => $item->id]));
            return;
        }

        if ($action === 'send') {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_queue_item_sending', ['id' => $item->id]));

            $result = $this->queuePublishService->publishItem($item);
            $msg = match ($result['status']) {
                'published' => trans('bot.channel_poster_queue_item_sent', ['id' => $item->id]),
                'failed' => trans('bot.channel_poster_queue_item_failed', ['id' => $item->id, 'error' => $result['error'] ?? 'unknown']),
                default => trans('bot.channel_poster_queue_item_gone'),
            };
            $publisher->sendPrivateMessage($chatId, $msg);
            return;
        }

        $this->showQueueList($publisher, $botItem, $type, $chatId);
    }

    /* ==========================================================
     * Content Sync — همگام‌سازی چندکاناله
     * ========================================================== */

    private function isOwnerOrAdmin(Bot $botItem, string $chatId, string $type): bool
    {
        return $this->service->isOwner($botItem, $chatId, $type) || $this->isAdmin($botItem, $chatId, $type);
    }

    private function isAdmin(Bot $botItem, string $chatId, string $type): bool
    {
        if ($this->service->isOwner($botItem, $chatId, $type)) {
            return true;
        }

        return ContentSyncAdmin::isRegistered($botItem->id, $chatId, $type);
    }

    /**
     * fast mode — مرحله ۱: ادمین لینک پیام مبدأ را فرستاده.
     */
    private function handleSyncLink(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $rawUrl,
        array $parsed
    ): void {
        try {
            $reg = $this->contentSync->registerSource($botItem, $rawUrl, $chatId, $type);
        } catch (\InvalidArgumentException $e) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_invalid_url'));
            return;
        }

        $content = $reg['content'];

        if ($reg['duplicate']) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_duplicate', [
                'id' => $content->id,
                'url' => $content->source_url,
            ]));
            return;
        }

        if (!$this->service->hasAnyDestination($botItem->id)) {
            $this->setState($botItem, $chatId, $type, self::STATE_SYNC_AWAITING_TEXT, [
                'content_id' => $content->id,
                'source_destination_id' => $reg['source_destination_id'],
            ]);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_link_registered_ask_forward', [
                'id' => $content->id,
            ]));
            return;
        }

        // §5: دکمه‌های کوتاه تنظیمات فقط یک‌بار — اولین لینک — نمایش داده می‌شوند.
        if (!ContentSyncAdminPrefs::isSettingsAsked($botItem->id, $chatId, $type)) {
            $this->sendOneTimeSettingsKeyboard($publisher, $botItem, $type, $chatId, $content);
            return;
        }

        // §1: کانال مبدأ ناشناخته — یک‌بار سؤال می‌شود و نگاشت ذخیره می‌شود.
        // تا آن‌که نگاشت نگردد، هیچ انتشار «مطمئن» انجام نمی‌شود.
        if ($reg['source_destination_id'] === null) {
            $this->setState($botItem, $chatId, $type, self::STATE_SYNC_AWAITING_SOURCE, [
                'content_id' => $content->id,
                'source_destination_id' => null,
                'parsed' => $reg['parsed'],
            ]);
            $this->sendSourceChannelKeyboard($publisher, $botItem, $type, $chatId, $content, $reg['parsed']);
            return;
        }

        $this->setState($botItem, $chatId, $type, self::STATE_SYNC_AWAITING_TEXT, [
            'content_id' => $content->id,
            'source_destination_id' => $reg['source_destination_id'],
        ]);

        $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_link_registered', ['id' => $content->id]));
    }

    /**
     * §5: کیبورد یک‌بارِ تنظیمات (اولین لینک) — دکمه‌های کوتاه، حداکثر ۲ در هر ردیف.
     */
    private function sendOneTimeSettingsKeyboard(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        ContentSyncContent $content
    ): void {
        $dedup = ContentSyncAdminPrefs::getDedupMode($botItem->id, $chatId, $type);
        $mode = ContentSyncAdminPrefs::getMode($botItem->id, $chatId, $type);
        $fast = ContentSyncAdminPrefs::getFastMode($botItem->id, $chatId, $type);
        $attr = ContentSyncAdminPrefs::getAttributionMode($botItem->id, $chatId, $type);

        $dedupShort = $dedup === ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM ? '🌐' : '🎯';
        $modeShort = $mode === ContentSyncAdminPrefs::MODE_QUEUE ? '⏳' : '⚡';
        $fastShort = $fast ? '⚡✅' : '⚡';
        $attrShort = $attr === ContentSyncAdminPrefs::ATTR_LINK_ONLY ? '🔗' : ($attr === ContentSyncAdminPrefs::ATTR_NONE ? '🚫' : '📌');

        $rows = [
            [
                ['text' => $dedupShort . ' ' . trans('bot.channel_sync_ot_dedup'), 'callback_data' => 'cps:sub:dedup'],
                ['text' => $modeShort . ' ' . trans('bot.channel_sync_ot_time'), 'callback_data' => 'cps:sub:time'],
            ],
            [
                ['text' => $fastShort . ' ' . trans('bot.channel_sync_ot_fast'), 'callback_data' => 'cps:sub:fast'],
                ['text' => $attrShort . ' ' . trans('bot.channel_sync_ot_attr'), 'callback_data' => 'cps:sub:attr'],
            ],
        ];

        ContentSyncAdminPrefs::markSettingsAsked($botItem->id, $chatId, $type);
        $this->setState($botItem, $chatId, $type, self::STATE_SYNC_AWAITING_TEXT, [
            'content_id' => $content->id,
            'source_destination_id' => null,
        ]);

        $publisher->sendPrivateMessage(
            $chatId,
            trans('bot.channel_sync_link_registered', ['id' => $content->id]) . "\n" . trans('bot.channel_sync_ot_intro'),
            $rows
        );
    }

    /**
     * §1: سؤال یک‌باره‌ی کانال مبدأ (وقتی شناسه‌ی لینک با هیچ مقصد ثبت‌شده‌ای
     * جور در نمی‌آید). پاسخ فقط نگاشت شناسه را ذخیره می‌کند — انتشار نامطمئن نیست.
     */
    private function sendSourceChannelKeyboard(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        ContentSyncContent $content,
        array $parsed
    ): void {
        $platform = (string) ($parsed['platform'] ?? '');
        $rows = [];
        $destinations = ChannelPosterDestination::where('bot_id', $botItem->id)
            ->where('is_active', true)
            ->where('platform', $platform)
            ->get();
        $index = 0;
        foreach ($destinations as $destination) {
            $title = trim((string) $destination->channel_title);
            $info = $title !== '' ? " — {$title}" : '';
            $tag = trim((string) $destination->tag);
            $tagInfo = $tag !== '' ? " ({$tag})" : '';
            $rows[] = [['text' => "{$destination->channel_chat_id}{$info}{$tagInfo}", 'callback_data' => 'cps:src:' . $index]];
            $index++;
        }
        $rows[] = [['text' => trans('bot.channel_sync_src_other'), 'callback_data' => 'cps:src:none']];

        $publisher->sendPrivateMessage(
            $chatId,
            trans('bot.channel_sync_src_ask', [
                'id' => $content->id,
                'platform' => $this->contentSync->platformLabels()[$platform] ?? $platform,
            ]),
            $rows
        );
    }

    /**
     * §1: پاسخ سؤال کانال مبدأ — نگاشت ذخیره می‌شود و جریان fast mode ادامه می‌یابد.
     */
    private function handleSyncSourceCallback(
        ChannelPosterPublisher $publisher,
        ?string $callbackId,
        Bot $botItem,
        string $type,
        string $chatId,
        string $data
    ): void {
        $state = $this->getState($botItem, $chatId, $type);
        if (!$state || $state->state !== self::STATE_SYNC_AWAITING_SOURCE) {
            return;
        }

        $content = ContentSyncContent::find((int) $state->getData('content_id', 0));
        if (!$content || (int) $content->bot_id !== (int) $botItem->id) {
            $this->clearState($botItem, $chatId, $type);
            return;
        }

        $parsed = $state->getData('parsed');
        if (!is_array($parsed)) {
            $this->clearState($botItem, $chatId, $type);
            return;
        }

        $sourceDestinationId = null;
        if (str_starts_with($data, 'cps:src:') && $data !== 'cps:src:none') {
            $index = (int) substr($data, 8);
            $destinations = ChannelPosterDestination::where('bot_id', $botItem->id)
                ->where('is_active', true)
                ->where('platform', (string) ($parsed['platform'] ?? ''))
                ->get();
            $sourceDestinationId = $destinations->values()->get($index)?->id;
        }

        if ($sourceDestinationId !== null) {
            $this->contentSync->recordSourceAssociation($sourceDestinationId, $parsed);
        }

        $publisher->answerCallback($callbackId, trans('bot.channel_sync_src_saved'));

        $this->setState($botItem, $chatId, $type, self::STATE_SYNC_AWAITING_TEXT, [
            'content_id' => $content->id,
            'source_destination_id' => $sourceDestinationId,
        ]);

        $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_link_registered', ['id' => $content->id]));
    }

    /**
     * fast mode — مرحله ۲: متن مطلب رسید؛ تگ را تعیین کن و منتشر کن / صف بگذار.
     */
    private function handleSyncText(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $text,
        BotUserState $state
    ): void {
        $content = ContentSyncContent::find((int) $state->getData('content_id', 0));
        if (!$content || (int) $content->bot_id !== (int) $botItem->id) {
            $this->clearState($botItem, $chatId, $type);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_content_expired'));
            return;
        }

        $this->contentSync->attachText($content, $text);
        $sourceDestinationId = $state->getData('source_destination_id');
        $sourceDestinationId = $sourceDestinationId === null ? null : (string) $sourceDestinationId;

        $useFast = ContentSyncAdminPrefs::getFastMode($botItem->id, $chatId, $type);
        $tagResult = $this->contentSync->resolveTag(
            $botItem->id,
            $sourceDestinationId,
            null,
            $chatId,
            $type,
            $useFast
        );

        if ($tagResult['status'] === 'ask') {
            if ($tagResult['groups'] === []) {
                $this->clearState($botItem, $chatId, $type);
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_no_targets'));
                return;
            }
            $this->setState($botItem, $chatId, $type, self::STATE_SYNC_AWAITING_TAG, [
                'content_id' => $content->id,
                'source_destination_id' => $sourceDestinationId,
            ]);
            $this->sendSyncTagKeyboard($publisher, $chatId, $tagResult['groups']);
            return;
        }

        $this->syncPublish($publisher, $botItem, $type, $chatId, $content, $tagResult['tag']);
    }

    /**
     * fast mode — انتخاب تگ از دکمه‌ها (فقط وقتی ابهام بوده).
     */
    private function handleSyncTagCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        int $index
    ): void {
        $state = $this->getState($botItem, $chatId, $type);
        if (!$state || $state->state !== self::STATE_SYNC_AWAITING_TAG) {
            return;
        }

        $content = ContentSyncContent::find((int) $state->getData('content_id', 0));
        $groups = $this->contentSync->resolveTag($botItem->id, null, null, null, $type)['groups'];
        $tag = $groups[$index]['key'] ?? null;

        if (!$content || $tag === null) {
            $this->clearState($botItem, $chatId, $type);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_content_expired'));
            return;
        }

        $this->syncPublish($publisher, $botItem, $type, $chatId, $content, $tag);
    }

    private function handleSyncTagChoice(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        BotUserState $state,
        int $index
    ): void {
        $this->handleSyncTagCallback($publisher, $botItem, $type, $chatId, $index);
    }

    /**
     * fast mode — مرحله ۳: انتشار (فوری یا صف) + تأیید کوتاه + آماده لینک بعدی.
     */
    private function syncPublish(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        ContentSyncContent $content,
        string $tag
    ): void {
        $mode = ContentSyncAdminPrefs::getMode($botItem->id, $chatId, $type);

        // §2: تگ نهایی روی خود محتوا ثبت می‌شود — گزارش و صف فقط همین تگ را می‌بینند.
        $content->forceFill(['tag' => $tag])->save();

        if ($mode === ContentSyncAdminPrefs::MODE_QUEUE) {
            $enq = $this->contentSync->enqueueForTag($content, $tag, $chatId, $type);
            $this->clearState($botItem, $chatId, $type);

            if ($enq['version_count'] === 0) {
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_no_targets'));
                return;
            }

            $time = $enq['queue']->scheduled_at->setTimezone('Asia/Tehran')->format('H:i');
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_queued_ready', [
                'id' => $content->id,
                'time' => $time,
            ]) . "\n" . $this->contentSync->helpFooter());
            return;
        }

        // انتشار فوری
        $outcome = $this->contentSync->publishImmediate($content, $tag, $publisher, $chatId, $type);

        if ($outcome['destinations']->isEmpty()) {
            $this->clearState($botItem, $chatId, $type);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_no_targets'));
            return;
        }

        $this->clearState($botItem, $chatId, $type);
        $publisher->sendPrivateMessage($chatId, $this->contentSync->buildFastConfirmation($content));
    }

    private function handleSettingsCommand(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId
    ): void {
        $this->sendSettingsScreen($publisher, $botItem, $type, $chatId);
    }

    /**
     * /help — راهنمای کامل دسته‌بندی‌شده؛ دستورات فقط‌مالک فقط به مالک.
     */
    private function handleHelpCommand(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId
    ): void {
        $lines = [
            trans('bot.channel_sync_help_title'),
            '',
            trans('bot.channel_sync_help_flow'),
            '',
            trans('bot.channel_sync_help_commands'),
        ];

        if ($this->service->isOwner($botItem, $chatId, $type)) {
            $lines[] = '';
            $lines[] = trans('bot.channel_sync_help_owner_commands');
        }

        $publisher->sendPrivateMessage($chatId, implode("\n", $lines));
    }

    private function sendSettingsScreen(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $screen = 'menu'
    ): void {
        if ($screen === 'dedup') {
            $dedup = ContentSyncAdminPrefs::getDedupMode($botItem->id, $chatId, $type);
            $lines = [
                trans('bot.channel_sync_sub_dedup_title'),
                trans('bot.channel_sync_sub_dedup_channel') . ($dedup === ContentSyncAdminPrefs::DEDUP_SOURCE_CHANNEL ? ' ✅' : ''),
                trans('bot.channel_sync_sub_dedup_platform') . ($dedup === ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM ? ' ✅' : ''),
            ];
            $rows = [
                [['text' => trans('bot.channel_sync_sub_dedup_channel'), 'callback_data' => 'cps:dedup:channel']],
                [['text' => trans('bot.channel_sync_sub_dedup_platform'), 'callback_data' => 'cps:dedup:platform']],
                [['text' => trans('bot.channel_sync_sub_back'), 'callback_data' => 'cps:menu']],
            ];
        } elseif ($screen === 'time') {
            $mode = ContentSyncAdminPrefs::getMode($botItem->id, $chatId, $type);
            $lines = [
                trans('bot.channel_sync_sub_time_title'),
                trans('bot.channel_sync_sub_time_immediate') . ($mode === ContentSyncAdminPrefs::MODE_IMMEDIATE ? ' ✅' : ''),
                trans('bot.channel_sync_sub_time_queue') . ($mode === ContentSyncAdminPrefs::MODE_QUEUE ? ' ✅' : ''),
            ];
            $rows = [
                [['text' => trans('bot.channel_sync_sub_time_immediate'), 'callback_data' => 'cps:mode:immediate']],
                [['text' => trans('bot.channel_sync_sub_time_queue'), 'callback_data' => 'cps:mode:queue']],
                [['text' => trans('bot.channel_sync_sub_back'), 'callback_data' => 'cps:menu']],
            ];
        } elseif ($screen === 'fast') {
            $fast = ContentSyncAdminPrefs::getFastMode($botItem->id, $chatId, $type);
            $lines = [
                trans('bot.channel_sync_sub_fast_title'),
                trans('bot.channel_sync_sub_fast_on') . ($fast ? ' ✅' : ''),
                trans('bot.channel_sync_sub_fast_off') . (!$fast ? ' ✅' : ''),
            ];
            $rows = [
                [['text' => trans('bot.channel_sync_sub_fast_on'), 'callback_data' => 'cps:fast:on']],
                [['text' => trans('bot.channel_sync_sub_fast_off'), 'callback_data' => 'cps:fast:off']],
                [['text' => trans('bot.channel_sync_sub_back'), 'callback_data' => 'cps:menu']],
            ];
        } elseif ($screen === 'defaults') {
            // صفحه‌ی پیش‌فرض مشترک ادمین‌ها — فقط مالک
            if (!$this->service->isOwner($botItem, $chatId, $type)) {
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_not_owner'));
                return;
            }
            $defaults = ContentSyncAdminPrefs::botDefaultsFor($botItem->id, $type);
            $lines = [
                trans('bot.channel_sync_sub_defaults_title'),
                trans('bot.channel_sync_settings_dedup_line', [
                    'dedup' => $defaults->dedup_mode === ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM
                        ? trans('bot.channel_sync_settings_dedup_platform')
                        : trans('bot.channel_sync_settings_dedup_channel'),
                ]),
                trans('bot.channel_sync_settings_mode_line', [
                    'mode' => $defaults->publish_mode === ContentSyncAdminPrefs::MODE_QUEUE
                        ? trans('bot.channel_sync_mode_queue')
                        : trans('bot.channel_sync_mode_immediate'),
                ]),
                trans('bot.channel_sync_settings_fast_line', [
                    'fast' => $defaults->fast_mode ? trans('bot.channel_sync_settings_fast_on') : trans('bot.channel_sync_settings_fast_off'),
                ]),
                trans('bot.channel_sync_settings_attr_line', [
                    'attr' => $this->attributionLabel((string) ($defaults->source_attribution ?: ContentSyncAdminPrefs::ATTR_SIGNED)),
                ]),
                trans('bot.channel_sync_sub_defaults_note'),
            ];
            $rows = [
                [['text' => trans('bot.channel_sync_sub_defaults_dedup'), 'callback_data' => 'cps:sub:defaults:dedup']],
                [['text' => trans('bot.channel_sync_sub_defaults_time'), 'callback_data' => 'cps:sub:defaults:time']],
                [['text' => trans('bot.channel_sync_sub_defaults_fast'), 'callback_data' => 'cps:sub:defaults:fast']],
                [['text' => trans('bot.channel_sync_sub_defaults_attr'), 'callback_data' => 'cps:sub:defaults:attr']],
                [['text' => trans('bot.channel_sync_sub_back'), 'callback_data' => 'cps:menu']],
            ];
        } elseif ($screen === 'defaults:dedup') {
            if (!$this->service->isOwner($botItem, $chatId, $type)) {
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_not_owner'));
                return;
            }
            $defaults = ContentSyncAdminPrefs::botDefaultsFor($botItem->id, $type);
            $lines = [
                trans('bot.channel_sync_sub_defaults_dedup_title'),
                trans('bot.channel_sync_sub_dedup_channel') . ($defaults->dedup_mode === ContentSyncAdminPrefs::DEDUP_SOURCE_CHANNEL ? ' ✅' : ''),
                trans('bot.channel_sync_sub_dedup_platform') . ($defaults->dedup_mode === ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM ? ' ✅' : ''),
            ];
            $rows = [
                [['text' => trans('bot.channel_sync_sub_dedup_channel'), 'callback_data' => 'cps:default:dedup:channel']],
                [['text' => trans('bot.channel_sync_sub_dedup_platform'), 'callback_data' => 'cps:default:dedup:platform']],
                [['text' => trans('bot.channel_sync_sub_back'), 'callback_data' => 'cps:sub:defaults']],
            ];
        } elseif ($screen === 'defaults:time') {
            if (!$this->service->isOwner($botItem, $chatId, $type)) {
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_not_owner'));
                return;
            }
            $defaults = ContentSyncAdminPrefs::botDefaultsFor($botItem->id, $type);
            $lines = [
                trans('bot.channel_sync_sub_defaults_time_title'),
                trans('bot.channel_sync_sub_time_immediate') . ($defaults->publish_mode === ContentSyncAdminPrefs::MODE_IMMEDIATE ? ' ✅' : ''),
                trans('bot.channel_sync_sub_time_queue') . ($defaults->publish_mode === ContentSyncAdminPrefs::MODE_QUEUE ? ' ✅' : ''),
            ];
            $rows = [
                [['text' => trans('bot.channel_sync_sub_time_immediate'), 'callback_data' => 'cps:default:mode:immediate']],
                [['text' => trans('bot.channel_sync_sub_time_queue'), 'callback_data' => 'cps:default:mode:queue']],
                [['text' => trans('bot.channel_sync_sub_back'), 'callback_data' => 'cps:sub:defaults']],
            ];
        } elseif ($screen === 'defaults:fast') {
            if (!$this->service->isOwner($botItem, $chatId, $type)) {
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_not_owner'));
                return;
            }
            $defaults = ContentSyncAdminPrefs::botDefaultsFor($botItem->id, $type);
            $lines = [
                trans('bot.channel_sync_sub_defaults_fast_title'),
                trans('bot.channel_sync_sub_fast_on') . ($defaults->fast_mode ? ' ✅' : ''),
                trans('bot.channel_sync_sub_fast_off') . (!$defaults->fast_mode ? ' ✅' : ''),
            ];
            $rows = [
                [['text' => trans('bot.channel_sync_sub_fast_on'), 'callback_data' => 'cps:default:fast:on']],
                [['text' => trans('bot.channel_sync_sub_fast_off'), 'callback_data' => 'cps:default:fast:off']],
                [['text' => trans('bot.channel_sync_sub_back'), 'callback_data' => 'cps:sub:defaults']],
            ];
        } elseif ($screen === 'attr') {
            $attr = ContentSyncAdminPrefs::getAttributionMode($botItem->id, $chatId, $type);
            $lines = [
                trans('bot.channel_sync_sub_attr_title'),
                trans('bot.channel_sync_sub_attr_signed') . ($attr === ContentSyncAdminPrefs::ATTR_SIGNED ? ' ✅' : ''),
                trans('bot.channel_sync_sub_attr_link_only') . ($attr === ContentSyncAdminPrefs::ATTR_LINK_ONLY ? ' ✅' : ''),
                trans('bot.channel_sync_sub_attr_none') . ($attr === ContentSyncAdminPrefs::ATTR_NONE ? ' ✅' : ''),
            ];
            $rows = [
                [['text' => trans('bot.channel_sync_sub_attr_signed'), 'callback_data' => 'cps:attr:signed']],
                [['text' => trans('bot.channel_sync_sub_attr_link_only'), 'callback_data' => 'cps:attr:link_only']],
                [['text' => trans('bot.channel_sync_sub_attr_none'), 'callback_data' => 'cps:attr:none']],
                [['text' => trans('bot.channel_sync_sub_back'), 'callback_data' => 'cps:menu']],
            ];
        } elseif ($screen === 'defaults:attr') {
            if (!$this->service->isOwner($botItem, $chatId, $type)) {
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_not_owner'));
                return;
            }
            $defaults = ContentSyncAdminPrefs::botDefaultsFor($botItem->id, $type);
            $lines = [
                trans('bot.channel_sync_sub_defaults_attr_title'),
                trans('bot.channel_sync_sub_attr_signed') . ($defaults->source_attribution === ContentSyncAdminPrefs::ATTR_SIGNED ? ' ✅' : ''),
                trans('bot.channel_sync_sub_attr_link_only') . ($defaults->source_attribution === ContentSyncAdminPrefs::ATTR_LINK_ONLY ? ' ✅' : ''),
                trans('bot.channel_sync_sub_attr_none') . ($defaults->source_attribution === ContentSyncAdminPrefs::ATTR_NONE ? ' ✅' : ''),
            ];
            $rows = [
                [['text' => trans('bot.channel_sync_sub_attr_signed'), 'callback_data' => 'cps:default:attr:signed']],
                [['text' => trans('bot.channel_sync_sub_attr_link_only'), 'callback_data' => 'cps:default:attr:link_only']],
                [['text' => trans('bot.channel_sync_sub_attr_none'), 'callback_data' => 'cps:default:attr:none']],
                [['text' => trans('bot.channel_sync_sub_back'), 'callback_data' => 'cps:sub:defaults']],
            ];
        } else {
            // منوی اصلی /settings
            $dedup = ContentSyncAdminPrefs::getDedupMode($botItem->id, $chatId, $type);
            $mode = ContentSyncAdminPrefs::getMode($botItem->id, $chatId, $type);
            $fast = ContentSyncAdminPrefs::getFastMode($botItem->id, $chatId, $type);

            $lines = [
                trans('bot.channel_sync_settings_title'),
                trans('bot.channel_sync_settings_dedup_line', [
                    'dedup' => $dedup === ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM
                        ? trans('bot.channel_sync_settings_dedup_platform')
                        : trans('bot.channel_sync_settings_dedup_channel'),
                ]),
                trans('bot.channel_sync_settings_mode_line', [
                    'mode' => $mode === ContentSyncAdminPrefs::MODE_QUEUE
                        ? trans('bot.channel_sync_mode_queue')
                        : trans('bot.channel_sync_mode_immediate'),
                ]),
                trans('bot.channel_sync_settings_fast_line', [
                    'fast' => $fast ? trans('bot.channel_sync_settings_fast_on') : trans('bot.channel_sync_settings_fast_off'),
                ]),
                trans('bot.channel_sync_settings_attr_line', [
                    'attr' => $this->attributionLabel(ContentSyncAdminPrefs::getAttributionMode($botItem->id, $chatId, $type)),
                ]),
            ];

            $rows = [
                [['text' => trans('bot.channel_sync_menu_dest'), 'callback_data' => 'cps:sub:dedup']],
                [['text' => trans('bot.channel_sync_menu_time'), 'callback_data' => 'cps:sub:time']],
                [['text' => trans('bot.channel_sync_menu_fast'), 'callback_data' => 'cps:sub:fast']],
            ];

            if ($this->service->isOwner($botItem, $chatId, $type)) {
                $rows[] = [['text' => trans('bot.channel_sync_menu_defaults'), 'callback_data' => 'cps:sub:defaults']];
            }

            $rows[] = [['text' => trans('bot.channel_sync_menu_attr'), 'callback_data' => 'cps:sub:attr']];
            $rows[] = [['text' => trans('bot.channel_sync_sub_back'), 'callback_data' => 'cps:back']];
        }

        $publisher->sendPrivateMessage($chatId, implode("\n", $lines), $rows);
    }

    /**
     * callbackهای همگام‌سازی: ناوبری منوی /settings + انتخاب مقادیر (با toast ثبت)
     * + انتخاب تگ. callbackهای قدیمی (cps:dedup، cps:fast، cps:mode، cps:default)
     * همچنان کار می‌کنند.
     */
    private function handleContentSyncCallback(
        ChannelPosterPublisher $publisher,
        ?string $callbackId,
        Bot $botItem,
        string $type,
        string $chatId,
        string $data
    ): void {
        // ناوبری منوی /settings — ادمین‌ها
        if ($data === 'cps:menu'
            || $data === 'cps:sub:dedup'
            || $data === 'cps:sub:time'
            || $data === 'cps:sub:fast'
            || $data === 'cps:sub:attr'
        ) {
            $screen = 'menu';
            if ($data === 'cps:sub:dedup') {
                $screen = 'dedup';
            }
            if ($data === 'cps:sub:time') {
                $screen = 'time';
            }
            if ($data === 'cps:sub:fast') {
                $screen = 'fast';
            }
            if ($data === 'cps:sub:attr') {
                $screen = 'attr';
            }
            $publisher->answerCallback($callbackId);
            $this->sendSettingsScreen($publisher, $botItem, $type, $chatId, $screen);
            return;
        }

        // ناوبری صفحه‌ی پیش‌فرض مشترک — فقط مالک
        if ($data === 'cps:sub:defaults'
            || $data === 'cps:sub:defaults:dedup'
            || $data === 'cps:sub:defaults:time'
            || $data === 'cps:sub:defaults:fast'
            || $data === 'cps:sub:defaults:attr'
        ) {
            if (!$this->service->isOwner($botItem, $chatId, $type)) {
                $publisher->answerCallback($callbackId, trans('bot.channel_poster_not_owner'));
                return;
            }
            $screen = 'defaults';
            if ($data === 'cps:sub:defaults:dedup') {
                $screen = 'defaults:dedup';
            }
            if ($data === 'cps:sub:defaults:time') {
                $screen = 'defaults:time';
            }
            if ($data === 'cps:sub:defaults:fast') {
                $screen = 'defaults:fast';
            }
            if ($data === 'cps:sub:defaults:attr') {
                $screen = 'defaults:attr';
            }
            $publisher->answerCallback($callbackId);
            $this->sendSettingsScreen($publisher, $botItem, $type, $chatId, $screen);
            return;
        }

        // بازگشت از منوی تنظیمات
        if ($data === 'cps:back') {
            $publisher->answerCallback($callbackId, trans('bot.channel_sync_settings_done'));
            return;
        }

        // انتخاب مقدار (شخصی) — ذخیره + toast، بدون پیام جدید
        if ($data === 'cps:dedup:channel' || $data === 'cps:dedup:platform') {
            ContentSyncAdminPrefs::forAdmin($botItem->id, $chatId, $type)->forceFill([
                'dedup_mode' => $data === 'cps:dedup:channel'
                    ? ContentSyncAdminPrefs::DEDUP_SOURCE_CHANNEL
                    : ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM,
            ])->save();
            $label = $data === 'cps:dedup:channel'
                ? trans('bot.channel_sync_settings_dedup_channel')
                : trans('bot.channel_sync_settings_dedup_platform');
            $publisher->answerCallback($callbackId, trans('bot.channel_sync_settings_saved', ['label' => $label]));
            return;
        }

        if ($data === 'cps:fast:on' || $data === 'cps:fast:off') {
            ContentSyncAdminPrefs::forAdmin($botItem->id, $chatId, $type)->forceFill([
                'fast_mode' => $data === 'cps:fast:on',
            ])->save();
            $label = $data === 'cps:fast:on'
                ? trans('bot.channel_sync_settings_fast_on')
                : trans('bot.channel_sync_settings_fast_off');
            $publisher->answerCallback($callbackId, trans('bot.channel_sync_settings_saved', ['label' => $label]));
            return;
        }

        if ($data === 'cps:mode:immediate' || $data === 'cps:mode:queue') {
            $mode = $data === 'cps:mode:immediate' ? ContentSyncAdminPrefs::MODE_IMMEDIATE : ContentSyncAdminPrefs::MODE_QUEUE;
            ContentSyncAdminPrefs::forAdmin($botItem->id, $chatId, $type)->forceFill(['publish_mode' => $mode])->save();
            $label = $mode === ContentSyncAdminPrefs::MODE_QUEUE
                ? trans('bot.channel_sync_mode_queue')
                : trans('bot.channel_sync_mode_immediate');
            $publisher->answerCallback($callbackId, trans('bot.channel_sync_settings_saved', ['label' => $label]));
            return;
        }

        // §4: روش نمایش منبع (شخصی)
        if ($data === 'cps:attr:signed' || $data === 'cps:attr:link_only' || $data === 'cps:attr:none') {
            $value = $data === 'cps:attr:signed'
                ? ContentSyncAdminPrefs::ATTR_SIGNED
                : ($data === 'cps:attr:link_only' ? ContentSyncAdminPrefs::ATTR_LINK_ONLY : ContentSyncAdminPrefs::ATTR_NONE);
            ContentSyncAdminPrefs::forAdmin($botItem->id, $chatId, $type)->forceFill(['source_attribution' => $value])->save();
            $publisher->answerCallback($callbackId, trans('bot.channel_sync_settings_saved', ['label' => $this->attributionLabel($value)]));
            return;
        }

        // پیش‌فرض مشترک ربات — فقط مالک
        if (str_starts_with($data, 'cps:default:') && !$this->service->isOwner($botItem, $chatId, $type)) {
            $publisher->answerCallback($callbackId, trans('bot.channel_poster_not_owner'));
            return;
        }

        if (str_starts_with($data, 'cps:default:dedup:')) {
            ContentSyncAdminPrefs::botDefaultsFor($botItem->id, $type)->forceFill([
                'dedup_mode' => str_ends_with($data, ':channel')
                    ? ContentSyncAdminPrefs::DEDUP_SOURCE_CHANNEL
                    : ContentSyncAdminPrefs::DEDUP_SOURCE_PLATFORM,
                'set_by_chat_id' => $chatId,
            ])->save();
            $label = str_ends_with($data, ':channel')
                ? trans('bot.channel_sync_settings_dedup_channel')
                : trans('bot.channel_sync_settings_dedup_platform');
            $publisher->answerCallback($callbackId, trans('bot.channel_sync_settings_saved', ['label' => $label]));
            return;
        }

        if (str_starts_with($data, 'cps:default:mode:')) {
            $defaults = ContentSyncAdminPrefs::botDefaultsFor($botItem->id, $type);
            $defaults->forceFill([
                'publish_mode' => str_ends_with($data, ':immediate')
                    ? ContentSyncAdminPrefs::MODE_IMMEDIATE
                    : ContentSyncAdminPrefs::MODE_QUEUE,
                'set_by_chat_id' => $chatId,
            ])->save();
            $label = str_ends_with($data, ':immediate')
                ? trans('bot.channel_sync_mode_immediate')
                : trans('bot.channel_sync_mode_queue');
            $publisher->answerCallback($callbackId, trans('bot.channel_sync_settings_saved', ['label' => $label]));
            return;
        }

        if (str_starts_with($data, 'cps:default:fast:')) {
            ContentSyncAdminPrefs::botDefaultsFor($botItem->id, $type)->forceFill([
                'fast_mode' => str_ends_with($data, ':on'),
                'set_by_chat_id' => $chatId,
            ])->save();
            $label = str_ends_with($data, ':on')
                ? trans('bot.channel_sync_settings_fast_on')
                : trans('bot.channel_sync_settings_fast_off');
            $publisher->answerCallback($callbackId, trans('bot.channel_sync_settings_saved', ['label' => $label]));
            return;
        }

        // §4: روش نمایش منبع (پیش‌فرض مشترک) — فقط مالک
        if (str_starts_with($data, 'cps:default:attr:')) {
            $value = str_ends_with($data, ':link_only')
                ? ContentSyncAdminPrefs::ATTR_LINK_ONLY
                : (str_ends_with($data, ':none') ? ContentSyncAdminPrefs::ATTR_NONE : ContentSyncAdminPrefs::ATTR_SIGNED);
            ContentSyncAdminPrefs::botDefaultsFor($botItem->id, $type)->forceFill([
                'source_attribution' => $value,
                'set_by_chat_id' => $chatId,
            ])->save();
            $publisher->answerCallback($callbackId, trans('bot.channel_sync_settings_saved', ['label' => $this->attributionLabel($value)]));
            return;
        }

        // §1: پاسخ سؤال کانال مبدأ
        if (str_starts_with($data, 'cps:src:')) {
            $this->handleSyncSourceCallback($publisher, $callbackId, $botItem, $type, $chatId, $data);
            return;
        }

        // انتخاب تگ (جریان fast mode)
        if (str_starts_with($data, 'cps:tag:')) {
            $publisher->answerCallback($callbackId);
            $this->handleSyncTagCallback($publisher, $botItem, $type, $chatId, (int) substr($data, 8));
            return;
        }
    }

    private function sendSyncTagKeyboard(ChannelPosterPublisher $publisher, string $chatId, array $groups): void
    {
        $rows = [];
        foreach ($groups as $index => $group) {
            $rows[] = [['text' => $group['label'], 'callback_data' => 'cps:tag:' . $index]];
        }

        $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_ask_tag'), $rows);
    }

    /**
     * §4: برچسب خوانای روش نمایش منبع (Source Attribution).
     */
    private function attributionLabel(string $mode): string
    {
        return match ($mode) {
            ContentSyncAdminPrefs::ATTR_LINK_ONLY => trans('bot.channel_sync_sub_attr_link_only'),
            ContentSyncAdminPrefs::ATTR_NONE => trans('bot.channel_sync_sub_attr_none'),
            default => trans('bot.channel_sync_sub_attr_signed'),
        };
    }

    /* ---------- دستورات ---------- */

    private function handleSyncModeCommand(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId
    ): void {
        $mode = ContentSyncAdminPrefs::getMode($botItem->id, $chatId, $type);

        // /mode فقط حالت فعلی را نمایش می‌دهد؛ تغییر از طریق /settings (زیرمنوی «زمان انتشار»)
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_mode_current', [
            'mode' => trans('bot.' . ($mode === ContentSyncAdminPrefs::MODE_QUEUE ? 'channel_sync_mode_queue' : 'channel_sync_mode_immediate')),
        ]));
    }

    private function handleSyncStatusCommand(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $text
    ): void {
        $arg = trim(explode(' ', $text, 2)[1] ?? '');

        $content = $arg !== ''
            ? $this->contentSync->findContent($botItem, $arg)
            : $this->contentSync->lastContent($botItem);

        if (!$content) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_not_found'));
            return;
        }

        $publisher->sendPrivateMessage($chatId, $this->contentSync->buildReport($content));
    }

    private function handleSyncRetryCommand(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId
    ): void {
        $content = $this->contentSync->lastContent($botItem);
        if (!$content) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_not_found'));
            return;
        }

        $failed = $content->versions()
            ->where('publish_status', \App\Models\ContentSyncVersion::STATUS_FAILED)
            ->count();

        if ($failed === 0) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_retry_nothing', ['id' => $content->id]));
            return;
        }

        $pub = $this->publisherFactory->make(
            $type === 'bale' ? $botItem->bale_bot_token : $botItem->telegram_bot_token,
            $type
        );

        $outcome = $this->contentSync->retryFailed($content, $pub, $chatId, $type);
        $publisher->sendPrivateMessage($chatId, $this->contentSync->buildFastConfirmation($content->fresh()));
    }

    private function handleSyncLastCommand(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId
    ): void {
        $content = $this->contentSync->lastContent($botItem);
        if (!$content) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_none'));
            return;
        }

        $publisher->sendPrivateMessage($chatId, $this->contentSync->buildReport($content));
    }

    private function handleSyncCommand(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $text
    ): void {
        $arg = trim(explode(' ', $text, 2)[1] ?? '');
        if ($arg === '') {
            $this->handleSyncLastCommand($publisher, $botItem, $type, $chatId);
            return;
        }

        $content = $this->contentSync->findContent($botItem, $arg);
        if (!$content) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_not_found'));
            return;
        }

        $publisher->sendPrivateMessage($chatId, $this->contentSync->buildReport($content));
    }

    private function handleSyncTagsCommand(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId
    ): void {
        $groups = $this->service->listTagGroups($botItem->id);
        if ($groups === []) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_no_tags'));
            return;
        }

        $lines = [trans('bot.channel_sync_tags_header')];
        foreach ($groups as $group) {
            $count = \App\Models\ChannelPosterDestination::where('bot_id', $botItem->id)
                ->where('is_active', true)
                ->where('tag', $group['key'])
                ->count();
            $lines[] = '• ' . $group['label'] . ' (' . $count . ')';
        }

        $lines[] = '';
        $lines[] = trans('bot.channel_sync_last_tag', [
            'tag' => ContentSyncAdminPrefs::where('bot_id', $botItem->id)
                ->where('admin_chat_id', $chatId)
                ->where('origin', $type)
                ->value('last_tag') ?? '—',
        ]);

        $publisher->sendPrivateMessage($chatId, implode("\n", $lines));
    }

    private function handleSyncAdminCommand(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $text
    ): void {
        // فقط مالک می‌تواند ادمین اضافه کند
        if (!$this->service->isOwner($botItem, $chatId, $type)) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_not_owner'));
            return;
        }

        $arg = trim(explode(' ', $text, 2)[1] ?? '');
        if ($arg === '') {
            $admins = \App\Models\ContentSyncAdmin::where('bot_id', $botItem->id)->get();
            if ($admins->isEmpty()) {
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_admin_none'));
                return;
            }

            $lines = [trans('bot.channel_sync_admin_header')];
            foreach ($admins as $admin) {
                $lines[] = '• ' . $admin->admin_chat_id . ' (' . $admin->origin . ')';
            }
            $publisher->sendPrivateMessage($chatId, implode("\n", $lines));
            return;
        }

        if (preg_match('/^\d+$/', $arg) !== 1) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_admin_invalid'));
            return;
        }

        \App\Models\ContentSyncAdmin::add($botItem->id, $arg, $type);
        \App\Models\ContentSyncOperation::log(
            $botItem->id,
            $chatId,
            $type,
            null,
            \App\Models\ContentSyncOperation::OP_ADD_ADMIN,
            \App\Models\ContentSyncOperation::RESULT_OK
        );

        $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_admin_added', ['chat_id' => $arg]));
    }

    /**
     * /channels — فهرست کانال‌های متصل با دکمه حذف برای هر کدام.
     */
    private function handleChannelsCommand(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId
    ): void {
        $destinations = ChannelPosterDestination::where('bot_id', $botItem->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($destinations->isEmpty()) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_channels_empty'));
            return;
        }

        $rows = [];
        foreach ($destinations as $dest) {
            $label = $dest->platform
                . ' — '
                . ($dest->channel_title ?: $dest->channel_chat_id)
                . ' (' . $this->service->displayTagLabel($dest->tag) . ')';
            $rows[] = [
                ['text' => $label, 'callback_data' => 'cp:unlink:' . $dest->id],
            ];
        }

        $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_channels_header'), $rows);
    }

    /**
     * دکمه «حذف» — کانال را غیرفعال می‌کند تا از تگ و مقاصد همگام‌سازی کنار برود.
     */
    private function handleUnlinkCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        int $destinationId
    ): void {
        $destination = ChannelPosterDestination::where('bot_id', $botItem->id)
            ->where('id', $destinationId)
            ->first();

        if (!$destination) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_channels_gone'));
            return;
        }

        $destination->forceFill(['is_active' => false])->save();
        \App\Models\ContentSyncOperation::log(
            $botItem->id,
            $chatId,
            $type,
            null,
            \App\Models\ContentSyncOperation::OP_UNLINK,
            \App\Models\ContentSyncOperation::RESULT_OK,
            'destination_id=' . $destination->id
        );

        $label = $destination->platform
            . ' — '
            . ($destination->channel_title ?: $destination->channel_chat_id)
            . ' (' . $this->service->displayTagLabel($destination->tag) . ')';
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_sync_channel_unlinked', ['channel' => $label]));

        // فهرست به‌روز را دوباره نشان بده
        $this->handleChannelsCommand($publisher, $botItem, $type, $chatId);
    }
}
