<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\PresenterBot;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class PresenterBotController extends Controller
{
    /**
     * Handle presenter bot webhook
     * @throws Exception
     */
    public function index(BotRequest $request)
    {
        // Log webhook received
        Log::info('🔔 Presenter Bot - Webhook received', [
            'origin' => $request->input('origin'),
            'bot_mother_id' => $request->input('bot_mother_id'),
            'has_token' => $request->has('token'),
            'timestamp' => now()->toDateTimeString()
        ]);
        
        try {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');
            
            if ($type == 'bale') {
                $token = $request->has('token') ? $request->input('token') : env('PRESENTER_BOT_TOKEN_BALE');
                $bot = new Telegram($token, 'bale');
            } else {
                $token = $request->has('token') ? $request->input('token') : env('PRESENTER_BOT_TOKEN_TELEGRAM');
                $bot = new Telegram($token);
            }

            // Verify webhook is set correctly
            $webhookInfo = BotHelper::checkWebhookInfo($token, $type);
            Log::info('📡 Presenter Bot - Webhook status check', [
                'type' => $type,
                'webhook_ok' => $webhookInfo['ok'] ?? false,
                'webhook_url' => $webhookInfo['result']['url'] ?? null,
                'pending_updates' => $webhookInfo['result']['pending_update_count'] ?? 0
            ]);

            // Log the request
            try {
                LogHelper::log($request, $type, $bot);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }

            // Check for callback query (inline button clicks)
            $update = $request->json()->all() ?? $request->all();
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $type, $botMotherId, $request);
                return;
            }

            $text = $bot->Text();
            $chatId = $bot->ChatID();
            
            // Detect chat info (group/private and start status)
            $chatInfo = BotHelper::detectChatInfo($bot);
            $isGroup = $chatInfo['is_group'];
            
            // Log message received and chat info
            Log::info('📨 Presenter Bot - Message received', [
                'chat_id' => $chatId,
                'text' => $text,
                'type' => $type,
                'is_group' => $isGroup,
                'chat_type' => $chatInfo['chat_type'],
                'is_started' => $chatInfo['is_started']
            ]);
            
            if ($isGroup) {
                // Handle group messages - ignore for now
                Log::info('👥 Presenter Bot - Group message ignored', ['chat_id' => $chatId]);
                return;
            }

            // Handle /start command
            if ($text == '/start' || str_starts_with($text, '/start ')) {
                Log::info('▶️ Presenter Bot - Processing /start command', ['chat_id' => $chatId]);
                $this->handleStart($bot, $type, $botMotherId, $request);
            } else {
                // Unknown command
                $message = trans("bot.this command not recognized");
                BotHelper::sendMessage($bot, $message);
            }
            
            Log::info('✅ Presenter Bot - Message processed successfully', ['chat_id' => $chatId]);

        } catch (Exception $e) {
            Log::error('❌ Presenter Bot - Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $chatId ?? null,
                'text' => $text ?? null,
                'origin' => $request->input('origin') ?? null
            ]);
            if (isset($bot)) {
                BotHelper::sendMessage($bot, 'خطایی رخ داد. لطفا دوباره تلاش کنید.');
            }
        }
    }

    /**
     * Handle start command
     * 
     * @param Telegram $bot
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleStart(Telegram $bot, string $type, int $botMotherId, BotRequest $request): void
    {
        $chatId = $bot->ChatID();
        
        // Get token from request
        $token = $request->has('token') ? $request->input('token') : ($type == 'bale' ? env('PRESENTER_BOT_TOKEN_BALE') : env('PRESENTER_BOT_TOKEN_TELEGRAM'));
        
        // Get bot from database
        $botItem = null;
        if ($type == 'bale') {
            $botItem = Bot::where('bale_bot_token', $token)->first();
        } else {
            $botItem = Bot::where('telegram_bot_token', $token)->first();
        }
        
        if (!$botItem) {
            Log::error('Presenter Bot - Bot not found in database', [
                'chat_id' => $chatId,
                'type' => $type,
                'token_preview' => substr($token, 0, 10) . '...'
            ]);
            BotHelper::sendMessage($bot, 'خطا: ربات در دیتابیس یافت نشد.');
            return;
        }
        
        if (!$botItem) {
            Log::error('Presenter Bot - Bot not found in database', [
                'chat_id' => $chatId,
                'type' => $type
            ]);
            BotHelper::sendMessage($bot, 'خطا: ربات در دیتابیس یافت نشد.');
            return;
        }
        
        // Get presenter bot data
        $presenterBot = PresenterBot::where('bot_id', $botItem->id)->first();
        
        if (!$presenterBot) {
            Log::error('Presenter Bot - Presenter bot data not found', [
                'chat_id' => $chatId,
                'bot_id' => $botItem->id
            ]);
            BotHelper::sendMessage($bot, 'خطا: محتوای ربات یافت نشد.');
            return;
        }
        
        // Get or create bot user - use botItem->id not botMotherId
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
        
        // Get lines
        $items = $presenterBot->getItems();
        $totalLines = count($items);
        
        if ($totalLines == 0) {
            BotHelper::sendMessage($bot, 'خطا: محتوای ربات خالی است.');
            return;
        }
        
        // Reset user state
        $botUser->settings([
            'presenter_current_line' => 0,
            'presenter_total_lines' => $totalLines,
        ]);
        
        // Send first item
        $this->sendLine($bot, $presenterBot, $botUser, 0);
    }

    /**
     * Handle callback query (button clicks)
     * 
     * @param Telegram $bot
     * @param array $callbackQuery
     * @param string $type
     * @param int $botMotherId
     * @return void
     */
    private function handleCallbackQuery(Telegram $bot, array $callbackQuery, string $type, int $botMotherId, BotRequest $request): void
    {
        $chatId = $callbackQuery['message']['chat']['id'] ?? $bot->ChatID();
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? '';
        
        Log::info('Presenter Bot - Callback query received', [
            'chat_id' => $chatId,
            'callback_data' => $callbackData,
            'type' => $type
        ]);
        
        // Answer callback query
        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => '',
        ]);
        
        // Get bot from database
        $botItem = null;
        $token = $request->has('token') ? $request->input('token') : ($type == 'bale' ? env('PRESENTER_BOT_TOKEN_BALE') : env('PRESENTER_BOT_TOKEN_TELEGRAM'));
        if ($type == 'bale') {
            $botItem = Bot::where('bale_bot_token', $token)->first();
        } else {
            $botItem = Bot::where('telegram_bot_token', $token)->first();
        }
        
        if (!$botItem) {
            Log::error('Presenter Bot - Bot not found in callback', [
                'chat_id' => $chatId,
                'type' => $type,
                'token_preview' => substr($token, 0, 10) . '...'
            ]);
            BotHelper::sendMessage($bot, 'خطا: ربات در دیتابیس یافت نشد.');
            return;
        }
        
        // Get presenter bot data
        $presenterBot = PresenterBot::where('bot_id', $botItem->id)->first();
        
        if (!$presenterBot) {
            Log::error('Presenter Bot - Presenter bot data not found in callback', [
                'chat_id' => $chatId,
                'bot_id' => $botItem->id
            ]);
            BotHelper::sendMessage($bot, 'خطا: محتوای ربات یافت نشد.');
            return;
        }
        
        // Get bot user
        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botItem->id)
            ->first();
        
        if (!$botUser) {
            Log::error('Presenter Bot - Bot user not found in callback', [
                'chat_id' => $chatId,
                'bot_id' => $botItem->id,
                'type' => $type
            ]);
            
            // Try to create user if not found
            $botUser = new BotUsers([
                'chat_id' => $chatId,
                'bot_id' => $botItem->id,
                'origin' => $type,
                'status' => 'active',
            ]);
            $botUser->save();
            
            // Get items and set initial state
            $items = $presenterBot->getItems();
            $totalLines = count($items);
            $botUser->settings([
                'presenter_current_line' => 0,
                'presenter_total_lines' => $totalLines,
            ]);
        }
        
        // Handle "next" button
        if ($callbackData == 'presenter_next') {
            $currentLine = $botUser->setting('presenter_current_line', 0);
            $totalLines = $botUser->setting('presenter_total_lines', 0);
            
            Log::info('Presenter Bot - Processing next button', [
                'chat_id' => $chatId,
                'current_line' => $currentLine,
                'total_lines' => $totalLines
            ]);
            
            // Check if there are more lines
            if ($currentLine < $totalLines - 1) {
                $nextLine = $currentLine + 1;
                $botUser->settings(['presenter_current_line' => $nextLine]);
                $this->sendLine($bot, $presenterBot, $botUser, $nextLine);
            } else {
                // Last line - send end message
                $endMessage = trans('bot.presenter.end');
                BotHelper::sendMessage($bot, $endMessage);
            }
        } else {
            Log::warning('Presenter Bot - Unknown callback data', [
                'chat_id' => $chatId,
                'callback_data' => $callbackData
            ]);
        }
    }

    /**
     * Send a specific line to user
     * 
     * @param Telegram $bot
     * @param PresenterBot $presenterBot
     * @param BotUsers $botUser
     * @param int $lineIndex
     * @return void
     */
    private function sendLine(Telegram $bot, PresenterBot $presenterBot, BotUsers $botUser, int $lineIndex): void
    {
        $items = $presenterBot->getItems();
        $totalLines = count($items);
        
        if ($lineIndex >= $totalLines) {
            return;
        }
        
        $chatId = $bot->ChatID();
        
        // Check if this is the last line
        if ($lineIndex == $totalLines - 1) {
            // Last item - send without button then end message
            $this->sendItem($bot, $items[$lineIndex]);
            $endMessage = trans('bot.presenter.end');
            BotHelper::sendMessage($bot, $endMessage);
        } else {
            // Not last item - send with "next" button
            $nextButtonText = trans('bot.presenter.next');
            $option = [
                array($bot->buildInlineKeyBoardButton($nextButtonText, callback_data: 'presenter_next'))
            ];
            $inlineKeyboard = $bot->buildInlineKeyBoard($option);

            $this->sendItem($bot, $items[$lineIndex], $inlineKeyboard);
        }
    }

    private function sendItem(Telegram $bot, array $item, $inlineKeyboard = null): void
    {
        $chatId = $bot->ChatID();
        $type = $item['type'] ?? 'text';

        if ($type === 'text') {
            $text = (string) ($item['content'] ?? '');
            if ($inlineKeyboard) {
                BotHelper::sendKeyboardMessage($bot, $text, $inlineKeyboard);
            } else {
                BotHelper::sendMessage($bot, $text);
            }
            return;
        }

        $fileId = (string) ($item['file_id'] ?? '');
        if ($fileId === '') {
            return;
        }

        $content = [
            'chat_id' => $chatId,
        ];

        $caption = (string) ($item['caption'] ?? '');
        if ($caption !== '') {
            $content['caption'] = $caption;
        }

        if ($inlineKeyboard) {
            $content['reply_markup'] = $inlineKeyboard;
        }

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
