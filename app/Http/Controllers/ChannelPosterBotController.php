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
use App\Services\ChannelPoster\SignatureRenderer;
use App\Services\ChannelPoster\SignatureSettingsService;
use App\Services\ContentSync\ContentSyncService;
use App\Services\ContentSync\PostUrlParser;
use App\Services\SyncPlatformRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
    public const STATE_AWAITING_SOROUSH_TOKEN = 'cp_awaiting_soroush_token';
    public const STATE_AWAITING_SOROUSH_CHAT_ID = 'cp_awaiting_soroush_chat_id';
    public const STATE_AWAITING_RUBIKA_TOKEN = 'cp_awaiting_rubika_token';
    public const STATE_AWAITING_RUBIKA_CHAT_ID = 'cp_awaiting_rubika_chat_id';
    public const STATE_AWAITING_SIGN_TAG = 'cp_awaiting_sign_tag';
    public const STATE_AWAITING_SIGN_CHANNEL = 'cp_awaiting_sign_channel';
    public const STATE_AWAITING_SIGN_LINK = 'cp_awaiting_sign_link';
    public const STATE_AWAITING_SIGN_LABEL = 'cp_awaiting_sign_label';
    public const STATE_AWAITING_SIGN_CONFIRM = 'cp_awaiting_sign_confirm';

    // === Smart Signature Settings (CP-SMART-SIGNATURE-SETTINGS) ===
    public const STATE_SSIG_TAG = 'cp_ssig_awaiting_tag';
    public const STATE_SSIG_CHANNEL = 'cp_ssig_awaiting_channel';
    public const STATE_SSIG_POLICY = 'cp_ssig_awaiting_policy';
    public const STATE_SSIG_CUSTOM_PICK = 'cp_ssig_awaiting_custom_pick';
    public const STATE_SSIG_RANDOM_COUNT = 'cp_ssig_awaiting_random_count';
    public const STATE_SSIG_LAYOUT = 'cp_ssig_awaiting_layout';
    public const STATE_SSIG_SEPARATOR = 'cp_ssig_awaiting_separator';
    public const STATE_SSIG_APPLY = 'cp_ssig_awaiting_apply';
    public const STATE_SSIG_PREVIEW = 'cp_ssig_awaiting_preview';

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
                'owner' => $botItem->{SyncPlatformRegistry::ownerColumn($type)},
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
            // متن خطا ممکن است URL شامل توکن ربات باشد؛ همیشه قبل از لاگ ماسک می‌شود
            Log::error('[ChannelPoster] Webhook error', [
                'error' => TokenHelper::redactTokens($e->getMessage()),
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

        // Phase 5: config-driven — ستون توکن از رجیستری پلتفرم‌ها (sync_platforms)
        $tokenColumn = SyncPlatformRegistry::tokenColumn($type);

        if ($token) {
            $botItem = Bot::where($tokenColumn, $token)->first();

            if ($botItem) {
                return [$botItem, $token];
            }
        }

        if ($botId) {
            $botItem = Bot::find($botId);
            if (!$botItem) {
                return null;
            }
            $dbToken = $botItem->{$tokenColumn};
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
            // Phase 5: روبیکا — shape { "update": { "type": "NewMessage", ... } }
            $normalized = self::normalizeRubikaUpdate($data);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $fallback = $candidates[0] ?? [];
        unset($fallback['origin'], $fallback['token'], $fallback['bot_id'], $fallback['bot_mother_id'], $fallback['language']);

        return is_array($fallback) ? $fallback : [];
    }

    /**
     * Phase 5: نرمال‌سازی update روبیکا به shape عمومی موتور.
     *
     * روبیکا POST JSON با این شکل می‌فرستد (مستندات رسمی،
     * docs/features/platform-api-research.md):
     *   { "update": { "type": "NewMessage", "chat_id": "...",
     *                 "new_message": { "message_id", "text", "time",
     *                                   "sender_type": "User"|"Bot",
     *                                   "sender_id", ... } } }
     *
     * فقط NewMessage با sender_type=User به message تبدیل می‌شود؛ سایر
     * typeها (UpdatedMessage/RemovedMessage/StartedBot/StoppedBot/EventData)
     * و پیام‌های خودِ ربات نادیده گرفته می‌شوند (null → 200 ok بی‌عمل).
     */
    private static function normalizeRubikaUpdate(array $data): ?array
    {
        $u = $data['update'] ?? null;
        if (!is_array($u)) {
            return null;
        }

        if (($u['type'] ?? null) !== 'NewMessage') {
            return null;
        }

        $msg = $u['new_message'] ?? null;
        if (!is_array($msg)) {
            return null;
        }

        $senderType = (string) ($msg['sender_type'] ?? '');
        if ($senderType !== 'User') {
            return null;
        }

        $chatId = (string) ($u['chat_id'] ?? $msg['chat_id'] ?? '');
        $senderId = (string) ($msg['sender_id'] ?? $chatId);
        if ($chatId === '' && $senderId === '') {
            return null;
        }

        $normalized = [
            'message' => [
                'message_id' => $msg['message_id'] ?? null,
                'chat' => [
                    'id' => $chatId !== '' ? $chatId : $senderId,
                    'type' => 'private',
                ],
                'from' => ['id' => $senderId !== '' ? $senderId : $chatId],
            ],
        ];

        $text = $msg['text'] ?? null;
        if (is_string($text)) {
            $normalized['message']['text'] = $text;
        }

        return $normalized;
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
                    'owner' => $botItem->{SyncPlatformRegistry::ownerColumn($type)},
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
            $state = $this->getState($botItem, $chatId, $type);
            // CP-SMART-SIGNATURE-SETTINGS: لغو ویزاردِ تنظیمات هوشمند — اگر مطلبی
            // معوق داشت (ورود از «تغییر تنظیمات» در جریان ارسال) دوباره به انتخاب
            // زمان برمی‌گردد وگرنه حالت کامل پاک می‌شود.
            if ($state && str_starts_with($state->state, 'cp_ssig_')) {
                $this->finishSmartSigWizard($publisher, $botItem, $type, $chatId, false);
                return;
            }
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
                $this->sendPlatformKeyboard($publisher, $chatId, $type, trim($text));
                return;
            }
        }

        if ($state && $state->state === self::STATE_AWAITING_PLATFORM && is_string($text) && !str_starts_with($text, '/')) {
            $this->handlePlatformByText($publisher, $botItem, $type, $chatId, $text);
            return;
        }

        if ($state && is_string($text) && !str_starts_with($text, '/')) {
            if ($state->state === self::STATE_AWAITING_SIGN_LINK) {
                $this->handleSignLink($publisher, $botItem, $type, $chatId, $text, $state->data ?? []);
                return;
            }
            if ($state->state === self::STATE_AWAITING_SIGN_LABEL) {
                $this->handleSignLabel($publisher, $botItem, $type, $chatId, $text, $state->data ?? []);
                return;
            }
            if ($state->state === self::STATE_AWAITING_SIGN_CONFIRM) {
                // دکمه در مبدأهای native (soroush/rubika) حذف می‌شود → معادل متنی
                $this->handleSignConfirmText($publisher, $botItem, $type, $chatId, $text, $state->data ?? []);
                return;
            }

            // === CP-SMART-SIGNATURE-SETTINGS: معادل‌های متنی (سروش/روبیکا) ===
            // دکمه‌ها در مبدأهای native حذف می‌شوند؛ همهٔ مراحل ویزارد با تایپ
            // هم کار می‌کنند. (برای همهٔ مبدأها مجاز است.)
            if (str_starts_with($state->state, 'cp_ssig_')) {
                $this->handleSmartSigText($publisher, $botItem, $type, $chatId, $text, $state->data ?? []);
                return;
            }

            // سؤال کوتاهِ هر-بار: معادل متنی (بله/خیر/تنظیمات/دیگر نپرس/الان/صف).
            // لینکِ جدید (محتوای تازه) به‌دنبال نمی‌افتد — به جریان اصلی برمی‌گردد.
            if ($state->state === self::STATE_AWAITING_DESTINATION
                && !($state->data['ssig_answered'] ?? false)
                && !str_contains($text, 'http')
            ) {
                $tagKey = (string) ($state->data['selected_tag'] ?? '');
                $dests = $this->service->resolveByTag($botItem->id, $tagKey);
                if ($dests->isEmpty()) {
                    $dests = $this->service->resolveDestinations($botItem->id, 'all');
                }
                if (SignatureSettingsService::effectiveApplicationPolicy($dests) === SignatureSettingsService::APPLY_ASK_EVERY_POST) {
                    $this->handleSmartSigPostQuestionText($publisher, $botItem, $type, $chatId, $text, $state->data ?? []);
                    return;
                }
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
            if ($state->state === self::STATE_AWAITING_SOROUSH_TOKEN) {
                $this->handleSoroushToken($publisher, $botItem, $type, $chatId, $text, $state->data ?? []);
                return;
            }
            if ($state->state === self::STATE_AWAITING_SOROUSH_CHAT_ID) {
                $this->handleExternalChatId($publisher, $botItem, $type, $chatId, $text, $state->data ?? [], ChannelPosterDestination::PLATFORM_SOROUSH);
                return;
            }
            if ($state->state === self::STATE_AWAITING_RUBIKA_TOKEN) {
                $this->handleRubikaToken($publisher, $botItem, $type, $chatId, $text, $state->data ?? []);
                return;
            }
            if ($state->state === self::STATE_AWAITING_RUBIKA_CHAT_ID) {
                $this->handleExternalChatId($publisher, $botItem, $type, $chatId, $text, $state->data ?? [], ChannelPosterDestination::PLATFORM_RUBIKA);
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
        $this->logMediaDiagnostic($message, $media);
        if (!$media) {
            if (is_string($text) && str_starts_with($text, '/')) {
                return;
            }
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_unsupported'));
            return;
        }

        // فایل صوتی با قالب ناپشتیبانی‌شده: به‌جای بی‌صدا رد شدن، پیام خطای
        // مشخصی ارسال می‌شود (سیاست: هرگز silent fail برای صدا).
        if (($media['type'] ?? '') === 'audio_unsupported') {
            Log::info('[ChannelPoster] Unsupported audio format rejected', [
                'bot_id' => $botItem->id,
                'mime_type' => $media['mime_type'] ?? null,
                'extension' => is_string($media['file_name'] ?? null)
                    ? strtolower(pathinfo($media['file_name'], PATHINFO_EXTENSION))
                    : null,
            ]);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_audio_unsupported'));
            return;
        }

        if (!$this->service->hasAnyDestination($botItem->id)) {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_BALE_FORWARD);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_bale_forward'));
            return;
        }

        $this->offerOrPublishContent($publisher, $botItem, $type, $chatId, $media);
    }

    /**
     * Diagnostic امن برای ورودی رسانه‌ای وب‌هوک.
     *
     * فقط نام کلیدهای پیام و (در صورت وجود) نام فایل، پسوند، MIME و حجم
     * را لاگ می‌کند تا مشخص باشد بله فایل MP3 را به‌صورت message.audio یا
     * message.document فرستاده است. هرگز توکن، URL یا محتوای فایل ثبت
     * نمی‌شود.
     */
    private function logMediaDiagnostic(array $message, ?array $media): void
    {
        $hasMedia = isset(
            $message['photo'],
            $message['video'],
            $message['voice'],
            $message['audio'],
            $message['document'],
            $message['caption']
        );

        if (!$hasMedia) {
            return;
        }

        try {
            $log = [
                'message_keys' => array_keys($message),
                'resolved_type' => $media['type'] ?? null,
            ];

            if (is_array($message['document'] ?? null)) {
                $doc = $message['document'];
                $log['document_present'] = true;
                $log['document_file_name'] = $doc['file_name'] ?? null;
                $log['document_extension'] = is_string($doc['file_name'] ?? null)
                    ? strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION))
                    : null;
                $log['document_mime_type'] = $doc['mime_type'] ?? null;
                $log['document_file_size'] = $doc['file_size'] ?? null;
            }

            if (is_array($message['audio'] ?? null)) {
                $audio = $message['audio'];
                $log['audio_present'] = true;
                $log['audio_file_name'] = $audio['file_name'] ?? null;
                $log['audio_mime_type'] = $audio['mime_type'] ?? null;
                $log['audio_file_size'] = $audio['file_size'] ?? null;
            }

            Log::info('[ChannelPoster][diag] incoming media', $log);
        } catch (Throwable) {
            // خطای لاگ نباید پردازش وب‌هوک را مختل کند
        }
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
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_tag_name') . $this->nativeInputHint($type));
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
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_new_or_existing_tag') . $this->nativeInputHint($type), $rows);
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

        $testResult = $publisher->sendTestMessage(
            $forward['id'],
            trans('bot.channel_poster_test_message')
        );

        if (!$testResult['ok']) {
            $publisher->sendPrivateMessage($chatId, $this->testSendFailureMessage($testResult));
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
            $this->sendPlatformKeyboard($publisher, $chatId, $type, $tag);
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

        // === CP-SMART-SIGNATURE-SETTINGS: سؤال کوتاه هر-بار (ASK_EVERY_POST) ===
        if ($data === 'cp:ssigp:yes' || $data === 'cp:ssigp:no') {
            $state = $this->getState($botItem, $chatId, $type);
            if (!$state || $state->state !== self::STATE_AWAITING_DESTINATION) {
                return;
            }
            $stateData = $state->data ?? [];
            $stateData['signature_enabled'] = ($data === 'cp:ssigp:yes');
            $stateData['ssig_answered'] = true;
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
            // بعد از پاسخ، فقط انتخاب زمان ارسال می‌ماند
            $tagKey = (string) ($stateData['selected_tag'] ?? '');
            $this->askSignatureAndQueue($publisher, $botItem, $type, $chatId, $tagKey);
            return;
        }

        if ($data === 'cp:ssigp:stop') {
            // «دیگر نپرس» → مقصدهای ask_every_post به always_apply تبدیل می‌شوند (دائمی)
            $state = $this->getState($botItem, $chatId, $type);
            if (!$state || $state->state !== self::STATE_AWAITING_DESTINATION) {
                return;
            }
            $stateData = $state->data ?? [];
            $tagKey = (string) ($stateData['selected_tag'] ?? '');
            $dests = $this->service->resolveByTag($botItem->id, $tagKey);
            foreach ($dests as $d) {
                if (!SignatureSettingsService::requiresPostQuestion($d)) {
                    continue;
                }
                $settings = SignatureSettingsService::normalize($d->signature_settings) ?? SignatureSettingsService::defaults();
                $settings['application_policy'] = SignatureSettingsService::APPLY_ALWAYS;
                $this->service->saveSmartSignatureSettings($d->id, $settings);
            }
            $stateData['signature_enabled'] = true;
            $stateData['ssig_answered'] = true;
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
            $this->askSignatureAndQueue($publisher, $botItem, $type, $chatId, $tagKey);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_stop_saved'));
            return;
        }

        if ($data === 'cp:ssigp:settings') {
            // «تغییر تنظیمات» → ویزارد تنظیمات هوشمند (محتوای معوق حفظ می‌شود)
            $state = $this->getState($botItem, $chatId, $type);
            if (!$state || $state->state !== self::STATE_AWAITING_DESTINATION) {
                return;
            }
            $this->startSmartSigWizard($publisher, $botItem, $type, $chatId, $state->data ?? []);
            return;
        }

        // === CP-SMART-SIGNATURE-SETTINGS: ویزارد تنظیمات هوشمند امضا ===
        if ($data === 'cp:ssig') {
            $this->startSmartSigWizard($publisher, $botItem, $type, $chatId);
            return;
        }
        if (str_starts_with($data, 'cp:ssigt:')) {
            $this->handleSmartSigTagCallback($publisher, $botItem, $type, $chatId, (int) substr($data, 9));
            return;
        }
        if (str_starts_with($data, 'cp:ssigc:')) {
            $this->handleSmartSigChannelCallback($publisher, $botItem, $type, $chatId, (int) substr($data, 9));
            return;
        }
        if (str_starts_with($data, 'cp:ssigpol:')) {
            $this->handleSmartSigPolicyCallback($publisher, $botItem, $type, $chatId, substr($data, 11));
            return;
        }
        if (str_starts_with($data, 'cp:ssigck:')) {
            $rest = substr($data, 10);
            $this->handleSmartSigCustomPick($publisher, $botItem, $type, $chatId, $rest);
            return;
        }
        if (str_starts_with($data, 'cp:ssigrc:')) {
            $this->handleSmartSigRandomCount($publisher, $botItem, $type, $chatId, (int) substr($data, 10));
            return;
        }
        if (str_starts_with($data, 'cp:ssiglay:')) {
            $this->handleSmartSigLayoutCallback($publisher, $botItem, $type, $chatId, substr($data, 11));
            return;
        }
        if (str_starts_with($data, 'cp:ssigap:')) {
            $this->handleSmartSigApplyCallback($publisher, $botItem, $type, $chatId, substr($data, 10));
            return;
        }
        if ($data === 'cp:ssigpv:yes' || $data === 'cp:ssigpv:no') {
            $this->handleSmartSigPreviewCallback($publisher, $botItem, $type, $chatId, $data === 'cp:ssigpv:yes');
            return;
        }
        if ($data === 'cp:ssigbk') {
            $this->handleSmartSigBack($publisher, $botItem, $type, $chatId);
            return;
        }
        if ($data === 'cp:ssigcancel') {
            $this->finishSmartSigWizard($publisher, $botItem, $type, $chatId, false);
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
        if (str_starts_with($data, 'cp:signconf:')) {
            $this->handleSignConfirmCallback($publisher, $botItem, $type, $chatId, $data);
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
            return;
        }

        if ($platform === ChannelPosterDestination::PLATFORM_SOROUSH) {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_SOROUSH_TOKEN, ['tag' => $tag]);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_soroush_token'));
            return;
        }

        if ($platform === ChannelPosterDestination::PLATFORM_RUBIKA) {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_RUBIKA_TOKEN, ['tag' => $tag]);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_rubika_token'));
        }
    }

    /**
     * ورودی متنی جایگزین دکمه‌ها — برای مبدأ‌های soroush/rubika که کیبورد inline
     * را نمی‌گیرند (و در همه مبدأها به‌عنوان fallback). نام پلتفرم با alias فارسی/انگلیسی
     * شناسایی می‌شود و سپس همان مسیر handlePlatformChoice (دکمه) طی می‌شود.
     */
    private function handlePlatformByText(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $text
    ): void {
        $platform = match (mb_strtolower(trim($text))) {
            'bale', 'بله' => ChannelPosterDestination::PLATFORM_BALE,
            'telegram', 'تلگرام' => ChannelPosterDestination::PLATFORM_TELEGRAM,
            'eitaa', 'ایتا' => ChannelPosterDestination::PLATFORM_EITAA,
            'soroush', 'سروش' => ChannelPosterDestination::PLATFORM_SOROUSH,
            'rubika', 'روبیکا' => ChannelPosterDestination::PLATFORM_RUBIKA,
            default => null,
        };

        if ($platform === null) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_platform_invalid'));
            return; // state حفظ می‌شود — کاربر می‌تواند دوباره امتحان کند
        }

        $state = $this->getState($botItem, $chatId, $type);
        $tag = (string) ($state?->getData('tag') ?? '');
        $this->handlePlatformChoice($publisher, $botItem, $type, $chatId, $platform, $tag);
    }

    /**
     * یادآوری فقط برای مبدأ‌های native (soroush/rubika) — کیبورد inline آن‌ها
     * ارسال نمی‌شود، پس کاربر باید انتخاب را تایپ کند.
     */
    private function nativeInputHint(string $type): string
    {
        return in_array($type, ['soroush', 'rubika'], true)
            ? "\n\n" . trans('bot.channel_poster_platform_native_hint')
            : '';
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

    private function handleSoroushToken(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $token,
        array $stateData
    ): void {
        $token = trim($token);
        // توکن سoroush پلاس طبق مستندات رسمی هم‌فرمت تلگرام است.
        if (!TokenHelper::isToken($token, 'soroush')) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_invalid_token'));
            return;
        }

        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_SOROUSH_CHAT_ID, [
            'tag' => $stateData['tag'] ?? '',
            'bot_token' => $token,
        ]);
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_soroush_chat_id'));
    }

    private function handleRubikaToken(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $token,
        array $stateData
    ): void {
        $token = trim($token);
        // فرمت توکن روبیکا در مستندات اعلام نشده (opaque) — بررسی سنجشی.
        if (!TokenHelper::isToken($token, 'rubika')) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_invalid_token'));
            return;
        }

        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_RUBIKA_CHAT_ID, [
            'tag' => $stateData['tag'] ?? '',
            'bot_token' => $token,
        ]);
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_rubika_chat_id'));
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
        $testResult = $publisher->sendTestMessage(
            $channelChatId,
            trans('bot.channel_poster_test_message'),
            $platform,
            $token
        );
        if (!$testResult['ok']) {
            $publisher->sendPrivateMessage($chatId, $this->testSendFailureMessage($testResult));
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
        $key = match ($platform) {
            ChannelPosterDestination::PLATFORM_TELEGRAM => 'bot.channel_poster_telegram_connected',
            ChannelPosterDestination::PLATFORM_SOROUSH => 'bot.channel_poster_soroush_connected',
            ChannelPosterDestination::PLATFORM_RUBIKA => 'bot.channel_poster_rubika_connected',
            default => 'bot.channel_poster_eitaa_connected',
        };
        $message = trans($key, ['tag' => $tag !== '' ? $tag : trans('bot.channel_poster_untagged')]);
        if (($testResult['class'] ?? null) === ChannelPosterPublisher::TEST_SEND_SUCCESS_WITHOUT_MESSAGE_ID) {
            $message .= "\n\n" . trans('bot.channel_poster_unverified_note');
        }
        $publisher->sendPrivateMessage($chatId, $message);
    }

    /**
     * M4: پیام جدا برای هر دلیل شکست تست. «آدمین نیست» فقط وقتی شواهد
     * واقعی API (permission denied / HTTP 403) وجود داشته باشد.
     */
    private function testSendFailureMessage(array $result): string
    {
        $class = (string) ($result['class'] ?? '');

        return match (true) {
            $class === ChannelPosterPublisher::TEST_SEND_INVALID_INPUT => trans('bot.channel_poster_invalid_token'),
            $class === ChannelPosterPublisher::TEST_SEND_TRANSPORT_FAILURE => trans('bot.channel_poster_test_send_network'),
            $class === ChannelPosterPublisher::TEST_SEND_UNKNOWN_RESULT => trans('bot.channel_poster_test_send_unknown'),
            $class === ChannelPosterPublisher::TEST_SEND_API_REJECTED
                && (($result['permission_denied'] ?? false) === true || ($result['http_status'] ?? null) === 403)
                => trans('bot.channel_poster_not_admin'),
            $class === ChannelPosterPublisher::TEST_SEND_API_REJECTED
                && ($result['http_status'] ?? null) === 401
                => trans('bot.channel_poster_invalid_token'),
            $class === ChannelPosterPublisher::TEST_SEND_API_REJECTED => trans('bot.channel_poster_test_send_failed'),
            default => trans('bot.channel_poster_not_admin'),
        };
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
        if (!empty($media['file_id']) && SyncPlatformRegistry::canDownloadMediaFromOrigin($type)) {
            // Phase 5: data-driven skip — دانلود رسانه از API مبدأ فقط وقتی
            // مستند و تأییدشده است (bale/telegram). برای soroush/rubika جریان
            // با همان file_id ادامه می‌یابد؛ انتشار هم‌پلتفرم با file_id
            // انجام می‌شود و انتشار بین‌پلتفرم رسانه‌ای به‌صورت صریح خطا
            // گزارش می‌شود (هرگز موفقیت کاذب).
            $originToken = (string) $botItem->{SyncPlatformRegistry::tokenColumn($type)};
            if ($originToken !== '') {
                $mediaPath = $this->mediaStore->fetchAndStore(
                    $originToken,
                    (string) $media['file_id'],
                    $type,
                    (string) $media['type'],
                    is_string($media['file_name'] ?? null) && $media['file_name'] !== ''
                        ? (string) $media['file_name']
                        : null
                );
            }
        }

        $pending = [
            'content_type' => $media['type'],
            'text' => $media['text'],
            'file_id' => $media['file_id'],
            'media_path' => $mediaPath,
            'file_name' => is_string($media['file_name'] ?? null) && $media['file_name'] !== ''
                ? (string) $media['file_name']
                : null,
            'mime_type' => is_string($media['mime_type'] ?? null) && $media['mime_type'] !== ''
                ? (string) $media['mime_type']
                : null,
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
        $state = $this->getState($botItem, $chatId, $type);
        $stateData = $state?->data ?? [];

        $destinations = $this->service->resolveByTag($botItem->id, $tagKey);
        if ($destinations->isEmpty()) {
            $destinations = $this->service->resolveDestinations($botItem->id, 'all');
        }

        $policy = SignatureSettingsService::effectiveApplicationPolicy($destinations);

        // CP-SMART-SIGNATURE-SETTINGS: سیاست اعمالِ هوشمند
        if ($policy === SignatureSettingsService::APPLY_ASK_EVERY_POST && !($stateData['ssig_answered'] ?? false)) {
            // سؤال کوتاهِ هر-بار — هرگز ویزارد کامل
            $msg = trans('bot.channel_poster_ssig_ask_this_post');
            $rows = [
                [
                    ['text' => trans('bot.channel_poster_ssig_btn_yes'), 'callback_data' => 'cp:ssigp:yes'],
                    ['text' => trans('bot.channel_poster_ssig_btn_no'), 'callback_data' => 'cp:ssigp:no'],
                ],
                [
                    ['text' => trans('bot.channel_poster_ssig_btn_settings'), 'callback_data' => 'cp:ssigp:settings'],
                    ['text' => trans('bot.channel_poster_ssig_btn_stop_asking'), 'callback_data' => 'cp:ssigp:stop'],
                ],
            ];
            $this->sendNativeHintIfNeeded($publisher, $chatId, $type, $msg, $rows, [
                trans('bot.channel_poster_ssig_text_hint_yes'),
                trans('bot.channel_poster_ssig_text_hint_settings'),
                trans('bot.channel_poster_ssig_text_hint_stop'),
            ]);
            return;
        }

        // ALWAYS_APPLY: امزا با تنظیمات هوشمند اعمال می‌شود (بدون سؤال)
        // DISABLED: امزا این بار اعمال نمی‌شود (بدون سؤال)
        if ($policy === SignatureSettingsService::APPLY_ALWAYS) {
            $stateData['signature_enabled'] = true;
            $stateData['ssig_answered'] = true;
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
        } elseif ($policy === SignatureSettingsService::APPLY_DISABLED) {
            $stateData['signature_enabled'] = false;
            $stateData['ssig_answered'] = true;
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
        }

        $hasLinks = SignatureSettingsService::anyHasLink($destinations);
        $pendingCount = $this->service->countPending($botItem->id, $tagKey);
        $nextSlot = $this->service->getNextSlot($botItem->id, $tagKey);
        $nextTimeLabel = $nextSlot->setTimezone('Asia/Tehran')->format('H:i');

        $rows = [];

        // Signature option (legacy فقط — فقط وقتی هیچ مقصد هوشمندی نباشد)
        if ($policy === 'legacy' && $hasLinks && !($stateData['ssig_answered'] ?? false)) {
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

        $this->sendNativeHintIfNeeded($publisher, $chatId, $type, $msg, $rows, [
            trans('bot.channel_poster_ssig_text_hint_now'),
            trans('bot.channel_poster_ssig_text_hint_queue'),
        ]);
    }

    /**
     * برای مبدأهای native (soroush/rubika) دکمه‌ها حذف می‌شوند؛ راهنمای متنی
     * کوتاه اضافه می‌کنیم تا کاربر بداند چه تایپ کند. (CP-SMART-SIGNATURE-SETTINGS)
     */
    private function sendNativeHintIfNeeded(
        ChannelPosterPublisher $publisher,
        string $chatId,
        string $type,
        string $msg,
        array $rows,
        array $textHints
    ): void {
        if (in_array($type, ['soroush', 'rubika'], true) && $textHints !== []) {
            $msg .= "\n\n" . trans('bot.channel_poster_platform_native_hint') . "\n" . implode("\n", $textHints);
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
        $fileName = $state->getData('file_name');
        $mimeType = $state->getData('mime_type');
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

        // متن پایه — امضای لینک‌دار به‌صورت per-destination در حلقه‌ی
        // انتشار فوری اضافه می‌شود؛ در حالت صف‌بانی، صف‌بانی خودش اضافه می‌کند
        // (جلوگیری از دوبرابر شدن امضا).
        $finalText = is_string($text) ? $text : null;

        // Enqueue instead of immediate publish
        if ($enqueue) {
            $nextSlot = $this->service->getNextSlot($botItem->id, $tagKey);
            // CP-SMART-SIGNATURE-SETTINGS: انتخاب random یک‌بار اینجا فریز می‌شود
            // (ثبات امزا در retry صف)
            $frozenSelection = null;
            if ($signatureEnabled) {
                $frozenSelection = $this->service->freezeSignatureSelections($botItem->id, $tagKey);
            }
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
                is_string($mediaPath) && $mediaPath !== '' ? $mediaPath : null,
                is_string($fileName) && $fileName !== '' ? $fileName : null,
                is_string($mimeType) && $mimeType !== '' ? $mimeType : null,
                $frozenSelection !== [] ? $frozenSelection : null
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
        // CP-SMART-SIGNATURE-SETTINGS: random یک‌بار فریز می‌شود تا همهٔ مقصدها
        // انتخاب یکسان/ثابت ببینند (برای retry از صف استفاده می‌شود)
        $frozenSelection = $signatureEnabled
            ? $this->service->freezeSignatureSelections($botItem->id, $tagKey)
            : null;
        foreach ($destinations as $destination) {
            // امضای لینک‌دارِ مخصوص همین پلتفرم + parse_mode آن (+ دکمهٔ button-type)
            $destText = $finalText;
            $destParseMode = null;
            $destReplyMarkup = null;
            if ($signatureEnabled && $destText !== null) {
                // تنظیمات هوشمندِ مقصد → موتور سیاست؛ وگرنه legacy
                if (SignatureSettingsService::isSmart($destination)) {
                    $sig = $this->service->buildSignatureForDestination($destination, $frozenSelection);
                } else {
                    $sig = $this->service->buildSignature($botItem->id, $tagKey, $destination->platform);
                }
                if ($sig['text'] !== '' || (is_array($sig['button'] ?? null) && $sig['button'] !== [])) {
                    // guard طول caption (فقط رسانه) — هرگز متن اصلی کوتاه نمی‌شود
                    if ($contentType !== 'text'
                        && $destText !== ''
                        && !SignatureRenderer::fitsCaption(mb_strlen($destText), mb_strlen($sig['text'] ?? ''))
                    ) {
                        Log::warning('[ChannelPoster] Signature dropped: caption would exceed limit', [
                            'platform' => $destination->platform,
                            'base_length' => mb_strlen($destText),
                            'signature_length' => mb_strlen($sig['text'] ?? ''),
                        ]);
                    } else {
                        $destText .= $sig['text'];
                        $destParseMode = $sig['parse_mode'];
                        $destReplyMarkup = $sig['button'] ?? null;
                    }
                }
            }

            $result = $publisher->publish(
                $destination->channel_chat_id,
                $contentType,
                $destText,
                is_string($fileId) ? $fileId : null,
                $destination->platform,
                $destination->bot_token,
                $localPath,
                is_string($fileName) && $fileName !== '' ? $fileName : null,
                is_string($mimeType) && $mimeType !== '' ? $mimeType : null,
                $destParseMode,
                $destReplyMarkup
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

    private function sendPlatformKeyboard(ChannelPosterPublisher $publisher, string $chatId, string $type, string $tag): void
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
            [[
                'text' => trans('bot.channel_poster_btn_rubika'),
                'callback_data' => 'cp:plat:rubika',
            ]],
        ];
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ask_platform', ['tag' => $tag]) . $this->nativeInputHint($type), $rows);
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
            ChannelPosterDestination::PLATFORM_RUBIKA => trans('bot.channel_poster_sig_rubika'),
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

        // ویزارد جدید: لینک → برچسب → تأیید. ذخیره در دیتابیس تا مرحلهٔ تأیید
        // (updateSignatureSettings) به‌تأخیر انجام می‌شود تا «خیر» بدون اثر باقی بماند.
        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_SIGN_LABEL, [
            'destination_id' => $destId,
            'tag' => (string) ($stateData['tag'] ?? ''),
            'link' => $link,
        ]);
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_sign_ask_label', [
            'link' => $link,
            'none' => trans('bot.channel_poster_sign_label_none'),
        ]));
    }

    private function handleSignLabel(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $label,
        array $stateData
    ): void {
        $destId = (int) ($stateData['destination_id'] ?? 0);
        $link = (string) ($stateData['link'] ?? '');
        if ($destId <= 0 || $link === '') {
            $this->clearState($botItem, $chatId, $type);
            return;
        }

        $label = trim($label);
        if ($label === '' || $label === trans('bot.channel_poster_sign_label_none')) {
            $label = null; // بدون برچسب کاربر → برچسب پیش‌فرض پلتفرم در زمان رندر
        } else {
            $label = mb_substr($label, 0, 255);
        }

        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_SIGN_CONFIRM, [
            'destination_id' => $destId,
            'tag' => (string) ($stateData['tag'] ?? ''),
            'link' => $link,
            'label' => $label,
        ]);
        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_sign_ask_confirm', [
            'link' => $link,
            'label' => $label !== null ? $label : trans('bot.channel_poster_sign_label_default_hint'),
        ]), [
            [
                ['text' => trans('bot.channel_poster_sign_conf_yes'), 'callback_data' => 'cp:signconf:yes'],
            ],
            [
                ['text' => trans('bot.channel_poster_sign_conf_no'), 'callback_data' => 'cp:signconf:no'],
            ],
            [
                ['text' => trans('bot.channel_poster_sign_conf_back'), 'callback_data' => 'cp:signconf:back'],
            ],
        ]);
    }

    private function handleSignConfirmCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $data
    ): void {
        $state = $this->getState($botItem, $chatId, $type);
        if (!$state || $state->state !== self::STATE_AWAITING_SIGN_CONFIRM) {
            return;
        }
        $choice = substr($data, strlen('cp:signconf:'));
        $d = $state->data ?? [];

        if ($choice === 'back') {
            // بازگشت به مرحلهٔ برچسب (لینک حفظ می‌شود)
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_SIGN_LABEL, [
                'destination_id' => (int) ($d['destination_id'] ?? 0),
                'tag' => (string) ($d['tag'] ?? ''),
                'link' => (string) ($d['link'] ?? ''),
            ]);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_sign_ask_label', [
                'link' => (string) ($d['link'] ?? ''),
                'none' => trans('bot.channel_poster_sign_label_none'),
            ]));
            return;
        }

        $this->saveSignConfirmation($publisher, $botItem, $type, $chatId, $d, $choice === 'yes');
    }

    /**
     * معادل متنی مرحلهٔ تأیید — دکمه در مبدأهای native (soroush/rubika) حذف
     * می‌شود (NativeChannelPosterPublisher)، پس کاربر می‌تواند بله/خیر/بازگشت
     * را تایپ کند (برای همهٔ مبدأها کار می‌کند).
     */
    private function handleSignConfirmText(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $text,
        array $stateData
    ): void {
        $choice = mb_strtolower(trim($text));
        $yes = in_array($choice, ['بله', 'بره', 'yes', 'y'], true);
        $no = in_array($choice, ['خیر', 'نه', 'no', 'n'], true);
        $back = in_array($choice, ['بازگشت', 'back'], true);

        if (!$yes && !$no && !$back) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_sign_confirm_invalid'));
            return;
        }

        if ($back) {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_SIGN_LABEL, [
                'destination_id' => (int) ($stateData['destination_id'] ?? 0),
                'tag' => (string) ($stateData['tag'] ?? ''),
                'link' => (string) ($stateData['link'] ?? ''),
            ]);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_sign_ask_label', [
                'link' => (string) ($stateData['link'] ?? ''),
                'none' => trans('bot.channel_poster_sign_label_none'),
            ]));
            return;
        }

        $this->saveSignConfirmation($publisher, $botItem, $type, $chatId, $stateData, $yes);
    }

    /**
     * ذخیرهٔ نهایی تنظیمات امزا (بله/خیر) — نقطهٔ تنها ورودی به دیتابیس در
     * ویزارد جدید. type همواره inline است؛ انتخاب نوع امزا جزوی از این
     * مأموریت نیست ولی در مدل/مایگریشن پشتیبانی می‌شود (G4).
     */
    private function saveSignConfirmation(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $d,
        bool $enabled
    ): void {
        $destId = (int) ($d['destination_id'] ?? 0);
        $link = (string) ($d['link'] ?? '');
        if ($destId <= 0 || $link === '') {
            $this->clearState($botItem, $chatId, $type);
            return;
        }

        $this->service->updateSignatureSettings($destId, $link, $d['label'] ?? null, SignatureRenderer::TYPE_INLINE, $enabled);
        $this->clearState($botItem, $chatId, $type);

        $key = $enabled ? 'channel_poster_sign_enabled' : 'channel_poster_sign_disabled';
        $publisher->sendPrivateMessage($chatId, trans('bot.' . $key, ['link' => $link]));
    }

    // ======================================================================
    // === CP-SMART-SIGNATURE-SETTINGS: ویزارد تنظیمات هوشمند امضا ===
    // سیاست انتخاب لینک → (چیدمان شرطی) → پیش‌نمایش → ذخیره.
    // همهٔ مراحل هم از دکمه و هم از تایپ متنی (سروش/روبیکا) قابل عبورند.
    // ======================================================================

    /**
     * شروع ویزارد. $pending = دادهٔ وضعیتِ مرحلهٔ انتخاب زمان ارسال (وقتی از
     * «تغییر تنظیمات» در جریان ارسال وارد شده‌ایم) — برای بازگشت بعد از ذخیره.
     */
    private function startSmartSigWizard(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $pending = []
    ): void {
        $groups = $this->service->listTagGroups($botItem->id);
        if ($groups === []) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_no_tag'));
            return;
        }

        $data = ['pending' => $pending];

        if (count($groups) === 1) {
            $this->showSmartSigChannels($publisher, $botItem, $type, $chatId, $groups[0]['key'], $data);
            return;
        }

        $rows = [];
        foreach ($groups as $index => $group) {
            $rows[] = [['text' => $group['label'], 'callback_data' => 'cp:ssigt:' . $index]];
        }
        $data['tag_keys'] = array_map(fn (array $g) => $g['key'], $groups);
        $this->setState($botItem, $chatId, $type, self::STATE_SSIG_TAG, $data);
        $this->sendNativeHintIfNeeded($publisher, $chatId, $type, trans('bot.channel_poster_ssig_ask_tag'), $rows, [
            trans('bot.channel_poster_ssig_text_hint_number'),
        ]);
    }

    private function handleSmartSigTagCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        int $index
    ): void {
        $state = $this->getState($botItem, $chatId, $type);
        $keys = $state?->getData('tag_keys', []) ?? [];
        $tag = is_array($keys) ? (string) ($keys[$index] ?? '') : '';
        if ($tag === '') {
            $groups = $this->service->listTagGroups($botItem->id);
            $tag = $groups[$index]['key'] ?? '';
        }
        if ($tag === '') {
            return;
        }
        $this->showSmartSigChannels($publisher, $botItem, $type, $chatId, $tag, $state?->data ?? []);
    }

    /**
     * انتخاب کانال/مقصد — با نشانگر لینک (✅/❌) مثل ویزارد /sign.
     */
    private function showSmartSigChannels(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $tag,
        array $data = []
    ): void {
        $destinations = $this->service->resolveByTag($botItem->id, $tag);
        if ($destinations->isEmpty()) {
            $this->clearState($botItem, $chatId, $type);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_no_channels'));
            return;
        }

        $platformLabels = [
            ChannelPosterDestination::PLATFORM_BALE => trans('bot.channel_poster_sig_bale'),
            ChannelPosterDestination::PLATFORM_TELEGRAM => trans('bot.channel_poster_sig_telegram'),
            ChannelPosterDestination::PLATFORM_EITAA => trans('bot.channel_poster_sig_eitaa'),
            ChannelPosterDestination::PLATFORM_SOROUSH => trans('bot.channel_poster_sig_soroush'),
            ChannelPosterDestination::PLATFORM_RUBIKA => trans('bot.channel_poster_sig_rubika'),
        ];

        $rows = [];
        $destIds = [];
        foreach ($destinations as $dest) {
            $label = $platformLabels[$dest->platform] ?? $dest->platform;
            $title = $dest->channel_title ?? $dest->channel_chat_id;
            $hasLink = !empty($dest->channel_link) ? '✅' : '❌';
            $rows[] = [['text' => "{$label} — {$title} {$hasLink}", 'callback_data' => 'cp:ssigc:' . $dest->id]];
            $destIds[] = $dest->id;
        }

        $data = array_merge($data, ['tag' => $tag, 'dest_ids' => $destIds]);
        $this->setState($botItem, $chatId, $type, self::STATE_SSIG_CHANNEL, $data);
        $this->sendNativeHintIfNeeded($publisher, $chatId, $type, trans('bot.channel_poster_ssig_pick_channel'), $rows, [
            trans('bot.channel_poster_ssig_text_hint_number'),
        ]);
    }

    private function handleSmartSigChannelCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        int $destId
    ): void {
        $dest = ChannelPosterDestination::find($destId);
        if (!$dest || $dest->bot_id !== $botItem->id) {
            return;
        }
        $state = $this->getState($botItem, $chatId, $type);
        $data = $state?->data ?? [];
        $data['destination_id'] = $destId;
        $data['tag'] = (string) ($data['tag'] ?? '');
        // تنظیمات ذخیره‌شدهٔ قبلی به‌عنوان پیش‌فرض (state preservation)
        $saved = SignatureSettingsService::normalize(is_array($dest->signature_settings) ? $dest->signature_settings : null);
        $this->showSmartSigPolicy($publisher, $botItem, $type, $chatId, $data, $saved);
    }

    /**
     * مرحلهٔ سیاست انتخاب لینک.
     *
     * @param array|null $savedSettings تنظیمات قبلی ذخیره‌شده (پیش‌فرض‌گذاری)
     */
    private function showSmartSigPolicy(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $data,
        ?array $savedSettings = null
    ): void {
        $policy = (string) ($savedSettings['link_selection_policy'] ?? SignatureSettingsService::POLICY_SELF_ONLY);

        $rows = [
            [['text' => trans('bot.channel_poster_ssig_policy_self'), 'callback_data' => 'cp:ssigpol:' . SignatureSettingsService::POLICY_SELF_ONLY]],
            [['text' => trans('bot.channel_poster_ssig_policy_all'), 'callback_data' => 'cp:ssigpol:' . SignatureSettingsService::POLICY_ALL_CHANNELS]],
            [['text' => trans('bot.channel_poster_ssig_policy_exclude'), 'callback_data' => 'cp:ssigpol:' . SignatureSettingsService::POLICY_EXCLUDE_SELF]],
            [['text' => trans('bot.channel_poster_ssig_policy_custom'), 'callback_data' => 'cp:ssigpol:' . SignatureSettingsService::POLICY_CUSTOM]],
            [['text' => trans('bot.channel_poster_ssig_policy_random'), 'callback_data' => 'cp:ssigpol:' . SignatureSettingsService::POLICY_RANDOM]],
            [['text' => trans('bot.channel_poster_ssig_policy_self_plus_eitaa'), 'callback_data' => 'cp:ssigpol:' . SignatureSettingsService::POLICY_SELF_ONLY_PLUS_EITAA_ON_TELEGRAM]],
            [['text' => trans('bot.channel_poster_ssig_back'), 'callback_data' => 'cp:ssigbk']],
            [['text' => trans('bot.channel_poster_ssig_cancel'), 'callback_data' => 'cp:ssigcancel']],
        ];

        $this->setState($botItem, $chatId, $type, self::STATE_SSIG_POLICY, $data);
        $this->sendNativeHintIfNeeded($publisher, $chatId, $type, trans('bot.channel_poster_ssig_ask_policy'), $rows, [
            trans('bot.channel_poster_ssig_text_hint_numbers_6'),
        ]);
    }

    private function handleSmartSigPolicyCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $policy
    ): void {
        if (!in_array($policy, SignatureSettingsService::policies(), true)) {
            return;
        }
        $state = $this->getState($botItem, $chatId, $type);
        if (!$state || $state->state !== self::STATE_SSIG_POLICY) {
            return;
        }
        $data = $state->data ?? [];
        $data['policy'] = $policy;
        unset($data['layout'], $data['separator']);

        if ($policy === SignatureSettingsService::POLICY_CUSTOM) {
            $data['custom_picks'] = [];
            $this->showSmartSigCustomPick($publisher, $botItem, $type, $chatId, $data);
            return;
        }
        if ($policy === SignatureSettingsService::POLICY_RANDOM) {
            $data['random_count'] = (int) ($data['random_count'] ?? 2);
            $this->showSmartSigRandomCount($publisher, $botItem, $type, $chatId, $data);
            return;
        }
        $this->afterPolicy($publisher, $botItem, $type, $chatId, $data);
    }

    /**
     * مرحلهٔ انتخاب مقصدهای custom (تک‌چند) + دکمهٔ پایان.
     */
    private function showSmartSigCustomPick(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $data
    ): void {
        $destId = (int) ($data['destination_id'] ?? 0);
        $tag = (string) ($data['tag'] ?? '');
        // لیست مقصدهای هم‌تگ (مقصد خود جداست؛ فقط کانال‌های دیگر قابل انتخاب)
        $tagDestinations = $this->service->resolveByTag($botItem->id, $tag);
        $picks = array_values(array_unique(array_map('intval', (array) ($data['custom_picks'] ?? []))));

        $platformLabels = [
            ChannelPosterDestination::PLATFORM_BALE => trans('bot.channel_poster_sig_bale'),
            ChannelPosterDestination::PLATFORM_TELEGRAM => trans('bot.channel_poster_sig_telegram'),
            ChannelPosterDestination::PLATFORM_EITAA => trans('bot.channel_poster_sig_eitaa'),
            ChannelPosterDestination::PLATFORM_SOROUSH => trans('bot.channel_poster_sig_soroush'),
            ChannelPosterDestination::PLATFORM_RUBIKA => trans('bot.channel_poster_sig_rubika'),
        ];

        $rows = [];
        foreach ($tagDestinations as $dest) {
            if ($destId > 0 && $dest->id === $destId) {
                continue; // خود مقصد جداست؛ فقط کانال‌های دیگر قابل انتخاب
            }
            $label = $platformLabels[$dest->platform] ?? $dest->platform;
            $title = $dest->channel_title ?? $dest->channel_chat_id;
            $mark = in_array($dest->id, $picks, true) ? '✅' : '➕';
            $rows[] = [['text' => "{$label} — {$title} {$mark}", 'callback_data' => 'cp:ssigck:' . $dest->id]];
        }
        $rows[] = [['text' => trans('bot.channel_poster_ssig_custom_done'), 'callback_data' => 'cp:ssigck:done']];
        $rows[] = [['text' => trans('bot.channel_poster_ssig_back'), 'callback_data' => 'cp:ssigbk']];
        $rows[] = [['text' => trans('bot.channel_poster_ssig_cancel'), 'callback_data' => 'cp:ssigcancel']];

        $this->setState($botItem, $chatId, $type, self::STATE_SSIG_CUSTOM_PICK, $data);
        $this->sendNativeHintIfNeeded($publisher, $chatId, $type, trans('bot.channel_poster_ssig_ask_custom'), $rows, [
            trans('bot.channel_poster_ssig_text_hint_numbers'),
        ]);
    }

    /**
     * $rest: شمارهٔ مقصد یا 'done' یا عددِ فاصلهٔ شماره‌ها (ورودی متنی).
     */
    private function handleSmartSigCustomPick(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $rest
    ): void {
        $state = $this->getState($botItem, $chatId, $type);
        if (!$state || $state->state !== self::STATE_SSIG_CUSTOM_PICK) {
            return;
        }
        $data = $state->data ?? [];
        $destId = (int) ($data['destination_id'] ?? 0);
        $tag = (string) ($data['tag'] ?? '');
        $picks = array_values(array_unique(array_map('intval', (array) ($data['custom_picks'] ?? []))));

        if ($rest === 'done') {
            if ($picks === []) {
                $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_custom_empty'));
                $this->showSmartSigCustomPick($publisher, $botItem, $type, $chatId, $data);
                return;
            }
            $data['custom_picks'] = $picks;
            $this->afterPolicy($publisher, $botItem, $type, $chatId, $data);
            return;
        }

        $index = (int) $rest;
        $tagDestinations = $this->service->resolveByTag($botItem->id, $tag);
        $candidates = $tagDestinations->filter(
            fn (ChannelPosterDestination $d) => $destId <= 0 || $d->id !== $destId
        )->values();

        $dest = $candidates->first(fn (ChannelPosterDestination $d) => $d->id === $index);
        if (!$dest) {
            $dest = $candidates->get($index - 1) ?? null;
        }
        if (!$dest) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_invalid_number'));
            return;
        }

        $id = $dest->id;
        if (in_array($id, $picks, true)) {
            $picks = array_values(array_diff($picks, [$id]));
        } else {
            $picks[] = $id;
        }
        $data['custom_picks'] = $picks;
        $this->showSmartSigCustomPick($publisher, $botItem, $type, $chatId, $data);
    }

    /**
     * مرحلهٔ تعداد random (حداکثر ۵۰).
     */
    private function showSmartSigRandomCount(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $data
    ): void {
        $tag = (string) ($data['tag'] ?? '');
        $eligibleCount = count(SignatureSettingsService::eligibleDestinations($this->service->resolveByTag($botItem->id, $tag)));

        $rows = [];
        foreach ([1, 2, 3] as $n) {
            $rows[] = [['text' => trans('bot.channel_poster_ssig_count_n', ['n' => $n]), 'callback_data' => 'cp:ssigrc:' . $n]];
        }
        $rows[] = [['text' => trans('bot.channel_poster_ssig_back'), 'callback_data' => 'cp:ssigbk']];
        $rows[] = [['text' => trans('bot.channel_poster_ssig_cancel'), 'callback_data' => 'cp:ssigcancel']];
        $this->setState($botItem, $chatId, $type, self::STATE_SSIG_RANDOM_COUNT, $data);
        $this->sendNativeHintIfNeeded($publisher, $chatId, $type, trans('bot.channel_poster_ssig_ask_count', ['max' => $eligibleCount]), $rows, [
            trans('bot.channel_poster_ssig_text_hint_count'),
        ]);
    }

    private function handleSmartSigRandomCount(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        int $count
    ): void {
        $state = $this->getState($botItem, $chatId, $type);
        if (!$state || $state->state !== self::STATE_SSIG_RANDOM_COUNT) {
            return;
        }
        if ($count < 1 || $count > 50) {
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_invalid_count'));
            return;
        }
        $data = $state->data ?? [];
        $data['random_count'] = $count;
        $this->afterPolicy($publisher, $botItem, $type, $chatId, $data);
    }

    /**
     * بعد از تعیین سیاست: چیدمان (فقط اگر ممکن است بیش از یک لینک شود) → اعمال → پیش‌نمایش.
     */
    private function afterPolicy(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $data
    ): void {
        $policy = (string) ($data['policy'] ?? SignatureSettingsService::POLICY_SELF_ONLY);
        $tag = (string) ($data['tag'] ?? '');
        $eligibleCount = count(SignatureSettingsService::eligibleDestinations($this->service->resolveByTag($botItem->id, $tag)));

        $picksArg = null;
        $finalLinkCount = null;
        if ($policy === SignatureSettingsService::POLICY_CUSTOM) {
            $picksArg = array_map('intval', (array) ($data['custom_picks'] ?? []));
        } elseif ($policy === SignatureSettingsService::POLICY_RANDOM) {
            $picksArg = ['random_count' => (int) ($data['random_count'] ?? 2)];
        } elseif ($policy === SignatureSettingsService::POLICY_SELF_ONLY_PLUS_EITAA_ON_TELEGRAM) {
            // تعداد لینک نهایی (خود + ایتا) دقیقاً با همان موتور resolveSelected
            // و روی مقصد واقعی — سؤال چیدمان فقط وقتی واقعاً ۲+ لینک می‌شود.
            $finalLinkCount = $this->selfPlusEitaaFinalCount(
                (int) ($data['destination_id'] ?? 0),
                $botItem,
                $tag,
                $policy
            );
        }

        if (SignatureSettingsService::layoutQuestionNeeded($policy, $eligibleCount, $picksArg, $finalLinkCount)) {
            $this->showSmartSigLayout($publisher, $botItem, $type, $chatId, $data);
            return;
        }

        $data['layout'] = SignatureSettingsService::LAYOUT_SINGLE_LINE;
        $this->showSmartSigApply($publisher, $botItem, $type, $chatId, $data);
    }

    /**
     * تعداد لینک نهایی سیاست self_only_plus_eitaa_on_telegram برای یک مقصد —
     * دقیقاً منطق buildSignatureForDestination (resolveByTag با fallback به 'all')
     * ولی فقط در حافظه، بدون ذخیره.
     */
    private function selfPlusEitaaFinalCount(int $destId, Bot $botItem, string $tag, string $policy): int
    {
        $dest = $destId > 0 ? ChannelPosterDestination::find($destId) : null;
        if (!$dest || $dest->bot_id !== $botItem->id) {
            return 0;
        }
        $tagDestinations = $this->service->resolveByTag($botItem->id, $tag);
        if ($tagDestinations->isEmpty()) {
            $tagDestinations = $this->service->resolveDestinations($botItem->id, 'all');
        }
        $dest->signature_settings = SignatureSettingsService::normalize([
            'link_selection_policy' => $policy,
        ]) ?? SignatureSettingsService::defaults();

        return count(SignatureSettingsService::resolveSelected($dest, $tagDestinations));
    }

    /**
     * مرحلهٔ چیدمان (فقط وقتی امزا می‌تواند چند لینک شود).
     */
    private function showSmartSigLayout(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $data
    ): void {
        $rows = [
            [
                ['text' => trans('bot.channel_poster_ssig_layout_single'), 'callback_data' => 'cp:ssiglay:' . SignatureSettingsService::LAYOUT_SINGLE_LINE],
                ['text' => trans('bot.channel_poster_ssig_layout_multi'), 'callback_data' => 'cp:ssiglay:' . SignatureSettingsService::LAYOUT_MULTI_LINE],
            ],
            [['text' => trans('bot.channel_poster_ssig_back'), 'callback_data' => 'cp:ssigbk']],
            [['text' => trans('bot.channel_poster_ssig_cancel'), 'callback_data' => 'cp:ssigcancel']],
        ];
        $this->setState($botItem, $chatId, $type, self::STATE_SSIG_LAYOUT, $data);
        $this->sendNativeHintIfNeeded($publisher, $chatId, $type, trans('bot.channel_poster_ssig_ask_layout'), $rows, [
            trans('bot.channel_poster_ssig_text_hint_numbers_2'),
        ]);
    }

    private function handleSmartSigLayoutCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $layout
    ): void {
        if (!in_array($layout, SignatureSettingsService::layouts(), true)) {
            return;
        }
        $state = $this->getState($botItem, $chatId, $type);
        if (!$state || $state->state !== self::STATE_SSIG_LAYOUT) {
            return;
        }
        $data = $state->data ?? [];
        $data['layout'] = $layout;
        if ($layout === SignatureSettingsService::LAYOUT_SINGLE_LINE) {
            $data['separator'] = SignatureSettingsService::SEPARATOR_DEFAULT;
            $this->showSmartSigSeparator($publisher, $botItem, $type, $chatId, $data);
            return;
        }
        $this->showSmartSigApply($publisher, $botItem, $type, $chatId, $data);
    }

    /**
     * مرحلهٔ جداکنندهٔ single_line (حداکثر ۱۶ نویسه).
     */
    private function showSmartSigSeparator(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $data
    ): void {
        $rows = [
            [['text' => trans('bot.channel_poster_ssig_back'), 'callback_data' => 'cp:ssigbk']],
            [['text' => trans('bot.channel_poster_ssig_cancel'), 'callback_data' => 'cp:ssigcancel']],
        ];
        $this->setState($botItem, $chatId, $type, self::STATE_SSIG_SEPARATOR, $data);
        $this->sendNativeHintIfNeeded($publisher, $chatId, $type, trans('bot.channel_poster_ssig_ask_separator', [
            'current' => (string) ($data['separator'] ?? SignatureSettingsService::SEPARATOR_DEFAULT),
        ]), $rows, [
            trans('bot.channel_poster_ssig_text_hint_separator'),
        ]);
    }

    /**
     * مرحلهٔ سیاست اعمال امزا.
     */
    private function showSmartSigApply(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $data
    ): void {
        $rows = [
            [['text' => trans('bot.channel_poster_ssig_apply_always'), 'callback_data' => 'cp:ssigap:' . SignatureSettingsService::APPLY_ALWAYS]],
            [['text' => trans('bot.channel_poster_ssig_apply_ask'), 'callback_data' => 'cp:ssigap:' . SignatureSettingsService::APPLY_ASK_EVERY_POST]],
            [['text' => trans('bot.channel_poster_ssig_apply_disabled'), 'callback_data' => 'cp:ssigap:' . SignatureSettingsService::APPLY_DISABLED]],
            [['text' => trans('bot.channel_poster_ssig_back'), 'callback_data' => 'cp:ssigbk']],
            [['text' => trans('bot.channel_poster_ssig_cancel'), 'callback_data' => 'cp:ssigcancel']],
        ];
        $this->setState($botItem, $chatId, $type, self::STATE_SSIG_APPLY, $data);
        $this->sendNativeHintIfNeeded($publisher, $chatId, $type, trans('bot.channel_poster_ssig_ask_apply'), $rows, [
            trans('bot.channel_poster_ssig_text_hint_numbers_3'),
        ]);
    }

    private function handleSmartSigApplyCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $policy
    ): void {
        if (!in_array($policy, SignatureSettingsService::applicationPolicies(), true)) {
            return;
        }
        $state = $this->getState($botItem, $chatId, $type);
        if (!$state || $state->state !== self::STATE_SSIG_APPLY) {
            return;
        }
        $data = $state->data ?? [];
        $data['apply'] = $policy;
        $this->showSmartSigPreview($publisher, $botItem, $type, $chatId, $data);
    }

    /**
     * مرحلهٔ پیش‌نمایش + تأیید نهایی (فقط با «بله» ذخیره می‌شود).
     */
    private function showSmartSigPreview(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $data
    ): void {
        $destId = (int) ($data['destination_id'] ?? 0);
        $tag = (string) ($data['tag'] ?? '');
        $settings = $this->smartSigDraftSettings($data);
        $dest = $destId > 0 ? ChannelPosterDestination::find($destId) : null;

        $preview = '';
        if ($dest) {
            $dest->signature_settings = $settings;
            $tagDestinations = $this->service->resolveByTag($botItem->id, $tag);
            $selected = SignatureSettingsService::resolveSelected($dest, $tagDestinations);
            $preview = SignatureSettingsService::buildPreview($selected, $settings);
        }

        $rows = [
            [
                ['text' => trans('bot.channel_poster_ssig_confirm_save'), 'callback_data' => 'cp:ssigpv:yes'],
                ['text' => trans('bot.channel_poster_ssig_confirm_no'), 'callback_data' => 'cp:ssigpv:no'],
            ],
            [['text' => trans('bot.channel_poster_ssig_cancel'), 'callback_data' => 'cp:ssigcancel']],
        ];
        $this->setState($botItem, $chatId, $type, self::STATE_SSIG_PREVIEW, $data);
        $this->sendNativeHintIfNeeded($publisher, $chatId, $type, trans('bot.channel_poster_ssig_preview', [
            'preview' => $preview !== '' ? $preview : trans('bot.channel_poster_ssig_preview_empty'),
        ]), $rows, [
            trans('bot.channel_poster_ssig_text_hint_yes_no'),
        ]);
    }

    private function handleSmartSigPreviewCallback(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        bool $confirm
    ): void {
        $state = $this->getState($botItem, $chatId, $type);
        if (!$state || $state->state !== self::STATE_SSIG_PREVIEW) {
            return;
        }
        if (!$confirm) {
            // خیر = بدون ذخیره (تنظیمات پیشین دست‌نخورده می‌ماند)
            $this->finishSmartSigWizard($publisher, $botItem, $type, $chatId, false);
            return;
        }
        $this->saveSmartSigSettings($publisher, $botItem, $type, $chatId, $state->data ?? []);
    }

    private function handleSmartSigBack(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId
    ): void {
        $state = $this->getState($botItem, $chatId, $type);
        if (!$state || !str_starts_with($state->state, 'cp_ssig_')) {
            return;
        }
        $data = $state->data ?? [];
        $destId = (int) ($data['destination_id'] ?? 0);

        match ($state->state) {
            self::STATE_SSIG_CUSTOM_PICK => $this->showSmartSigPolicy($publisher, $botItem, $type, $chatId, $data),
            self::STATE_SSIG_RANDOM_COUNT => $this->showSmartSigPolicy($publisher, $botItem, $type, $chatId, $data),
            self::STATE_SSIG_POLICY => $destId > 0
                ? $this->showSmartSigChannels($publisher, $botItem, $type, $chatId, (string) ($data['tag'] ?? ''), $data)
                : $this->startSmartSigWizard($publisher, $botItem, $type, $chatId, (array) ($data['pending'] ?? [])),
            self::STATE_SSIG_CHANNEL => $this->startSmartSigWizard($publisher, $botItem, $type, $chatId, (array) ($data['pending'] ?? [])),
            self::STATE_SSIG_SEPARATOR => $this->showSmartSigLayout($publisher, $botItem, $type, $chatId, $data),
            self::STATE_SSIG_APPLY => $this->afterPolicy($publisher, $botItem, $type, $chatId, $data),
            self::STATE_SSIG_PREVIEW => $this->showSmartSigApply($publisher, $botItem, $type, $chatId, $data),
            default => $this->finishSmartSigWizard($publisher, $botItem, $type, $chatId, false),
        };
    }

    /**
     * ذخیرهٔ نهایی و پایان ویزارد.
     *
     * @param array $d دادهٔ وضعیتِ ویزارد (شامل pending در صورت ورود از «تغییر تنظیمات»)
     */
    private function saveSmartSigSettings(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        array $d
    ): void {
        $destId = (int) ($d['destination_id'] ?? 0);
        $dest = $destId > 0 ? ChannelPosterDestination::find($destId) : null;
        if (!$dest || $dest->bot_id !== $botItem->id) {
            $this->clearState($botItem, $chatId, $type);
            return;
        }

        $settings = $this->smartSigDraftSettings($d);
        $this->service->saveSmartSignatureSettings($destId, $settings);
        $this->finishSmartSigWizard($publisher, $botItem, $type, $chatId, true);
    }

    /**
     * ساخت آرایهٔ تنظیمات از دادهٔ ویزارد (همان‌طور که در دیتابیس ذخیره می‌شود).
     */
    private function smartSigDraftSettings(array $d): array {
        $apply = (string) ($d['apply'] ?? SignatureSettingsService::APPLY_ALWAYS);
        $settings = [
            'enabled' => $apply !== SignatureSettingsService::APPLY_DISABLED,
            'link_selection_policy' => (string) ($d['policy'] ?? SignatureSettingsService::POLICY_SELF_ONLY),
            'layout' => (string) ($d['layout'] ?? SignatureSettingsService::LAYOUT_SINGLE_LINE),
            'separator' => (string) ($d['separator'] ?? SignatureSettingsService::SEPARATOR_DEFAULT),
            'selected_destinations' => array_values(array_map('intval', (array) ($d['custom_picks'] ?? []))),
            'random_count' => (int) ($d['random_count'] ?? 2),
            'application_policy' => $apply,
        ];
        return SignatureSettingsService::normalize($settings) ?? SignatureSettingsService::defaults();
    }

    /**
     * پایان ویزارد: ذخیره‌شده یا لغوشده. اگر مطلب معوق داشت (ورود از
     * «تغییر تنظیمات» در جریان ارسال) به انتخاب زمان/امضا بازمی‌گردد.
     */
    private function finishSmartSigWizard(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        bool $saved
    ): void {
        $state = $this->getState($botItem, $chatId, $type);
        $data = $state?->data ?? [];
        $pending = (array) ($data['pending'] ?? []);

        $this->clearState($botItem, $chatId, $type);

        if ($pending !== []) {
            $tagKey = (string) ($pending['selected_tag'] ?? '');
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $pending);
            // اگر امضا ذخیره شد و سیاست still ask_every_post است، سؤال دوباره نمی‌آید
            // چون دادهٔ معوقِ قبلی «پاسخ‌داده» بوده (ssig_answered در $pending).
            $this->askSignatureAndQueue($publisher, $botItem, $type, $chatId, $tagKey);
            return;
        }

        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_' . ($saved ? 'saved' : 'cancelled')));
    }

    /**
     * معادل متنیِ همهٔ مراحل ویزارد — دکمه در مبدأهای native (soroush/rubika)
     * حذف می‌شود (NativeChannelPosterPublisher). برای همهٔ مبدأها مجاز است.
     */
    private function handleSmartSigText(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $text,
        array $d
    ): void {
        $state = $this->getState($botItem, $chatId, $type);
        $raw = trim($text);
        $choice = mb_strtolower($raw);
        $destId = (int) ($d['destination_id'] ?? 0);

        // بازگشت/انصراف — همهٔ مراحل (دکمه در مبدأهای native حذف می‌شود)
        if (in_array($choice, ['بازگشت', 'back', '❮'], true)) {
            $this->handleSmartSigBack($publisher, $botItem, $type, $chatId);
            return;
        }
        if (in_array($choice, ['انصراف', 'لغو', 'cancel'], true)) {
            $this->finishSmartSigWizard($publisher, $botItem, $type, $chatId, false);
            return;
        }

        if ($state && $state->state === self::STATE_SSIG_TAG) {
            $keys = $d['tag_keys'] ?? [];
            if ($raw !== '' && ctype_digit($raw) && isset($keys[(int) $raw - 1])) {
                $this->handleSmartSigTagCallback($publisher, $botItem, $type, $chatId, (int) $raw - 1);
                return;
            }
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_invalid_number'));
            return;
        }

        if ($state && $state->state === self::STATE_SSIG_CHANNEL) {
            $ids = array_map('intval', (array) ($d['dest_ids'] ?? []));
            if ($raw !== '' && ctype_digit($raw) && isset($ids[(int) $raw - 1])) {
                $this->handleSmartSigChannelCallback($publisher, $botItem, $type, $chatId, $ids[(int) $raw - 1]);
                return;
            }
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_invalid_number'));
            return;
        }

        if ($state && $state->state === self::STATE_SSIG_POLICY) {
            $map = [
                '1' => SignatureSettingsService::POLICY_SELF_ONLY,
                '2' => SignatureSettingsService::POLICY_ALL_CHANNELS,
                '3' => SignatureSettingsService::POLICY_EXCLUDE_SELF,
                '4' => SignatureSettingsService::POLICY_CUSTOM,
                '5' => SignatureSettingsService::POLICY_RANDOM,
                '6' => SignatureSettingsService::POLICY_SELF_ONLY_PLUS_EITAA_ON_TELEGRAM,
            ];
            if (isset($map[$choice])) {
                $this->handleSmartSigPolicyCallback($publisher, $botItem, $type, $chatId, $map[$choice]);
                return;
            }
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_invalid_number'));
            return;
        }

        if ($state && $state->state === self::STATE_SSIG_CUSTOM_PICK) {
            if (in_array($choice, ['انجام شد', 'done', 'ok', '✔', '✅'], true)) {
                $this->handleSmartSigCustomPick($publisher, $botItem, $type, $chatId, 'done');
                return;
            }
            if ($raw !== '' && ctype_digit($raw) && (int) $raw >= 1) {
                $this->handleSmartSigCustomPick($publisher, $botItem, $type, $chatId, $raw);
                return;
            }
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_invalid_number'));
            return;
        }

        if ($state && $state->state === self::STATE_SSIG_RANDOM_COUNT) {
            if ($raw !== '' && ctype_digit($raw) && (int) $raw >= 1 && (int) $raw <= 50) {
                $this->handleSmartSigRandomCount($publisher, $botItem, $type, $chatId, (int) $raw);
                return;
            }
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_invalid_count'));
            return;
        }

        if ($state && $state->state === self::STATE_SSIG_LAYOUT) {
            $map = [
                '1' => SignatureSettingsService::LAYOUT_SINGLE_LINE,
                '2' => SignatureSettingsService::LAYOUT_MULTI_LINE,
            ];
            if (isset($map[$choice])) {
                $this->handleSmartSigLayoutCallback($publisher, $botItem, $type, $chatId, $map[$choice]);
                return;
            }
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_invalid_number'));
            return;
        }

        if ($state && $state->state === self::STATE_SSIG_SEPARATOR) {
            $sep = $raw === '' ? SignatureSettingsService::SEPARATOR_DEFAULT : mb_substr($raw, 0, 16);
            $d2 = $d;
            $d2['separator'] = $sep;
            $this->showSmartSigApply($publisher, $botItem, $type, $chatId, $d2);
            return;
        }

        if ($state && $state->state === self::STATE_SSIG_APPLY) {
            $map = [
                '1' => SignatureSettingsService::APPLY_ALWAYS,
                '2' => SignatureSettingsService::APPLY_ASK_EVERY_POST,
                '3' => SignatureSettingsService::APPLY_DISABLED,
            ];
            if (isset($map[$choice])) {
                $this->handleSmartSigApplyCallback($publisher, $botItem, $type, $chatId, $map[$choice]);
                return;
            }
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_invalid_number'));
            return;
        }

        if ($state && $state->state === self::STATE_SSIG_PREVIEW) {
            $yes = in_array($choice, ['بله', 'بره', 'yes', 'y'], true);
            $no = in_array($choice, ['خیر', 'نه', 'no', 'n'], true);
            if ($yes) {
                $this->handleSmartSigPreviewCallback($publisher, $botItem, $type, $chatId, true);
                return;
            }
            if ($no) {
                $this->handleSmartSigPreviewCallback($publisher, $botItem, $type, $chatId, false);
                return;
            }
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_confirm_invalid'));
            return;
        }
    }

    /**
     * معادل متنیِ سؤال کوتاهِ هر-بار (ASK_EVERY_POST) — دکمه‌ها در مبدأهای
     * native (soroush/rubika) حذف می‌شوند. برای همهٔ مبدأها مجاز است.
     *
     * بله/خیر → پاسخ سؤال (و سپس انتخاب زمان ارسال)
     * تنظیمات → ویزارد (محتوای معوق حفظ می‌شود)
     * دیگر نپرس → تبدیل دائمی به always_apply
     * الان/صف → اگر سؤال هنوز بی‌پاسخ است، امزا فرضِ پیش‌فرض (خیر) می‌شود
     */
    private function handleSmartSigPostQuestionText(
        ChannelPosterPublisher $publisher,
        Bot $botItem,
        string $type,
        string $chatId,
        string $text,
        array $stateData
    ): void {
        $choice = mb_strtolower(trim($text));
        $tagKey = (string) ($stateData['selected_tag'] ?? '');

        if (in_array($choice, ['بله', 'بره', 'yes', 'y'], true)) {
            $stateData['signature_enabled'] = true;
            $stateData['ssig_answered'] = true;
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
            $this->askSignatureAndQueue($publisher, $botItem, $type, $chatId, $tagKey);
            return;
        }
        if (in_array($choice, ['خیر', 'نه', 'no', 'n'], true)) {
            $stateData['signature_enabled'] = false;
            $stateData['ssig_answered'] = true;
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
            $this->askSignatureAndQueue($publisher, $botItem, $type, $chatId, $tagKey);
            return;
        }
        if (in_array($choice, ['تنظیمات', 'settings'], true)
            || str_contains($choice, 'تنظیمات')
            || str_contains($choice, 'settings')
        ) {
            $this->startSmartSigWizard($publisher, $botItem, $type, $chatId, $stateData);
            return;
        }
        if (in_array($choice, ['دیگر نپرس', 'donotask', 'stop'], true)
            || str_contains($choice, 'نپرس')
            || str_contains($choice, 'ask')
        ) {
            $dests = $this->service->resolveByTag($botItem->id, $tagKey);
            if ($dests->isEmpty()) {
                $dests = $this->service->resolveDestinations($botItem->id, 'all');
            }
            foreach ($dests as $d) {
                if (!SignatureSettingsService::requiresPostQuestion($d)) {
                    continue;
                }
                $settings = SignatureSettingsService::normalize($d->signature_settings) ?? SignatureSettingsService::defaults();
                $settings['application_policy'] = SignatureSettingsService::APPLY_ALWAYS;
                $this->service->saveSmartSignatureSettings($d->id, $settings);
            }
            $stateData['signature_enabled'] = true;
            $stateData['ssig_answered'] = true;
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
            $this->askSignatureAndQueue($publisher, $botItem, $type, $chatId, $tagKey);
            $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_stop_saved'));
            return;
        }
        if (in_array($choice, ['الان', 'now'], true)) {
            // بدون پاسخ به سؤالِ امزا → امزا این بار اعمال نمی‌شود (موقت)
            $stateData['signature_enabled'] = false;
            $stateData['ssig_answered'] = true;
            $stateData['enqueue'] = false;
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
            $state = $this->getState($botItem, $chatId, $type);
            if ($state) {
                $this->publishPendingToTag($publisher, $botItem, $type, $chatId, $state, $tagKey);
            }
            return;
        }
        if (in_array($choice, ['صف', 'queue'], true)) {
            $stateData['signature_enabled'] = false;
            $stateData['ssig_answered'] = true;
            $stateData['enqueue'] = true;
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_DESTINATION, $stateData);
            $state = $this->getState($botItem, $chatId, $type);
            if ($state) {
                $this->publishPendingToTag($publisher, $botItem, $type, $chatId, $state, $tagKey);
            }
            return;
        }

        $publisher->sendPrivateMessage($chatId, trans('bot.channel_poster_ssig_ask_this_post') . "\n" . trans('bot.channel_poster_ssig_text_hint_yes'));
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
                // CP-SMART-SIGNATURE-SETTINGS: ورودی واحد تنظیمات هوشمند امضا (فقط مالک)
                $rows[] = [['text' => trans('bot.channel_poster_ssig_menu_entry'), 'callback_data' => 'cp:ssig']];
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
            (string) $botItem->{SyncPlatformRegistry::tokenColumn($type)},
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
