<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Helpers\RssAdminStateHelper;
use App\Http\Requests\BotRequest;
use App\Models\Bot;
use App\Services\RssFeedRegistrationService;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Telegram;

class RssAdminBotController extends Controller
{
    private const CALLBACK_ADD = 'rss_add';

    private const CALLBACK_LIST = 'rss_list';

    private const CALLBACK_BACK = 'rss_back';

    private const CALLBACK_TAG_PREFIX = 'rss_tag_';

    public function __construct(
        private readonly RssFeedRegistrationService $registrationService,
    ) {
    }

    public function index(BotRequest $request): void
    {
        Log::info('RSS Admin Bot - Webhook received', [
            'origin' => $request->input('origin'),
            'has_token' => $request->has('token'),
        ]);

        try {
            $type = $request->input('origin', 'telegram');
            $token = $request->input('token');

            if (empty($token)) {
                Log::warning('RSS Admin Bot - Missing token');

                return;
            }

            $bot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

            $update = $request->json()->all() ?? $request->all();

            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $type);

                return;
            }

            $message = $update['message'] ?? $update['edited_message'] ?? null;
            $text = $message['text'] ?? $bot->Text();
            $chatId = $message['chat']['id'] ?? $bot->ChatID();

            if ($request->has('language')) {
                App::setLocale($request->input('language'));
            } else {
                App::setLocale('fa');
            }

            if (!AdminHelper::isAdmin($chatId)) {
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.rss_admin_unauthorized'));

                return;
            }

            $botItem = $this->getBotByToken($token, $type);
            if (!$botItem || $botItem->endpoint_id !== 'webhook-rss-admin') {
                BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.rss_admin_bot_not_registered'));

                return;
            }

            if ($text === '/start' || str_starts_with((string) $text, '/start ')) {
                RssAdminStateHelper::clearState($chatId);
                $this->sendMainMenu($bot, $chatId);

                return;
            }

            if ($text !== null && trim($text) !== '') {
                $this->handleTextMessage($bot, trim($text), $chatId);
            }
        } catch (Exception $e) {
            Log::error('RSS Admin Bot - Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function getBotByToken(string $token, string $type): ?Bot
    {
        if ($type === 'bale') {
            return Bot::where('bale_bot_token', $token)->first();
        }

        return Bot::where('telegram_bot_token', $token)->first();
    }

    private function sendMainMenu(Telegram $bot, mixed $chatId): void
    {
        $rows = [
            [
                [
                    'text' => trans('bot.rss_admin_btn_add'),
                    'callback_data' => self::CALLBACK_ADD,
                ],
            ],
            [
                [
                    'text' => trans('bot.rss_admin_btn_list'),
                    'callback_data' => self::CALLBACK_LIST,
                ],
            ],
        ];

        $this->sendInlineMenu($bot, $chatId, trans('bot.rss_admin_welcome'), $rows);
    }

    private function sendTagMenu(Telegram $bot, mixed $chatId): void
    {
        $tags = $this->registrationService->getChannelTags();

        if ($tags === []) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.rss_admin_no_tags'));

            return;
        }

        $rows = [];
        foreach ($tags as $tag) {
            $rows[] = [[
                'text' => $tag['name'],
                'callback_data' => self::CALLBACK_TAG_PREFIX . $tag['name'],
            ]];
        }

        $rows[] = [[
            'text' => trans('bot.rss_admin_btn_back'),
            'callback_data' => self::CALLBACK_BACK,
        ]];

        $this->sendInlineMenu($bot, $chatId, trans('bot.rss_admin_choose_tag'), $rows);
    }

    private function sendFeedList(Telegram $bot, mixed $chatId): void
    {
        $feeds = $this->registrationService->listActiveFeeds();
        if ($feeds === []) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.rss_admin_no_feeds'));

            return;
        }

        $lines = [trans('bot.rss_admin_feed_list_header')];
        foreach ($feeds as $feed) {
            $status = $feed['is_active']
                ? trans('bot.rss_admin_feed_active')
                : trans('bot.rss_admin_feed_inactive');
            $lines[] = "#{$feed['id']} — {$feed['title']} ({$status})";
            $lines[] = $feed['url'];
            $lines[] = '';
        }

        BotHelper::sendMessageByChatId($bot, $chatId, implode("\n", $lines));
    }

    private function handleCallbackQuery(Telegram $bot, array $callbackQuery, string $type): void
    {
        $chatId = $callbackQuery['message']['chat']['id'] ?? $bot->ChatID();
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? '';

        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => '',
        ]);

        if (!AdminHelper::isAdmin($chatId)) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.rss_admin_unauthorized'));

            return;
        }

        if ($callbackData === self::CALLBACK_ADD) {
            $this->sendTagMenu($bot, $chatId);

            return;
        }

        if ($callbackData === self::CALLBACK_LIST) {
            $this->sendFeedList($bot, $chatId);

            return;
        }

        if ($callbackData === self::CALLBACK_BACK) {
            RssAdminStateHelper::clearState($chatId);
            $this->sendMainMenu($bot, $chatId);

            return;
        }

        if (str_starts_with($callbackData, self::CALLBACK_TAG_PREFIX)) {
            $tagName = substr($callbackData, strlen(self::CALLBACK_TAG_PREFIX));
            if ($tagName === '') {
                return;
            }

            RssAdminStateHelper::setState($chatId, 'await_url', ['tag' => $tagName]);
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.rss_admin_send_url', ['tag' => $tagName]));
        }
    }

    private function handleTextMessage(Telegram $bot, string $text, mixed $chatId): void
    {
        $state = RssAdminStateHelper::getCurrentState($chatId);

        if ($state !== 'await_url') {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.rss_admin_use_buttons'));

            return;
        }

        $data = RssAdminStateHelper::getData($chatId);
        $tagName = $data['tag'] ?? '';

        if ($tagName === '') {
            RssAdminStateHelper::clearState($chatId);
            $this->sendMainMenu($bot, $chatId);

            return;
        }

        $result = $this->registrationService->registerFeed($text, $tagName);

        if (!empty($result['duplicate'])) {
            BotHelper::sendMessageByChatId(
                $bot,
                $chatId,
                trans('bot.rss_admin_duplicate', ['id' => $result['rss_item_id'] ?? '?'])
            );
            RssAdminStateHelper::clearState($chatId);

            return;
        }

        if (!$result['ok']) {
            $errorKey = match ($result['error'] ?? '') {
                'invalid_url' => 'bot.rss_admin_error_invalid_url',
                'invalid_xml', 'fetch_failed', 'no_items', 'invalid_feed' => 'bot.rss_admin_error_invalid_feed',
                default => 'bot.rss_admin_error_generic',
            };
            BotHelper::sendMessageByChatId($bot, $chatId, trans($errorKey));
            RssAdminStateHelper::clearState($chatId);

            return;
        }

        BotHelper::sendMessageByChatId(
            $bot,
            $chatId,
            trans('bot.rss_admin_registered', [
                'id' => $result['rss_item_id'],
                'tag' => $tagName,
            ])
        );

        RssAdminStateHelper::clearState($chatId);
        $this->sendMainMenu($bot, $chatId);
    }

    /**
     * @param array<int, array<int, array{text: string, callback_data: string}>> $rows
     */
    private function sendInlineMenu(Telegram $bot, mixed $chatId, string $message, array $rows): void
    {
        $option = [];
        foreach ($rows as $row) {
            $botRow = [];
            foreach ($row as $btn) {
                $botRow[] = $bot->buildInlineKeyBoardButton(
                    $btn['text'],
                    callback_data: $btn['callback_data']
                );
            }
            $option[] = $botRow;
        }

        $keyboard = $bot->buildInlineKeyBoard($option);
        BotHelper::sendKeyboardMessageToChatId($bot, $message, $keyboard, $chatId);
    }
}
