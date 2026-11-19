<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Interfaces\Services\PoemBotService;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;

class PoemBotController extends Controller
{
    public function __construct(
        private PoemBotService $poemBotService
    ) {}

    public function webhook(Request $request)
    {
        $startTime = microtime(true);
        
        Log::info('🤖 [PoemBot] Webhook received', [
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);

        try {
            $type = $request->input('origin', 'telegram');
            $botMotherId = $request->input('bot_mother_id');
            $botId = $request->input('bot_id');
            
            $bot = $this->createBotInstance($request, $type, $botId);
            
            if (!$bot) {
                Log::error('❌ [PoemBot] Could not create bot instance');
                return response()->json(['status' => 'error'], 200);
            }

            Log::info('📥 [PoemBot] Request details', [
                'type' => $type,
                'bot_id' => $botId,
                'bot_mother_id' => $botMotherId
            ]);

            $update = $request->json()->all() ?? $request->all();
            
            // Check for callback query first
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $type, $botId, $botMotherId);
                return response()->json(['status' => 'ok'], 200);
            }

            // Get chat_id and text safely
            $chatId = $bot->ChatID();
            $text = $bot->Text() ?? '';
            
            Log::info('📨 [PoemBot] Message received', [
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            // Handle text messages
            if (!empty($text)) {
                $this->handleTextMessage($bot, $text, $chatId, $type, $botId, $botMotherId);
            }

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('✅ [PoemBot] Request processed', [
                'processing_time_ms' => $processingTime
            ]);

            return response()->json(['status' => 'ok'], 200);
            
        } catch (Exception $e) {
            Log::error('❌ [PoemBot] Exception occurred', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json(['status' => 'error'], 200);
        }
    }

    private function createBotInstance(Request $request, string $type, ?int $botId): ?Telegram
    {
        $token = null;

        if ($request->has('token')) {
            $token = $request->input('token');
            Log::info('🔑 [PoemBot] Using token from query string');
        } elseif ($botId) {
            $bot = Bot::find($botId);
            if ($bot) {
                $token = $type === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
                Log::info('🔑 [PoemBot] Using token from database', ['bot_id' => $botId]);
            }
        }

        if (!$token) {
            Log::error('❌ [PoemBot] No token found');
            return null;
        }

        return $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }

    private function handleTextMessage(Telegram $bot, string $text, int $chatId, string $type, int $botId, int $botMotherId): void
    {
        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botId)
            ->first();

        if (!$botUser) {
            $botUser = BotUsers::create([
                'chat_id' => $chatId,
                'bot_id' => $botId,
                'origin' => $type,
                'status' => 'active',
            ]);
        }

        // Get current state
        $state = BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->active()
            ->latest()
            ->first();

        $currentState = $state ? $state->state : null;

        // Handle commands
        if ($text == '/start' || str_starts_with($text, '/start ')) {
            $this->handleStart($bot, $botUser, $botMotherId);
            return;
        }

        if ($text == '/help') {
            $this->handleHelp($bot);
            return;
        }

        if ($text == '/about') {
            $this->handleAbout($bot);
            return;
        }

        if ($text == '/myprofile') {
            $this->handleMyProfile($bot, $botUser);
            return;
        }

        if ($text == '/newpoem') {
            $this->handleNewPoem($bot, $botUser, $botMotherId);
            return;
        }

        if ($text == '/editpoem') {
            $this->handleEditPoem($bot, $botUser, $botMotherId);
            return;
        }

        if (str_starts_with($text, '/like ')) {
            $poemId = (int) str_replace('/like ', '', $text);
            $this->handleLikeCommand($bot, $poemId, $botUser);
            return;
        }

        // Handle state-based messages
        switch ($currentState) {
            case 'waiting_poem_type':
                $this->handlePoemTypeSelection($bot, $botUser, $botMotherId, $text, $botId);
                break;
            case 'waiting_poem_line':
                $this->handlePoemLine($bot, $botUser, $botMotherId, $text, $botId);
                break;
            case 'editing_poem':
                $this->handleEditingPoem($bot, $botUser, $botMotherId, $text);
                break;
            default:
                $this->handleStart($bot, $botUser, $botMotherId);
        }
    }

    private function handleStart(Telegram $bot, BotUsers $botUser, int $botMotherId): void
    {
        $message = trans('bot.poem_bot_welcome');
        
        $buttons = [
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_new_poem'), callback_data: 'new_poem')],
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_edit_poem'), callback_data: 'edit_poem')],
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_my_poems'), callback_data: 'my_poems')],
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_likes'), callback_data: 'likes')],
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_list'), callback_data: 'poem_list')],
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_suggest'), callback_data: 'suggest_line')],
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_help'), callback_data: 'help')],
        ];
        
        $inlineKeyboard = $bot->buildInlineKeyBoard($buttons);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
    }

    private function handleHelp(Telegram $bot): void
    {
        $message = trans('bot.poem_bot_help_text');
        BotHelper::sendMessage($bot, $message);
    }

    private function handleAbout(Telegram $bot): void
    {
        $message = trans('bot.poem_bot_about');
        BotHelper::sendMessage($bot, $message);
    }

    private function handleMyProfile(Telegram $bot, BotUsers $botUser): void
    {
        $poemsCount = $this->poemBotService->getUserPoems($botUser->id, 1)->count();
        $message = trans('bot.poem_bot_profile', ['poems_count' => $poemsCount]);
        BotHelper::sendMessage($bot, $message);
    }

    private function handleNewPoem(Telegram $bot, BotUsers $botUser, int $botMotherId): void
    {
        $message = trans('bot.poem_bot_select_type');
        
        $buttons = [
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_classic'), callback_data: 'poem_type_classic')],
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_novel'), callback_data: 'poem_type_novel')],
        ];
        
        $inlineKeyboard = $bot->buildInlineKeyBoard($buttons);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
        
        $this->setState($botUser, $botMotherId, 'waiting_poem_type');
    }

    private function handleEditPoem(Telegram $bot, BotUsers $botUser, int $botMotherId): void
    {
        $poems = $this->poemBotService->getUserPoems($botUser->id, $botMotherId);
        
        if ($poems->isEmpty()) {
            BotHelper::sendMessage($bot, trans('bot.poem_bot_no_poems'));
            return;
        }

        $message = trans('bot.poem_bot_select_poem_to_edit');
        $buttons = [];
        
        foreach ($poems->take(10) as $poem) {
            $title = $poem->title ?: trans('bot.poem_bot_untitled');
            $buttons[] = [$bot->buildInlineKeyBoardButton($title, callback_data: 'edit_poem_' . $poem->id)];
        }
        
        $inlineKeyboard = $bot->buildInlineKeyBoard($buttons);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
        
        $this->setState($botUser, $botMotherId, 'selecting_poem_to_edit');
    }

    private function handleLikeCommand(Telegram $bot, int $poemId, BotUsers $botUser): void
    {
        try {
            $liked = $this->poemBotService->likePoem($poemId, $botUser->id);
            if ($liked) {
                BotHelper::sendMessage($bot, trans('bot.poem_bot_liked'));
            } else {
                BotHelper::sendMessage($bot, trans('bot.poem_bot_already_liked'));
            }
        } catch (Exception $e) {
            Log::error('Error liking poem', ['error' => $e->getMessage()]);
            BotHelper::sendMessage($bot, trans('bot.poem_bot_error'));
        }
    }

    private function handlePoemTypeSelection(Telegram $bot, BotUsers $botUser, int $botMotherId, string $text, int $botId): void
    {
        $poemType = $text === trans('bot.poem_bot_classic') || $text === 'classic' ? 'classic' : 'novel';
        
        $this->setStateData($botUser, $botMotherId, 'poem_type', $poemType);
        $this->setStateData($botUser, $botMotherId, 'poem_lines', []);
        $this->setState($botUser, $botMotherId, 'waiting_poem_line');
        
        $lineType = $poemType === 'classic' ? trans('bot.poem_bot_line') : trans('bot.poem_bot_sentence');
        BotHelper::sendMessage($bot, trans('bot.poem_bot_send_line', ['line_type' => $lineType]));
    }

    private function handlePoemLine(Telegram $bot, BotUsers $botUser, int $botMotherId, string $text, int $botId): void
    {
        $poemType = $this->getStateData($botUser, $botMotherId, 'poem_type');
        $lines = $this->getStateData($botUser, $botMotherId, 'poem_lines', []);
        
        $lines[] = $text;
        $this->setStateData($botUser, $botMotherId, 'poem_lines', $lines);
        
        $lineType = $poemType === 'classic' ? trans('bot.poem_bot_line') : trans('bot.poem_bot_sentence');
        $message = trans('bot.poem_bot_line_added', ['count' => count($lines)]);
        
        $buttons = [
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_add_more'), callback_data: 'add_more_line')],
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_finish'), callback_data: 'finish_poem')],
        ];
        
        $inlineKeyboard = $bot->buildInlineKeyBoard($buttons);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
    }

    private function handleEditingPoem(Telegram $bot, BotUsers $botUser, int $botMotherId, string $text): void
    {
        // Handle editing logic
        BotHelper::sendMessage($bot, trans('bot.poem_bot_editing_not_implemented'));
    }

    private function handleCallbackQuery(Telegram $bot, array $callbackQuery, string $type, int $botId, int $botMotherId): void
    {
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? '';
        $chatId = $callbackQuery['message']['chat']['id'] ?? $bot->ChatID();

        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => '',
        ]);

        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botId)
            ->first();

        if (!$botUser) {
            return;
        }

        // Handle callback data
        if ($callbackData === 'new_poem') {
            $this->handleNewPoem($bot, $botUser, $botMotherId);
        } elseif ($callbackData === 'edit_poem') {
            $this->handleEditPoem($bot, $botUser, $botMotherId);
        } elseif ($callbackData === 'my_poems') {
            $this->handleMyPoems($bot, $botUser, $botMotherId);
        } elseif ($callbackData === 'likes') {
            $this->handleLikes($bot, $botUser, $botMotherId);
        } elseif ($callbackData === 'poem_list') {
            $this->handlePoemList($bot, $botUser, $botMotherId);
        } elseif ($callbackData === 'suggest_line') {
            $this->handleSuggestLine($bot, $botUser, $botMotherId);
        } elseif ($callbackData === 'help') {
            $this->handleHelp($bot);
        } elseif (str_starts_with($callbackData, 'poem_type_')) {
            $poemType = str_replace('poem_type_', '', $callbackData);
            $this->setStateData($botUser, $botMotherId, 'poem_type', $poemType);
            $this->setStateData($botUser, $botMotherId, 'poem_lines', []);
            $this->setState($botUser, $botMotherId, 'waiting_poem_line');
            
            $lineType = $poemType === 'classic' ? trans('bot.poem_bot_line') : trans('bot.poem_bot_sentence');
            BotHelper::sendMessage($bot, trans('bot.poem_bot_send_line', ['line_type' => $lineType]));
        } elseif ($callbackData === 'add_more_line') {
            $poemType = $this->getStateData($botUser, $botMotherId, 'poem_type');
            $lineType = $poemType === 'classic' ? trans('bot.poem_bot_line') : trans('bot.poem_bot_sentence');
            BotHelper::sendMessage($bot, trans('bot.poem_bot_send_line', ['line_type' => $lineType]));
        } elseif ($callbackData === 'finish_poem') {
            $this->finishPoem($bot, $botUser, $botMotherId, $botId);
        } elseif (str_starts_with($callbackData, 'edit_poem_')) {
            $poemId = (int) str_replace('edit_poem_', '', $callbackData);
            $this->showPoemForEditing($bot, $poemId, $botUser, $botMotherId);
        } elseif (str_starts_with($callbackData, 'like_poem_')) {
            $poemId = (int) str_replace('like_poem_', '', $callbackData);
            $this->handleLikePoem($bot, $poemId, $botUser);
        }
    }

    private function handleMyPoems(Telegram $bot, BotUsers $botUser, int $botMotherId): void
    {
        $poems = $this->poemBotService->getUserPoems($botUser->id, $botMotherId);
        
        if ($poems->isEmpty()) {
            BotHelper::sendMessage($bot, trans('bot.poem_bot_no_poems'));
            return;
        }

        $message = trans('bot.poem_bot_my_poems_list', ['count' => $poems->count()]);
        
        foreach ($poems->take(5) as $poem) {
            $title = $poem->title ?: trans('bot.poem_bot_untitled');
            $message .= "\n\n" . $title . " (" . $poem->likes_count . " " . trans('bot.poem_bot_likes') . ")";
        }
        
        BotHelper::sendMessage($bot, $message);
    }

    private function handleLikes(Telegram $bot, BotUsers $botUser, int $botMotherId): void
    {
        // Get poems liked by user
        $message = trans('bot.poem_bot_likes_list');
        BotHelper::sendMessage($bot, $message);
    }

    private function handlePoemList(Telegram $bot, BotUsers $botUser, int $botMotherId): void
    {
        $poems = $this->poemBotService->getPublishedPoems($botMotherId, 'likes_count');
        
        if ($poems->isEmpty()) {
            BotHelper::sendMessage($bot, trans('bot.poem_bot_no_published_poems'));
            return;
        }

        $message = trans('bot.poem_bot_published_poems');
        
        $buttons = [
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_sort_by_likes'), callback_data: 'sort_likes')],
            [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_sort_by_date'), callback_data: 'sort_date')],
        ];
        
        $inlineKeyboard = $bot->buildInlineKeyBoard($buttons);
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
    }

    private function handleSuggestLine(Telegram $bot, BotUsers $botUser, int $botMotherId): void
    {
        $message = trans('bot.poem_bot_suggest_line_instruction');
        BotHelper::sendMessage($bot, $message);
        $this->setState($botUser, $botMotherId, 'waiting_suggestion');
    }

    private function handleLikePoem(Telegram $bot, int $poemId, BotUsers $botUser): void
    {
        try {
            $liked = $this->poemBotService->likePoem($poemId, $botUser->id);
            if ($liked) {
                BotHelper::sendMessage($bot, trans('bot.poem_bot_liked'));
            } else {
                BotHelper::sendMessage($bot, trans('bot.poem_bot_already_liked'));
            }
        } catch (Exception $e) {
            Log::error('Error liking poem', ['error' => $e->getMessage()]);
            BotHelper::sendMessage($bot, trans('bot.poem_bot_error'));
        }
    }

    private function finishPoem(Telegram $bot, BotUsers $botUser, int $botMotherId, int $botId): void
    {
        $poemType = $this->getStateData($botUser, $botMotherId, 'poem_type');
        $lines = $this->getStateData($botUser, $botMotherId, 'poem_lines', []);
        
        if (empty($lines)) {
            BotHelper::sendMessage($bot, trans('bot.poem_bot_no_lines'));
            return;
        }

        try {
            $poem = $this->poemBotService->createPoem([
                'bot_user_id' => $botUser->id,
                'bot_mother_id' => $botMotherId,
                'bot_id' => $botId,
                'title' => null,
                'poem_type' => $poemType,
                'status' => 'draft',
                'likes_count' => 0,
            ], $lines);

            $this->clearState($botUser, $botMotherId);
            
            $message = trans('bot.poem_bot_poem_created', ['id' => $poem->id]);
            
            $buttons = [
                [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_publish'), callback_data: 'publish_poem_' . $poem->id)],
                [$bot->buildInlineKeyBoardButton(trans('bot.poem_bot_save_draft'), callback_data: 'save_draft_' . $poem->id)],
            ];
            
            $inlineKeyboard = $bot->buildInlineKeyBoard($buttons);
            BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
        } catch (Exception $e) {
            Log::error('Error creating poem', ['error' => $e->getMessage()]);
            BotHelper::sendMessage($bot, trans('bot.poem_bot_error'));
        }
    }

    private function showPoemForEditing(Telegram $bot, int $poemId, BotUsers $botUser, int $botMotherId): void
    {
        // Implementation for showing poem for editing
        BotHelper::sendMessage($bot, trans('bot.poem_bot_editing_not_implemented'));
    }

    private function setState(BotUsers $botUser, int $botMotherId, string $state, array $data = []): void
    {
        BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->delete();

        BotUserState::create([
            'bot_user_id' => $botUser->id,
            'bot_mother_id' => $botMotherId,
            'state' => $state,
            'data' => $data,
            'expires_at' => now()->addHours(2),
        ]);
    }

    private function setStateData(BotUsers $botUser, int $botMotherId, string $key, $value): void
    {
        $state = BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->active()
            ->latest()
            ->first();

        if ($state) {
            $data = $state->data ?? [];
            $data[$key] = $value;
            $state->data = $data;
            $state->save();
        }
    }

    private function getStateData(BotUsers $botUser, int $botMotherId, string $key, $default = null)
    {
        $state = BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->active()
            ->latest()
            ->first();

        if ($state && isset($state->data[$key])) {
            return $state->data[$key];
        }

        return $default;
    }

    private function clearState(BotUsers $botUser, int $botMotherId): void
    {
        BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $botMotherId)
            ->delete();
    }
}
