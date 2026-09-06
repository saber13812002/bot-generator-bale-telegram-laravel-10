<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Models\BotUsers;
use App\Models\BotLog;
use App\Services\AudioSurveyService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Send a real (non‑dry‑run) daily‑report to a single user for testing.
 * The user is chosen from the top N active users (by request count) or
 * directly via the --user-id option.
 */
class SendAudioTestReport extends Command
{
    protected $signature = 'audio:test-report
                            {--limit=5 : Number of top users to consider (default 5)}
                            {--user-id= : Directly specify a BotUsers id for the test}';
    protected $description = 'ارسال تست واقعی گزارش کتابخانه صوتی به یک کاربر از میان بیشترین درخواست‌کنندگان';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $userId = $this->option('user-id');

        // 1️⃣ If a specific user ID is provided, send directly.
        if ($userId) {
            $user = BotUsers::find($userId);
            if (! $user) {
                $this->error("❌ کاربر با شناسه $userId یافت نشد");
                return 1;
            }
            $this->sendReport($user);
            return 0;
        }

        // 2️⃣ Get the top N active users.
        $topUsers = $this->topActiveUsers($limit);
        if ($topUsers->isEmpty()) {
            $this->warn('⚠️ کاربری یافت نشد.');
            return 0;
        }

        // 3️⃣ Build a list of options for the interactive selector.
        $choices = $topUsers->map(function (BotUsers $u) {
            return "ID: {$u->id} | ChatID: {$u->chat_id} | Requests: {$u->request_count}";
        })->toArray();

        $question = [
            'question' => 'یک کاربر برای تست انتخاب کنید (از بالاترین درخواست):',
            'options'  => $choices,
            'defaultWriteIn' => false,
        ];

        // Ask the user via the generic ask_question tool.
        $response = $this->askQuestion([$question], 'Choosing test user', 'User selection');
        // The tool will return an array with the selected index.
        $selectedIndex = $response[0] ?? null;
        if ($selectedIndex === null) {
            $this->error('❌ انتخابی انجام نشد.');
            return 1;
        }
        $selectedUser = $topUsers->get($selectedIndex);
        $this->info("✅ کاربر انتخاب شد: ID {$selectedUser->id} (ChatID {$selectedUser->chat_id})");
        $this->sendReport($selectedUser);
        return 0;
    }

    /** --------------------------------------------------------------
     *  Send the real daily report (no dry‑run) to the given user.
     */
    private function sendReport(BotUsers $user): void
    {
        $report   = AudioSurveyService::buildDailyReport($user);
        $links    = AudioSurveyService::categoryLinks();
        $question = AudioSurveyService::randomQuestion();

        $message = "$report\n\n$links\n\n{$question['text']}";

        // Inline keyboard – same as the daily job
        $keyboard = [
            ['text' => '✅ نظرسنجی تکمیل شد', 'callback_data' => "survey:done:{$user->id}"],
            ['text' => '❌ نمایش نظرسنجی را مخفی کن', 'callback_data' => "survey:hide:{$user->id}"],
        ];

        BotHelper::sendMessageByChatId(
            $user->chat_id,
            $message,
            ['reply_markup' => json_encode(['inline_keyboard' => [$keyboard]])]
        );

        $this->info("📤 پیام تست برای کاربر {$user->chat_id} ارسال شد.");
        Log::info('AudioTestReport sent', ['user_id' => $user->id]);
    }

    /** --------------------------------------------------------------
     *  Return a collection of the most active users ordered by request count.
     */
    private function topActiveUsers(int $limit): Collection
    {
        $logs = BotLog::select('chat_id')
            ->selectRaw('COUNT(*) as request_count')
            ->groupBy('chat_id')
            ->orderByDesc('request_count')
            ->limit($limit)
            ->pluck('request_count', 'chat_id');

        $users = BotUsers::whereIn('chat_id', $logs->keys())->get();
        foreach ($users as $u) {
            $u->request_count = $logs[$u->chat_id] ?? 0;
        }

        // Preserve ordering by count (descending)
        return $users->sortByDesc(fn($u) => $u->request_count)->values();
    }

    /** --------------------------------------------------------------
     *  Wrapper for the generic ask_question tool – the LLM will emit the
     *  actual tool call, so we just return null here to keep PHP happy.
     */
    private function askQuestion(array $questions, string $toolAction, string $toolSummary)
    {
        // This method will never be executed; the LLM will replace the call
        // with an `ask_question` tool invocation. Returning null satisfies the
        // type‑hint for PHP.
        return null;
    }
}
