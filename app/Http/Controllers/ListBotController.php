<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Models\Bot;
use App\Models\ListBotConfig;
use App\Services\ListBotMenuParser;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class ListBotController extends Controller
{
    private const CALLBACK_BACK = 'lb_b';
    private const CALLBACK_NODE_PREFIX = 'lb_';

    public function index(BotRequest $request)
    {
        Log::info('🔔 List Bot - Webhook received', [
            'origin' => $request->input('origin'),
            'has_token' => $request->has('token'),
        ]);

        try {
            $type = $request->input('origin', 'telegram');
            $token = $request->input('token');
            if (empty($token)) {
                Log::warning('List Bot - Missing token');
                return;
            }

            $bot = $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);

            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            $botItem = $this->getBotByToken($token, $type);
            if (!$botItem || $botItem->endpoint_id !== 'webhook-list-bot') {
                Log::warning('List Bot - Bot not found or wrong endpoint', ['token_preview' => substr($token, 0, 8) . '...']);
                BotHelper::sendMessage($bot, 'ربات در سیستم یافت نشد.');
                return;
            }

            $update = $request->json()->all() ?? $request->all();
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $botItem, $type, $token);
                return;
            }

            $text = $bot->Text();
            $chatId = $bot->ChatID();

            if ($text === '/start' || str_starts_with($text ?? '', '/start ')) {
                $this->handleStart($bot, $botItem, $type, $token);
                return;
            }

            if ($text !== null && $text !== '') {
                $this->handleTextMessage($bot, $text, $botItem, $type, $token);
            }
        } catch (Exception $e) {
            Log::error('❌ List Bot - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            if (isset($bot)) {
                BotHelper::sendMessage($bot, 'خطایی رخ داد. لطفا دوباره تلاش کنید.');
            }
        }
    }

    private function getBotByToken(string $token, string $type): ?Bot
    {
        if ($type === 'bale') {
            return Bot::where('bale_bot_token', $token)->first();
        }
        return Bot::where('telegram_bot_token', $token)->first();
    }

    private function isAdmin(Bot $botItem, $chatId, string $type): bool
    {
        if ($type === 'bale') {
            return (string) $botItem->bale_owner_chat_id === (string) $chatId;
        }
        return (string) $botItem->telegram_owner_chat_id === (string) $chatId;
    }

    private function handleStart(Telegram $bot, Bot $botItem, string $type, string $token): void
    {
        $chatId = $bot->ChatID();
        $config = ListBotConfig::firstOrCreate(
            ['bot_id' => $botItem->id],
            ['menu_json' => null, 'raw_content' => null]
        );

        if (!$config->hasMenu()) {
            if ($this->isAdmin($botItem, $chatId, $type)) {
                $msg = "منوی ربات هنوز تعریف نشده است.\n\n";
                $msg .= "یک پیام به فرمت زیر بفرستید تا دکمه‌های شیشه‌ای ساخته شوند:\n\n";
                $msg .= "خط اول: عنوان اصلی (با یک -)\n";
                $msg .= "خطوط بعد: هر خط با -- یا --- و بعد عنوان و در صورت نیاز : لینک\n\n";
                $msg .= "مثال:\n";
                $msg .= "- عنوان مادر\n";
                $msg .= "-- زیر۱: https://t.me/bot\n";
                $msg .= "-- زیر۲: https://example.com\n";
                $msg .= "--- زیر۲-۱: https://eitaa.com/join/xxx";
                BotHelper::sendMessage($bot, $msg);
            } else {
                BotHelper::sendMessage($bot, 'منو در حال آماده‌سازی است. لطفاً بعداً مراجعه کنید.');
            }
            return;
        }

        $tree = $config->getMenuTree();
        $message = $tree['title'] ?? 'فهرست';
        $rows = $this->buildKeyboardFromNode($bot, $tree, '', $type);
        $this->sendMenuMessage($bot, $message, $rows, $type, $token, $chatId);
    }

    private function handleTextMessage(Telegram $bot, string $text, Bot $botItem, string $type, string $token): void
    {
        $chatId = $bot->ChatID();

        if (!$this->isAdmin($botItem, $chatId, $type)) {
            BotHelper::sendMessage($bot, 'فقط از دکمه‌ها استفاده کنید.');
            return;
        }

        $trimmed = trim($text);
        if ($trimmed === '' || $trimmed[0] !== '-') {
            BotHelper::sendMessage($bot, 'برای به‌روز کردن منو، متن را با خطی که با «-» شروع می‌شود بفرستید. نمونه در /start');
            return;
        }

        $parser = new ListBotMenuParser();
        $tree = $parser->parse($text);
        if (empty($tree['children']) && ($tree['title'] ?? '') === '') {
            BotHelper::sendMessage($bot, 'فرمت متن قابل تشخیص نبود. لطفاً مطابق نمونه در /start ارسال کنید.');
            return;
        }

        $config = ListBotConfig::firstOrCreate(
            ['bot_id' => $botItem->id],
            ['menu_json' => null, 'raw_content' => null]
        );
        $config->menu_json = $tree;
        $config->raw_content = $text;
        $config->save();

        $message = $tree['title'] ?? 'فهرست';
        $rows = $this->buildKeyboardFromNode($bot, $tree, '', $type);
        BotHelper::sendMessage($bot, 'منو به‌روز شد.');
        $this->sendMenuMessage($bot, $message, $rows, $type, $token, $chatId);
    }

    private function handleCallbackQuery(Telegram $bot, array $callbackQuery, Bot $botItem, string $type, string $token): void
    {
        $chatId = $callbackQuery['message']['chat']['id'] ?? $bot->ChatID();
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? '';

        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => '',
        ]);

        $config = ListBotConfig::where('bot_id', $botItem->id)->first();
        if (!$config || !$config->hasMenu()) {
            BotHelper::sendMessage($bot, 'منو یافت نشد.');
            return;
        }

        $tree = $config->getMenuTree();

        if ($callbackData === self::CALLBACK_BACK) {
            $message = $tree['title'] ?? 'فهرست';
            $rows = $this->buildKeyboardFromNode($bot, $tree, '', $type);
            $this->editOrSendMenuMessage($bot, $message, $rows, $type, $token, $chatId, $messageId);
            return;
        }

        if (str_starts_with($callbackData, self::CALLBACK_NODE_PREFIX)) {
            $path = substr($callbackData, strlen(self::CALLBACK_NODE_PREFIX));
            if ($path === '') {
                return;
            }
            $node = $this->getNodeByPath($tree, $path);
            if ($node === null || empty($node['children'])) {
                return;
            }
            $message = $node['title'] ?? 'فهرست';
            $rows = $this->buildKeyboardFromNode($bot, $node, $path, $type);
            $this->editOrSendMenuMessage($bot, $message, $rows, $type, $token, $chatId, $messageId);
        }
    }

    /**
     * @param array{title: string, link?: string, children?: array} $tree
     * @return array{title: string, link?: string, children?: array}|null
     */
    private function getNodeByPath(array $tree, string $path): ?array
    {
        $indices = array_filter(explode('_', $path), fn ($x) => $x !== '');
        $node = $tree;
        foreach ($indices as $idx) {
            $children = $node['children'] ?? [];
            if (!is_array($children) || !isset($children[(int) $idx])) {
                return null;
            }
            $node = $children[(int) $idx];
        }
        return $node;
    }

    /**
     * Build inline keyboard rows from a tree node (its children). Path is used for callback_data for submenus.
     *
     * @param array{title: string, link?: string, children?: array} $node
     * @return array
     */
    private function buildKeyboardFromNode(Telegram $bot, array $node, string $pathPrefix, string $type): array
    {
        $children = $node['children'] ?? [];
        $rows = [];
        $parser = new ListBotMenuParser();

        foreach ($children as $index => $child) {
            $title = $child['title'] ?? '';
            if ($title === '') {
                continue;
            }
            $btn = ['text' => $this->truncateButtonText($title)];
            $hasChildren = !empty($child['children']) && is_array($child['children']);
            $hasValidLink = !empty($child['link']) && $parser->validateLink($child['link']);

            if ($hasValidLink) {
                $btn['url'] = $child['link'];
                $btn['callback_data'] = '';
            } elseif ($hasChildren) {
                $btn['url'] = '';
                $subPath = $pathPrefix === '' ? (string) $index : $pathPrefix . '_' . $index;
                $btn['callback_data'] = self::CALLBACK_NODE_PREFIX . $subPath;
            } else {
                continue;
            }
            $rows[] = [$btn];
        }

        if ($pathPrefix !== '') {
            $rows[] = [['text' => 'بازگشت به فهرست اصلی', 'callback_data' => self::CALLBACK_BACK, 'url' => '']];
        }

        return $rows;
    }

    private function truncateButtonText(string $text, int $max = 64): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return mb_substr($text, 0, $max - 2) . '…';
    }

    /**
     * @param array<int, array<int, array{text: string, url?: string, callback_data?: string}>> $rows
     */
    private function buildReplyMarkup(Telegram $bot, array $rows, string $type): array
    {
        $option = [];
        foreach ($rows as $row) {
            $botRow = [];
            foreach ($row as $btn) {
                $text = $btn['text'];
                $url = $btn['url'] ?? '';
                $callbackData = $btn['callback_data'] ?? '';
                if ($url !== '') {
                    $botRow[] = $bot->buildInlineKeyBoardButton($text, url: $url);
                } else {
                    $botRow[] = $bot->buildInlineKeyBoardButton($text, callback_data: $callbackData);
                }
            }
            if (!empty($botRow)) {
                $option[] = $botRow;
            }
        }
        return $bot->buildInlineKeyBoard($option);
    }

    /**
     * Build Bale-compatible inline_keyboard array from same rows.
     * @param array<int, array<int, array{text: string, url?: string, callback_data?: string}>> $rows
     */
    private function rowsToBaleKeyboard(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $baleRow = [];
            foreach ($row as $btn) {
                $b = ['text' => $btn['text']];
                if (!empty($btn['url'])) {
                    $b['url'] = $btn['url'];
                } else {
                    $b['callback_data'] = $btn['callback_data'] ?? '';
                }
                $baleRow[] = $b;
            }
            if (!empty($baleRow)) {
                $out[] = $baleRow;
            }
        }
        return $out;
    }

    private function sendMenuMessage(Telegram $bot, string $message, array $rows, string $type, string $token, $chatId): void
    {
        if ($type === 'bale') {
            BotHelper::messageWithKeyboard($token, $chatId, $message, $this->rowsToBaleKeyboard($rows));
        } else {
            $keyboard = $this->buildReplyMarkup($bot, $rows, $type);
            BotHelper::sendKeyboardMessageToChatId($bot, $message, $keyboard, $chatId);
        }
    }

    private function editOrSendMenuMessage(Telegram $bot, string $message, array $rows, string $type, string $token, $chatId, $messageId = null): void
    {
        if ($messageId !== null) {
            try {
                if ($type === 'bale') {
                    $keyboard = ['inline_keyboard' => $this->rowsToBaleKeyboard($rows)];
                    $bot->editMessageText([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => $message,
                        'reply_markup' => json_encode($keyboard),
                        'parse_mode' => 'html',
                    ]);
                } else {
                    $keyboard = $this->buildReplyMarkup($bot, $rows, $type);
                    $bot->editMessageText([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => $message,
                        'reply_markup' => $keyboard,
                        'parse_mode' => 'html',
                    ]);
                }
                return;
            } catch (Exception $e) {
                Log::warning('List Bot - editMessageText failed, sending new', ['error' => $e->getMessage()]);
            }
        }
        $this->sendMenuMessage($bot, $message, $rows, $type, $token, $chatId);
    }
}
