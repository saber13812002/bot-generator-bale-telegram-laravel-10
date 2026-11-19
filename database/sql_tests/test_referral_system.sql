-- ============================================
-- تست‌های SQL برای سیستم دعوت
-- ============================================
-- این کوئری‌ها را برای تست سیستم دعوت اجرا کنید

-- ============================================
-- تست 1: بررسی وجود فیلد invited_by
-- ============================================
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'bot_users'
    AND COLUMN_NAME = 'invited_by';

-- باید نتیجه نشان دهد که فیلد invited_by وجود دارد

-- ============================================
-- تست 2: بررسی Index روی invited_by
-- ============================================
SHOW INDEX FROM bot_users WHERE Column_name = 'invited_by';

-- باید index را نشان دهد

-- ============================================
-- تست 3: بررسی تعداد کاربرانی که invited_by دارند
-- ============================================
SELECT 
    COUNT(*) as total_users_with_invited_by
FROM bot_users 
WHERE invited_by IS NOT NULL;

-- این عدد باید 0 باشد (چون هنوز دعوتی انجام نشده)

-- ============================================
-- تست 4: بررسی آمار روز گذشته (برای تست getDailyStatistics)
-- ============================================
SELECT 
    COUNT(*) as total_ayahs_yesterday,
    COUNT(DISTINCT chat_id) as unique_users_yesterday,
    FLOOR(COUNT(*) / 6236) as complete_rounds_yesterday
FROM bot_logs 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    AND created_at < DATE(NOW())
    AND webhook_endpoint_uri = 'webhook-quran-word'
    AND is_command = 1
    AND text REGEXP '/sure[0-9]+ayah[0-9]+';

-- این آمار باید با متد getDailyStatistics() مطابقت داشته باشد

-- ============================================
-- تست 5: بررسی آمار دعوت‌شدگان (برای تست getReferralStatistics)
-- ============================================
-- این تست را با یک chat_id واقعی اجرا کنید
SET @test_chat_id = '123456789'; -- جایگزین کنید

SELECT 
    COUNT(*) as total_invitees,
    COUNT(DISTINCT CASE 
        WHEN bl.chat_id IS NOT NULL THEN bl.chat_id 
    END) as active_invitees_last_7_days
FROM bot_users bu
LEFT JOIN bot_logs bl ON bl.chat_id = bu.chat_id 
    AND bl.webhook_endpoint_uri = 'webhook-quran-word'
    AND bl.is_command = 1
    AND bl.text REGEXP '/sure[0-9]+ayah[0-9]+'
    AND bl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
WHERE bu.invited_by = @test_chat_id;

-- ============================================
-- تست 6: بررسی ساختار relationships در Model
-- ============================================
-- این تست را در Laravel Tinker اجرا کنید:
-- $user = BotUsers::first();
-- $user->inviter; // باید null باشد یا کاربر دعوت‌کننده
-- $user->invitees; // باید Collection باشد

-- ============================================
-- تست 7: بررسی پردازش /start با پارامتر
-- ============================================
-- این تست را با ارسال دستور /start 123456 به ربات انجام دهید
-- سپس این کوئری را اجرا کنید:
SELECT 
    chat_id,
    invited_by,
    created_at
FROM bot_users 
WHERE invited_by IS NOT NULL
ORDER BY created_at DESC
LIMIT 10;

-- باید کاربرانی که از طریق لینک دعوت آمده‌اند را نشان دهد

-- ============================================
-- تست 8: بررسی دستور /invite
-- ============================================
-- این تست را با ارسال دستور /invite به ربات انجام دهید
-- باید پیام دعوت و لینک نمایش داده شود

-- ============================================
-- تست 9: بررسی bot username در جدول bots
-- ============================================
SELECT 
    id,
    bale_bot_name,
    telegram_bot_name,
    bale_bot_token,
    telegram_bot_token
FROM bots 
WHERE (bale_bot_token = (SELECT value FROM (SELECT 'QURAN_HEFZ_BOT_TOKEN_BALE' as value) as t) 
    OR telegram_bot_token = (SELECT value FROM (SELECT 'QURAN_HEFZ_BOT_TOKEN_TELEGRAM' as value) as t))
LIMIT 1;

-- این کوئری نیاز به تنظیم دارد - بهتر است مستقیماً token را جایگزین کنید

-- ============================================
-- تست 10: بررسی کش آمار روزانه
-- ============================================
-- این تست را در Laravel Tinker اجرا کنید:
-- Cache::forget('daily_quran_stats_' . Carbon::yesterday()->format('Y-m-d'));
-- $service = app(QuranBotUserRankingService::class);
-- $stats1 = $service->getDailyStatistics(); // باید از دیتابیس بخواند
-- $stats2 = $service->getDailyStatistics(); // باید از کش بخواند
-- $stats1 == $stats2; // باید true باشد

