<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\PsychologyTestBot;
use App\Models\PsychologyTestCategory;
use App\Models\PsychologyTestQuestion;
use App\Models\PsychologyTestResult;
use App\Models\PsychologyTestBotAdmin;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram;

class PsychologyTestBotController extends Controller
{
    /**
     * Handle psychology test bot webhook
     * @throws Exception
     */
    public function index(BotRequest $request)
    {
        // Log webhook received
        Log::info('🔔 Psychology Test Bot - Webhook received', [
            'origin' => $request->input('origin'),
            'bot_mother_id' => $request->input('bot_mother_id'),
            'has_token' => $request->has('token'),
            'timestamp' => now()->toDateTimeString()
        ]);
        
        try {
            $type = $request->input('origin');
            $botMotherId = $request->input('bot_mother_id');
            
            // Get token from request
            $token = $request->has('token') ? $request->input('token') : null;
            if (!$token) {
                Log::error('Psychology Test Bot - Token not provided');
                return;
            }
            
            if ($type == 'bale') {
                $bot = new Telegram($token, 'bale');
            } else {
                $bot = new Telegram($token);
            }

            // Verify webhook is set correctly
            $webhookInfo = BotHelper::checkWebhookInfo($token, $type);
            Log::info('📡 Psychology Test Bot - Webhook status check', [
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
            Log::info('📨 Psychology Test Bot - Message received', [
                'chat_id' => $chatId,
                'text' => $text,
                'type' => $type,
                'is_group' => $isGroup,
                'chat_type' => $chatInfo['chat_type'],
                'is_started' => $chatInfo['is_started']
            ]);
            
            if ($isGroup) {
                // Handle group messages - ignore for now
                Log::info('👥 Psychology Test Bot - Group message ignored', ['chat_id' => $chatId]);
                return;
            }

            // Get bot from database
            $botItem = null;
            if ($type == 'bale') {
                $botItem = Bot::where('bale_bot_token', $token)->first();
            } else {
                $botItem = Bot::where('telegram_bot_token', $token)->first();
            }
            
            if (!$botItem) {
                Log::error('Psychology Test Bot - Bot not found in database', [
                    'chat_id' => $chatId,
                    'type' => $type
                ]);
                BotHelper::sendMessage($bot, 'خطا: ربات در دیتابیس یافت نشد.');
                return;
            }
            
            // Get psychology test bot
            $psychologyTestBot = PsychologyTestBot::where('bot_id', $botItem->id)->first();
            
            if (!$psychologyTestBot) {
                Log::error('Psychology Test Bot - Psychology test bot data not found', [
                    'chat_id' => $chatId,
                    'bot_id' => $botItem->id
                ]);
                BotHelper::sendMessage($bot, 'خطا: اطلاعات تست یافت نشد.');
                return;
            }

            // Handle /start command
            if ($text == '/start' || str_starts_with($text, '/start ')) {
                Log::info('▶️ Psychology Test Bot - Processing /start command', ['chat_id' => $chatId]);
                $this->handleStart($bot, $type, $botItem, $psychologyTestBot);
            }
            // Handle /add_admin command (for admins)
            else if (str_starts_with($text, '/add_admin')) {
                Log::info('👤 Psychology Test Bot - Processing /add_admin command', ['chat_id' => $chatId]);
                $this->handleAddAdmin($bot, $text, $type, $psychologyTestBot);
            }
            // Handle /results command (for admins)
            else if ($text == '/results' || str_starts_with($text, '/results ')) {
                Log::info('📊 Psychology Test Bot - Processing /results command', ['chat_id' => $chatId]);
                $this->handleResults($bot, $type, $psychologyTestBot);
            }
            else {
                // Unknown command
                $message = trans("bot.this command not recognized");
                BotHelper::sendMessage($bot, $message);
            }
            
            Log::info('✅ Psychology Test Bot - Message processed successfully', ['chat_id' => $chatId]);

        } catch (Exception $e) {
            Log::error('❌ Psychology Test Bot - Error occurred', [
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
     * Handle start command - start the test
     * 
     * @param Telegram $bot
     * @param string $type
     * @param Bot $botItem
     * @param PsychologyTestBot $psychologyTestBot
     * @return void
     */
    private function handleStart(Telegram $bot, string $type, Bot $botItem, PsychologyTestBot $psychologyTestBot): void
    {
        $chatId = $bot->ChatID();
        
        // Get or create bot user
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
        
        // Get all questions
        $questions = PsychologyTestQuestion::where('psychology_test_bot_id', $psychologyTestBot->id)
            ->with('category')
            ->get();
        
        if ($questions->isEmpty()) {
            BotHelper::sendMessage($bot, 'خطا: هیچ سوالی تعریف نشده است.');
            return;
        }
        
        // Shuffle questions for random order
        $questions = $questions->shuffle();
        
        // Reset user state
        $botUser->settings([
            'psychology_test_questions' => $questions->pluck('id')->toArray(),
            'psychology_test_current_question_index' => 0,
            'psychology_test_answers' => [],
            'psychology_test_bot_id' => $psychologyTestBot->id,
        ]);
        
        // Send first question
        $this->sendQuestion($bot, $questions->first(), 0, $questions->count());
    }

    /**
     * Send a question to user
     * 
     * @param Telegram $bot
     * @param PsychologyTestQuestion $question
     * @param int $questionIndex
     * @param int $totalQuestions
     * @return void
     */
    private function sendQuestion(Telegram $bot, PsychologyTestQuestion $question, int $questionIndex, int $totalQuestions): void
    {
        $chatId = $bot->ChatID();
        
        // Options: خیلی کم, کم, متوسط, زیاد, خیلی زیاد
        $options = [
            'psychology_answer_1' => 'خیلی کم',
            'psychology_answer_2' => 'کم',
            'psychology_answer_3' => 'متوسط',
            'psychology_answer_4' => 'زیاد',
            'psychology_answer_5' => 'خیلی زیاد',
        ];
        
        $message = "📝 سوال " . ($questionIndex + 1) . "/{$totalQuestions}\n\n";
        $message .= $question->question_text . "\n\n";
        
        // Build inline keyboard
        $keyboard = [];
        foreach ($options as $callbackData => $optionText) {
            $keyboard[] = [
                $bot->buildInlineKeyBoardButton($optionText, callback_data: "{$callbackData}_{$question->id}")
            ];
        }
        
        $inlineKeyboard = $bot->buildInlineKeyBoard($keyboard);
        
        BotHelper::sendKeyboardMessage($bot, $message, $inlineKeyboard);
        
        Log::info('Psychology Test Bot - Question sent', [
            'chat_id' => $chatId,
            'question_id' => $question->id,
            'question_index' => $questionIndex,
            'total_questions' => $totalQuestions
        ]);
    }

    /**
     * Handle callback query (button clicks)
     * 
     * @param Telegram $bot
     * @param array $callbackQuery
     * @param string $type
     * @param int $botMotherId
     * @param BotRequest $request
     * @return void
     */
    private function handleCallbackQuery(Telegram $bot, array $callbackQuery, string $type, int $botMotherId, BotRequest $request): void
    {
        $chatId = $callbackQuery['message']['chat']['id'] ?? $bot->ChatID();
        $callbackData = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? '';
        
        Log::info('Psychology Test Bot - Callback query received', [
            'chat_id' => $chatId,
            'callback_data' => $callbackData,
            'type' => $type
        ]);
        
        // Answer callback query
        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => '',
        ]);
        
        // Get token from request
        $token = $request->has('token') ? $request->input('token') : null;
        if (!$token) {
            BotHelper::sendMessage($bot, 'خطا: توکن یافت نشد.');
            return;
        }
        
        // Get bot from database
        $botItem = null;
        if ($type == 'bale') {
            $botItem = Bot::where('bale_bot_token', $token)->first();
        } else {
            $botItem = Bot::where('telegram_bot_token', $token)->first();
        }
        
        if (!$botItem) {
            Log::error('Psychology Test Bot - Bot not found in callback', [
                'chat_id' => $chatId,
                'type' => $type
            ]);
            BotHelper::sendMessage($bot, 'خطا: ربات در دیتابیس یافت نشد.');
            return;
        }
        
        // Get bot user
        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botItem->id)
            ->first();
        
        if (!$botUser) {
            Log::error('Psychology Test Bot - Bot user not found in callback', [
                'chat_id' => $chatId,
                'bot_id' => $botItem->id
            ]);
            BotHelper::sendMessage($bot, 'خطا: کاربر یافت نشد.');
            return;
        }
        
        // Parse callback data: psychology_answer_X_questionId
        if (preg_match('/^psychology_answer_(\d+)_(\d+)$/', $callbackData, $matches)) {
            $answerIndex = intval($matches[1]); // 1-5
            $questionId = intval($matches[2]);
            
            $this->handleAnswer($bot, $botUser, $answerIndex, $questionId, $type, $botItem);
        } else {
            Log::warning('Psychology Test Bot - Unknown callback data', [
                'chat_id' => $chatId,
                'callback_data' => $callbackData
            ]);
        }
    }

    /**
     * Handle answer to question
     * 
     * @param Telegram $bot
     * @param BotUsers $botUser
     * @param int $answerIndex (1-5)
     * @param int $questionId
     * @param string $type
     * @param Bot $botItem
     * @return void
     */
    private function handleAnswer(Telegram $bot, BotUsers $botUser, int $answerIndex, int $questionId, string $type, Bot $botItem): void
    {
        $chatId = $bot->ChatID();
        
        // Get current state
        $questions = $botUser->setting('psychology_test_questions', []);
        $currentIndex = $botUser->setting('psychology_test_current_question_index', 0);
        $answers = $botUser->setting('psychology_test_answers', []);
        $psychologyTestBotId = $botUser->setting('psychology_test_bot_id');
        
        if (!$psychologyTestBotId) {
            BotHelper::sendMessage($bot, 'خطا: اطلاعات تست یافت نشد.');
            return;
        }
        
        // Save answer
        $answers[$questionId] = $answerIndex;
        $botUser->settings([
            'psychology_test_answers' => $answers,
        ]);
        
        // Move to next question
        $currentIndex++;
        $botUser->settings([
            'psychology_test_current_question_index' => $currentIndex,
        ]);
        
        // Check if all questions answered
        if ($currentIndex >= count($questions)) {
            // All questions answered - calculate results
            $this->calculateAndShowResults($bot, $botUser, $psychologyTestBotId, $type);
        } else {
            // Get next question
            $nextQuestionId = $questions[$currentIndex];
            $nextQuestion = PsychologyTestQuestion::with('category')->find($nextQuestionId);
            
            if ($nextQuestion) {
                $this->sendQuestion($bot, $nextQuestion, $currentIndex, count($questions));
            } else {
                BotHelper::sendMessage($bot, 'خطا: سوال بعدی یافت نشد.');
            }
        }
    }

    /**
     * Calculate and show results
     * 
     * @param Telegram $bot
     * @param BotUsers $botUser
     * @param int $psychologyTestBotId
     * @param string $type
     * @return void
     */
    private function calculateAndShowResults(Telegram $bot, BotUsers $botUser, int $psychologyTestBotId, string $type): void
    {
        $chatId = $bot->ChatID();
        $answers = $botUser->setting('psychology_test_answers', []);
        
        // Get all questions with answers
        $questions = PsychologyTestQuestion::where('psychology_test_bot_id', $psychologyTestBotId)
            ->with('category')
            ->get();
        
        // Calculate scores for each category
        $categoryScores = [];
        
        foreach ($questions as $question) {
            $answerIndex = $answers[$question->id] ?? null;
            if ($answerIndex === null) {
                continue;
            }
            
            $categoryId = $question->psychology_test_category_id;
            if (!isset($categoryScores[$categoryId])) {
                $categoryScores[$categoryId] = [
                    'total_score' => 0,
                    'total_weight' => 0,
                ];
            }
            
            // Calculate score based on direction
            // answerIndex: 1=خیلی کم, 2=کم, 3=متوسط, 4=زیاد, 5=خیلی زیاد
            // Convert to 0-4 scale
            $answerValue = $answerIndex - 1;
            
            if ($question->direction == 1) {
                // خیلی زیاد به سمت دسته
                $score = ($answerValue / 4) * $question->weight;
            } else {
                // خیلی کم به سمت دسته
                $score = ((4 - $answerValue) / 4) * $question->weight;
            }
            
            $categoryScores[$categoryId]['total_score'] += $score;
            $categoryScores[$categoryId]['total_weight'] += $question->weight;
        }
        
        // Calculate final scores (percentage)
        $resultData = [];
        foreach ($categoryScores as $categoryId => $scoreData) {
            $category = PsychologyTestCategory::find($categoryId);
            if (!$category) {
                continue;
            }
            
            $finalScore = 0;
            if ($scoreData['total_weight'] > 0) {
                $finalScore = ($scoreData['total_score'] / $scoreData['total_weight']) * 100;
            }
            
            $resultData[$category->name] = [
                'score' => round($finalScore, 2),
                'description' => $category->description,
            ];
        }
        
        // Save result
        $result = PsychologyTestResult::create([
            'psychology_test_bot_id' => $psychologyTestBotId,
            'chat_id' => $chatId,
            'origin' => $type,
            'result_data' => $resultData,
            'completed_at' => now(),
        ]);
        
        // Build result message
        $message = "✅ تست شما تکمیل شد!\n\n";
        $message .= "📊 نتایج:\n\n";
        
        foreach ($resultData as $categoryName => $categoryResult) {
            $message .= "📌 {$categoryName}:\n";
            $message .= "   امتیاز: " . $categoryResult['score'] . "%\n";
            if (!empty($categoryResult['description'])) {
                $message .= "   {$categoryResult['description']}\n";
            }
            $message .= "\n";
        }
        
        $message .= "💡 می‌توانید دوباره تست را با دستور /start شروع کنید.";
        
        BotHelper::sendMessage($bot, $message);
        
        // Clear user state
        $botUser->settings([
            'psychology_test_questions' => null,
            'psychology_test_current_question_index' => null,
            'psychology_test_answers' => null,
            'psychology_test_bot_id' => null,
        ]);
        
        Log::info('Psychology Test Bot - Test completed', [
            'chat_id' => $chatId,
            'psychology_test_bot_id' => $psychologyTestBotId,
            'result_id' => $result->id,
        ]);
    }

    /**
     * Handle add admin command
     * 
     * @param Telegram $bot
     * @param string $text
     * @param string $type
     * @param PsychologyTestBot $psychologyTestBot
     * @return void
     */
    private function handleAddAdmin(Telegram $bot, string $text, string $type, PsychologyTestBot $psychologyTestBot): void
    {
        $chatId = $bot->ChatID();
        
        // Check if user is creator
        $isCreator = PsychologyTestBotAdmin::where('psychology_test_bot_id', $psychologyTestBot->id)
            ->where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('is_creator', true)
            ->exists();
        
        if (!$isCreator) {
            BotHelper::sendMessage($bot, '❌ شما دسترسی به این دستور ندارید. فقط سازنده ربات می‌تواند ادمین اضافه کند.');
            return;
        }
        
        // Parse command: /add_admin @username or /add_admin chat_id
        $parts = explode(' ', trim($text), 2);
        if (count($parts) < 2) {
            BotHelper::sendMessage($bot, "❌ فرمت دستور نادرست است.\nاستفاده: /add_admin @username یا /add_admin chat_id");
            return;
        }
        
        $adminIdentifier = trim($parts[1]);
        
        // Check current admin count (max 3 including creator)
        $adminCount = PsychologyTestBotAdmin::where('psychology_test_bot_id', $psychologyTestBot->id)->count();
        if ($adminCount >= 3) {
            BotHelper::sendMessage($bot, '❌ حداکثر 3 ادمین مجاز است (شامل سازنده).');
            return;
        }
        
        // TODO: Parse username and get chat_id
        // For now, we'll assume adminIdentifier is chat_id
        $adminChatId = is_numeric($adminIdentifier) ? intval($adminIdentifier) : null;
        
        if (!$adminChatId) {
            BotHelper::sendMessage($bot, '❌ شناسه کاربر نامعتبر است. لطفاً chat_id عددی وارد کنید.');
            return;
        }
        
        // Check if already admin
        $exists = PsychologyTestBotAdmin::where('psychology_test_bot_id', $psychologyTestBot->id)
            ->where('chat_id', $adminChatId)
            ->where('origin', $type)
            ->exists();
        
        if ($exists) {
            BotHelper::sendMessage($bot, "❌ این کاربر قبلاً ادمین است.");
            return;
        }
        
        // Add admin
        PsychologyTestBotAdmin::create([
            'psychology_test_bot_id' => $psychologyTestBot->id,
            'chat_id' => $adminChatId,
            'origin' => $type,
            'is_creator' => false,
        ]);
        
        BotHelper::sendMessage($bot, "✅ ادمین با موفقیت اضافه شد.\n\nشناسه: {$adminChatId}");
        
        Log::info('Psychology Test Bot - Admin added', [
            'psychology_test_bot_id' => $psychologyTestBot->id,
            'added_by' => $chatId,
            'new_admin_chat_id' => $adminChatId,
        ]);
    }

    /**
     * Handle results command - show results to admins
     * 
     * @param Telegram $bot
     * @param string $type
     * @param PsychologyTestBot $psychologyTestBot
     * @return void
     */
    private function handleResults(Telegram $bot, string $type, PsychologyTestBot $psychologyTestBot): void
    {
        $chatId = $bot->ChatID();
        
        // Check if user is admin
        $isAdmin = PsychologyTestBotAdmin::where('psychology_test_bot_id', $psychologyTestBot->id)
            ->where('chat_id', $chatId)
            ->where('origin', $type)
            ->exists();
        
        if (!$isAdmin) {
            BotHelper::sendMessage($bot, '❌ شما دسترسی به این دستور ندارید. فقط ادمین‌ها می‌توانند نتایج را مشاهده کنند.');
            return;
        }
        
        // Get all results
        $results = PsychologyTestResult::where('psychology_test_bot_id', $psychologyTestBot->id)
            ->orderBy('completed_at', 'desc')
            ->limit(50) // Limit to last 50 results
            ->get();
        
        if ($results->isEmpty()) {
            BotHelper::sendMessage($bot, '📊 هنوز هیچ نتیجه‌ای ثبت نشده است.');
            return;
        }
        
        $message = "📊 نتایج تست‌ها:\n\n";
        $message .= "تعداد کل: " . $results->count() . "\n\n";
        
        foreach ($results->take(10) as $result) {
            $message .= "👤 Chat ID: {$result->chat_id}\n";
            $message .= "📅 تاریخ: " . $result->completed_at->format('Y-m-d H:i') . "\n";
            $message .= "📊 امتیازات:\n";
            
            foreach ($result->result_data as $categoryName => $categoryResult) {
                $message .= "  • {$categoryName}: {$categoryResult['score']}%\n";
            }
            
            $message .= "\n";
        }
        
        if ($results->count() > 10) {
            $message .= "... و " . ($results->count() - 10) . " نتیجه دیگر";
        }
        
        BotHelper::sendMessage($bot, $message);
        
        Log::info('Psychology Test Bot - Results viewed', [
            'psychology_test_bot_id' => $psychologyTestBot->id,
            'viewed_by' => $chatId,
            'results_count' => $results->count(),
        ]);
    }
}
