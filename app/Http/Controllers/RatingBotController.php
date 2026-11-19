<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\RatingBot;
use App\Models\RatingBotResponse;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class RatingBotController extends Controller
{
    /**
     * Handle rating bot webhook
     * @throws Exception
     */
    public function index(BotRequest $request)
    {
        Log::info('🔔 Rating Bot - Webhook received', [
            'origin' => $request->input('origin'),
            'bot_mother_id' => $request->input('bot_mother_id'),
            'has_token' => $request->has('token'),
            'timestamp' => now()->toDateTimeString(),
        ]);

        $chatId = null;
        $text = null;

        try {
            $type = $request->input('origin');
            $botMotherId = (int) $request->input('bot_mother_id');

            if ($type === 'bale') {
                $token = $request->has('token') ? $request->input('token') : env('RATING_BOT_TOKEN_BALE');
                $bot = new Telegram($token, 'bale');
            } else {
                $token = $request->has('token') ? $request->input('token') : env('RATING_BOT_TOKEN_TELEGRAM');
                $bot = new Telegram($token);
            }

            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            $update = $request->json()->all() ?? $request->all();
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $type, $botMotherId, $request);
                return;
            }

            $text = $bot->Text();
            $chatId = $bot->ChatID();

            $chatInfo = BotHelper::detectChatInfo($bot);
            if (($chatInfo['is_group'] ?? false) === true) {
                return;
            }

            if ($text === '/start' || str_starts_with($text, '/start ')) {
                $this->handleStart($bot, $type, $botMotherId, $request);
                return;
            }

            BotHelper::sendMessage($bot, trans('bot.this command not recognized'));
        } catch (Exception $e) {
            Log::error('❌ Rating Bot - Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $chatId,
                'text' => $text,
                'origin' => $request->input('origin'),
            ]);

            if (isset($bot)) {
                BotHelper::sendMessage($bot, 'خطایی رخ داد. لطفا دوباره تلاش کنید.');
            }
        }
    }

    private function handleStart(Telegram $bot, string $type, int $botMotherId, BotRequest $request): void
    {
        $chatId = $bot->ChatID();
        $token = $request->has('token')
            ? $request->input('token')
            : ($type === 'bale' ? env('RATING_BOT_TOKEN_BALE') : env('RATING_BOT_TOKEN_TELEGRAM'));

        $botItem = $type === 'bale'
            ? Bot::where('bale_bot_token', $token)->first()
            : Bot::where('telegram_bot_token', $token)->first();

        if (!$botItem) {
            Log::error('Rating Bot - Bot not found in database', [
                'chat_id' => $chatId,
                'type' => $type,
                'token_preview' => substr($token, 0, 10) . '...',
            ]);
            BotHelper::sendMessage($bot, 'خطا: ربات در دیتابیس یافت نشد.');
            return;
        }

        $ratingBot = RatingBot::where('bot_id', $botItem->id)->first();
        if (!$ratingBot) {
            Log::error('Rating Bot - Rating bot data not found', [
                'chat_id' => $chatId,
                'bot_id' => $botItem->id,
            ]);
            BotHelper::sendMessage($bot, 'خطا: محتوای ربات یافت نشد.');
            return;
        }

        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botItem->id)
            ->first();

        if (!$botUser) {
            $botUser = new BotUsers([
                'chat_id' => $chatId,
                'bot_id' => $botItem->id,
                'origin' => $type,
                'status' => 'active',
            ]);
            $botUser->save();
        }

        $items = $ratingBot->getItems();
        $totalLines = count($items);
        if ($totalLines === 0) {
            BotHelper::sendMessage($bot, 'خطا: محتوای ربات خالی است.');
            return;
        }

        $botUser->settings([
            'rating_current_line' => 0,
            'rating_total_lines' => $totalLines,
        ]);

        $this->sendLine($bot, $ratingBot, 0);
    }

    private function handleCallbackQuery(Telegram $bot, array $callbackQuery, string $type, int $botMotherId, BotRequest $request): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? '';
        $chatId = $callbackQuery['message']['chat']['id'] ?? $bot->ChatID();

        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => '',
        ]);

        // Expect: r_{itemIndex}_{rating}
        if (!preg_match('/^r_(\d+)_(\d+)$/', (string) $callbackData, $m)) {
            Log::warning('Rating Bot - Unknown callback data', [
                'chat_id' => $chatId,
                'callback_data' => $callbackData,
                'type' => $type,
            ]);
            return;
        }

        $itemIndex = (int) $m[1];
        $rating = (int) $m[2];
        if ($rating < 1 || $rating > 5) {
            return;
        }

        $token = $request->has('token')
            ? $request->input('token')
            : ($type === 'bale' ? env('RATING_BOT_TOKEN_BALE') : env('RATING_BOT_TOKEN_TELEGRAM'));

        $botItem = $type === 'bale'
            ? Bot::where('bale_bot_token', $token)->first()
            : Bot::where('telegram_bot_token', $token)->first();

        if (!$botItem) {
            Log::error('Rating Bot - Bot not found in callback', [
                'chat_id' => $chatId,
                'type' => $type,
                'token_preview' => substr($token, 0, 10) . '...',
            ]);
            BotHelper::sendMessage($bot, 'خطا: ربات در دیتابیس یافت نشد.');
            return;
        }

        $ratingBot = RatingBot::where('bot_id', $botItem->id)->first();
        if (!$ratingBot) {
            BotHelper::sendMessage($bot, 'خطا: محتوای ربات یافت نشد.');
            return;
        }

        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botItem->id)
            ->first();

        if (!$botUser) {
            $botUser = new BotUsers([
                'chat_id' => $chatId,
                'bot_id' => $botItem->id,
                'origin' => $type,
                'status' => 'active',
            ]);
            $botUser->save();

            $lines = $ratingBot->getItems();
            $botUser->settings([
                'rating_current_line' => 0,
                'rating_total_lines' => count($lines),
            ]);
        }

        RatingBotResponse::updateOrCreate(
            [
                'rating_bot_id' => $ratingBot->id,
                'chat_id' => (string) $chatId,
                'origin' => $type,
                'item_index' => $itemIndex,
            ],
            [
                'rating' => $rating,
            ]
        );

        $totalLines = (int) $botUser->setting('rating_total_lines', count($ratingBot->getItems()));
        $nextIndex = $itemIndex + 1;

        $botUser->settings([
            'rating_current_line' => $nextIndex,
            'rating_total_lines' => $totalLines,
        ]);

        if ($nextIndex >= $totalLines) {
            BotHelper::sendMessage($bot, trans('bot.rating.end'));
            return;
        }

        $this->sendLine($bot, $ratingBot, $nextIndex);
    }

    private function sendLine(Telegram $bot, RatingBot $ratingBot, int $lineIndex): void
    {
        $items = $ratingBot->getItems();
        $totalLines = count($items);
        if ($lineIndex >= $totalLines) {
            return;
        }

        $buttons = [];
        for ($i = 1; $i <= 5; $i++) {
            $buttons[] = $bot->buildInlineKeyBoardButton((string) $i, callback_data: "r_{$lineIndex}_{$i}");
        }

        $inlineKeyboard = $bot->buildInlineKeyBoard([$buttons]);

        $this->sendItem($bot, $items[$lineIndex], $inlineKeyboard);
    }

    private function sendItem(Telegram $bot, array $item, $inlineKeyboard): void
    {
        $chatId = $bot->ChatID();
        $type = $item['type'] ?? 'text';
        $prompt = trans('bot.rating.prompt');

        if ($type === 'text') {
            $text = (string) ($item['content'] ?? '');
            $message = $text . "\n\n" . $prompt;
            BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
            return;
        }

        $fileId = (string) ($item['file_id'] ?? '');
        if ($fileId === '') {
            return;
        }

        $caption = (string) ($item['caption'] ?? '');
        $finalCaption = trim(($caption !== '' ? ($caption . "\n\n") : '') . $prompt);

        $content = [
            'chat_id' => $chatId,
            'reply_markup' => $inlineKeyboard,
            'caption' => $finalCaption,
        ];

        switch ($type) {
            case 'photo':
                $content['photo'] = $fileId;
                $bot->sendPhoto($content);
                break;
            case 'video':
                $content['video'] = $fileId;
                $bot->sendVideo($content);
                break;
            case 'voice':
                $content['voice'] = $fileId;
                $bot->sendVoice($content);
                break;
            case 'audio':
                $content['audio'] = $fileId;
                $bot->sendAudio($content);
                break;
            case 'document':
            default:
                $content['document'] = $fileId;
                $bot->sendDocument($content);
                break;
        }
    }
}

