<?php

namespace App\Console\Commands;

use App\Helpers\AdminHelper;
use App\Helpers\BotHelper;
use App\Models\QuranSearchSuggestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram;

class SendDailyQuranSuggestionToAdmins extends Command
{
    protected $signature = 'quran:send-daily-suggestion-to-admins
                            {--days=30 : تعداد روز گذشته برای انتخاب از پیشنهادات}';

    protected $description = 'ارسال یک پیشنهاد روزانه (جستجوی تک‌نتیجه یا کلیک‌شده) به ادمین‌های ربات قرآن';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $since = now()->subDays($days);

        $candidates = QuranSearchSuggestion::query()
            ->where('created_at', '>=', $since)
            ->where(function ($q) {
                $q->where('result_count', 1)
                    ->orWhere('source', QuranSearchSuggestion::SOURCE_CLICKED);
            })
            ->get();

        if ($candidates->isEmpty()) {
            $this->warn('هیچ پیشنهادی در بازه مشخص‌شده یافت نشد.');
            Log::info('[SendDailyQuranSuggestionToAdmins] No candidates', ['days' => $days]);
            return 0;
        }

        $suggestion = $candidates->random();
        $message = $this->buildSuggestionMessage($suggestion);

        $admins = AdminHelper::getAdmins();
        $admins = array_filter($admins); // remove nulls from env

        if (empty($admins)) {
            $this->warn('لیست ادمین‌ها خالی است.');
            return 0;
        }

        $baleToken = env('QURAN_HEFZ_BOT_TOKEN_BALE');
        $telegramToken = env('QURAN_HEFZ_BOT_TOKEN_TELEGRAM');

        $sent = 0;
        foreach ($admins as $chatId) {
            if (!$chatId) {
                continue;
            }
            try {
                if ($baleToken) {
                    $botBale = new Telegram($baleToken, 'bale');
                    BotHelper::sendMessageByChatId($botBale, (string) $chatId, $message);
                    $sent++;
                }
                if ($telegramToken) {
                    $botTelegram = new Telegram($telegramToken);
                    BotHelper::sendMessageByChatId($botTelegram, (string) $chatId, $message);
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::warning('[SendDailyQuranSuggestionToAdmins] Failed to send to admin', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("پیشنهاد روزانه به {$sent} ارسال فرستاده شد.");
        Log::info('[SendDailyQuranSuggestionToAdmins] Done', [
            'suggestion_id' => $suggestion->id,
            'sent_count' => $sent,
        ]);

        return 0;
    }

    private function buildSuggestionMessage(QuranSearchSuggestion $suggestion): string
    {
        $intro = "پیشنهاد روزانه برای ارسال به کاربران:\n\n";
        if ($suggestion->sura && $suggestion->aya) {
            $command = \App\Helpers\StringHelper::command_template_sure . $suggestion->sura . \App\Helpers\StringHelper::command_template_ayah . $suggestion->aya;
            return $intro . "دستور آیه:\n" . $command . "\n\nبا /// یا //// در ربات قرآن می‌توانید این را برای همه بفرستید.";
        }
        if (!empty($suggestion->search_phrase)) {
            return $intro . "عبارت جستجو:\n" . $suggestion->search_phrase . "\n\nبا /// یا //// در ربات قرآن می‌توانید این را برای همه بفرستید.";
        }
        return $intro . "منبع: کلیک روی آیه (شناسه پیشنهاد: {$suggestion->id}).";
    }
}
