<?php

namespace App\Services;

use App\Models\BotUsers;

/**
 * AudioSurveyService
 *
 * Provides simple utilities for the audio library bot test report.
 * This is a minimal implementation sufficient for the test command.
 */
class AudioSurveyService
{
    /**
     * Build a daily report string for the given user.
     * In a real implementation this would aggregate the user's podcast progress.
     */
    public static function buildDailyReport(BotUsers $user): string
    {
        // Placeholder: list the user's chat ID and a static message.
        return "📚 گزارش روزانه کتابخانه صوتی برای کاربر {$user->chat_id}\n" .
               "⚡ تعداد پادکست‌های شروع‌شده: 0\n" .
               "⚡ پیشرفت فعلی: –";
    }

    /**
     * Return a list of category links (as a simple text block).
     */
    public static function categoryLinks(): string
    {
        return "📂 دسته‌بندی‌ها:\n" .
               "1️⃣ /category1\n" .
               "2️⃣ /category2\n" .
               "3️⃣ /category3";
    }

    /**
     * Return a random question for the survey.
     * Here we simply return a static question; a real implementation could randomize.
     */
    public static function randomQuestion(): array
    {
        return [
            'text' => '❓ نظرتان درباره محتوای صوتی چیست؟',
        ];
    }
}
