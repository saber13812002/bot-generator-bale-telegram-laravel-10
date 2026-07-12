-- =============================================
-- 🔍 بررسی وضعیت ربات‌های قرآنی
-- مجموعه کوئری‌های SQL برای مشاهده وضعیت
-- =============================================

-- =============================================
-- 1️⃣ لیست کامل همه ربات‌های موجود در دیتابیس
-- =============================================
-- این کوئری همه ربات‌ها را با وضعیت تلگرام و بله نشان می‌دهد
SELECT 
    id,
    telegram_bot_name,
    telegram_bot_status,
    telegram_webhook_is_set,
    bale_bot_name,
    bale_bot_status,
    bale_webhook_is_set,
    bot_mother_id,
    endpoint_id,
    language_code,
    type,
    created_at,
    updated_at
FROM bots
ORDER BY id;

-- =============================================
-- 2️⃣ فقط ربات‌های قرآنی (بر اساس bot_mother_id=1)
-- =============================================
-- ربات‌های قرآنی که با bot_mother_id=1 ثبت شده‌اند
SELECT 
    id,
    COALESCE(telegram_bot_name, bale_bot_name) AS bot_name,
    CASE 
        WHEN telegram_bot_name IS NOT NULL THEN CONCAT('@', telegram_bot_name)
        WHEN bale_bot_name IS NOT NULL THEN CONCAT('@', bale_bot_name)
        ELSE 'بدون نام'
    END AS bot_username,
    language_code,
    type,
    telegram_bot_status AS telegram_status,
    bale_bot_status AS bale_status,
    telegram_webhook_is_set AS tg_webhook,
    bale_webhook_is_set AS bale_webhook,
    created_at
FROM bots
WHERE bot_mother_id = 1
   OR endpoint_id = 'webhook-quran-word'
   OR language_code IS NOT NULL
ORDER BY language_code, type;

-- =============================================
-- 3️⃣ وضعیت webhook برای ربات‌های تلگرام
-- =============================================
SELECT 
    id,
    telegram_bot_name,
    telegram_bot_status,
    telegram_webhook_is_set,
    CASE 
        WHEN telegram_webhook_is_set = 1 AND telegram_bot_status = 'Active' THEN '✅ فعال'
        WHEN telegram_webhook_is_set = 1 AND telegram_bot_status = 'DeActive' THEN '⚠️ وب‌هوک ست ولی غیرفعال'
        WHEN telegram_webhook_is_set = 0 THEN '❌ وب‌هوک ست نشده'
        ELSE 'نامشخص'
    END AS telegram_status_text,
    updated_at AS last_update
FROM bots
WHERE telegram_bot_name IS NOT NULL
   AND bot_mother_id = 1
ORDER BY telegram_bot_name;

-- =============================================
-- 4️⃣ وضعیت webhook برای ربات‌های بله
-- =============================================
SELECT 
    id,
    bale_bot_name,
    bale_bot_status,
    bale_webhook_is_set,
    CASE 
        WHEN bale_webhook_is_set = 1 AND bale_bot_status = 'Active' THEN '✅ فعال'
        WHEN bale_webhook_is_set = 1 AND bale_bot_status = 'DeActive' THEN '⚠️ وب‌هوک ست ولی غیرفعال'
        WHEN bale_webhook_is_set = 0 THEN '❌ وب‌هوک ست نشده'
        ELSE 'نامشخص'
    END AS bale_status_text,
    updated_at AS last_update
FROM bots
WHERE bale_bot_name IS NOT NULL
   AND bot_mother_id = 1
ORDER BY bale_bot_name;

-- =============================================
-- 5️⃣ تعداد کاربران هر ربات (از bot_users)
-- =============================================
SELECT 
    bu.bot_id,
    COALESCE(b.telegram_bot_name, b.bale_bot_name) AS bot_name,
    b.language_code,
    bu.origin,
    COUNT(*) AS total_users,
    SUM(CASE WHEN bu.status = 'active' THEN 1 ELSE 0 END) AS active_users,
    SUM(CASE WHEN bu.status = 'suspend' THEN 1 ELSE 0 END) AS suspended_users,
    MAX(bu.created_at) AS newest_user,
    MIN(bu.created_at) AS oldest_user
FROM bot_users bu
JOIN bots b ON b.id = bu.bot_id
WHERE b.bot_mother_id = 1
GROUP BY bu.bot_id, bu.origin, b.telegram_bot_name, b.bale_bot_name, b.language_code
ORDER BY total_users DESC;

-- =============================================
-- 6️⃣ تعداد کاربران بر اساس زبان (از bot_logs)
-- =============================================
-- این کوئری کاربران واقعی که از ربات استفاده کرده‌اند را نشان می‌دهد
SELECT 
    bl.language,
    bl.type AS platform,
    COUNT(DISTINCT bl.chat_id) AS unique_users,
    COUNT(*) AS total_requests,
    MAX(bl.created_at) AS last_activity,
    MIN(bl.created_at) AS first_activity
FROM bot_logs bl
WHERE bl.webhook_endpoint_uri = 'webhook-quran-word'
  AND bl.bot_mother_id = 1
GROUP BY bl.language, bl.type
ORDER BY unique_users DESC;

-- =============================================
-- 7️⃣ خلاصه وضعیت کلی ربات‌های قرآنی
-- =============================================
SELECT 
    'Telegram' AS platform,
    COUNT(*) AS total_bots,
    SUM(CASE WHEN telegram_webhook_is_set = 1 AND telegram_bot_status = 'Active' THEN 1 ELSE 0 END) AS active_webhooks,
    SUM(CASE WHEN telegram_webhook_is_set = 0 THEN 1 ELSE 0 END) AS missing_webhook,
    SUM(CASE WHEN telegram_bot_status = 'DeActive' THEN 1 ELSE 0 END) AS deactivated
FROM bots
WHERE telegram_bot_name IS NOT NULL
  AND bot_mother_id = 1

UNION ALL

SELECT 
    'Bale' AS platform,
    COUNT(*) AS total_bots,
    SUM(CASE WHEN bale_webhook_is_set = 1 AND bale_bot_status = 'Active' THEN 1 ELSE 0 END) AS active_webhooks,
    SUM(CASE WHEN bale_webhook_is_set = 0 THEN 1 ELSE 0 END) AS missing_webhook,
    SUM(CASE WHEN bale_bot_status = 'DeActive' THEN 1 ELSE 0 END) AS deactivated
FROM bots
WHERE bale_bot_name IS NOT NULL
  AND bot_mother_id = 1;

-- =============================================
-- 8️⃣ آخرین لاگ‌های ربات‌های قرآنی
-- =============================================
SELECT 
    bl.id,
    bl.language,
    bl.type,
    bl.text,
    bl.chat_id,
    bl.created_at
FROM bot_logs bl
WHERE bl.webhook_endpoint_uri = 'webhook-quran-word'
  AND bl.bot_mother_id = 1
ORDER BY bl.created_at DESC
LIMIT 50;

-- =============================================
-- 9️⃣ ربات‌هایی که در bot_users کاربر دارند ولی 
--    در bot_logs لاگی ندارند (شاید کار نمی‌کنند)
-- =============================================
SELECT 
    b.id,
    COALESCE(b.telegram_bot_name, b.bale_bot_name) AS bot_name,
    b.language_code,
    COUNT(bu.id) AS user_count
FROM bots b
JOIN bot_users bu ON bu.bot_id = b.id
WHERE b.bot_mother_id = 1
  AND b.id NOT IN (
      SELECT DISTINCT bl.bot_id 
      FROM bot_logs bl 
      WHERE bl.webhook_endpoint_uri = 'webhook-quran-word'
        AND bl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
  )
GROUP BY b.id, b.telegram_bot_name, b.bale_bot_name, b.language_code
ORDER BY user_count DESC;

-- =============================================
-- 🔟 ربات‌هایی با بیشترین تعامل (بر اساس لاگ)
-- =============================================
SELECT 
    bl.language,
    CASE 
        WHEN bl.language = 'ar-IQ' THEN 'عربی (Quran_Hifzbot)'
        WHEN bl.language = 'ur' THEN 'اردو (Quran_urdubot)'
        WHEN bl.language = 'zh-CN' THEN 'چینی (Gulanjing_yuedu_bot)'
        WHEN bl.language = 'es' THEN 'اسپانیایی (Coran_spanish_bot)'
        WHEN bl.language = 'de-DE' THEN 'آلمانی (KoranTextBot)'
        WHEN bl.language = 'fr' THEN 'فرانسوی (Coran_Texte_bot)'
        WHEN bl.language = 'en' THEN 'انگلیسی (Tilawat_Quran_Bot)'
        WHEN bl.language = 'fa' THEN 'فارسی (hefzaquran_word_daily_bot)'
        WHEN bl.language = 'ru' THEN 'روسی (Koran_chteniye_bot)'
        WHEN bl.language = 'tr' THEN 'ترکی (Kurani_Kerim_bot)'
        WHEN bl.language = 'he' THEN 'عبری (Quran_in_Hebrew_translation_BOT)'
        ELSE bl.language
    END AS bot_info,
    bl.type AS platform,
    COUNT(DISTINCT bl.chat_id) AS unique_users,
    COUNT(*) AS total_interactions,
    DATE_FORMAT(MAX(bl.created_at), '%Y-%m-%d') AS last_active_date
FROM bot_logs bl
WHERE bl.webhook_endpoint_uri = 'webhook-quran-word'
  AND bl.bot_mother_id = 1
  AND bl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY bl.language, bl.type
ORDER BY total_interactions DESC;

-- =============================================
-- 📊 گزارش کامل تمام ربات‌ها برای بررسی
-- =============================================
SELECT 
    '📋 گزارش کامل وضعیت ربات‌های قرآنی' AS report_title,
    NOW() AS generated_at;

-- کوئری ترکیبی نهایی
SELECT 
    COALESCE(b.telegram_bot_name, b.bale_bot_name) AS username,
    b.language_code,
    CASE 
        WHEN b.language_code = 'ar-IQ' THEN '🇸🇦 عربی'
        WHEN b.language_code = 'ur' THEN '🇵🇰 اردو'
        WHEN b.language_code = 'zh-CN' THEN '🇨🇳 چینی'
        WHEN b.language_code = 'es' THEN '🇪🇸 اسپانیایی'
        WHEN b.language_code = 'de-DE' THEN '🇩🇪 آلمانی'
        WHEN b.language_code = 'fr' THEN '🇫🇷 فرانسوی'
        WHEN b.language_code = 'en' THEN '🇬🇧 انگلیسی'
        WHEN b.language_code = 'fa' THEN '🇮🇷 فارسی'
        WHEN b.language_code = 'ru' THEN '🇷🇺 روسی'
        WHEN b.language_code = 'tr' THEN '🇹🇷 ترکی'
        WHEN b.language_code = 'he' THEN '🇮🇱 عبری'
        ELSE '🌍 سایر'
    END AS language_flag,
    b.type,
    CASE 
        WHEN b.type = 'telegram' THEN 
            CONCAT('https://t.me/', b.telegram_bot_name)
        WHEN b.type = 'bale' THEN 
            CONCAT('https://ble.ir/', b.bale_bot_name)
        ELSE '—'
    END AS bot_link,
    CASE 
        WHEN (b.type = 'telegram' AND b.telegram_webhook_is_set = 1 AND b.telegram_bot_status = 'Active') 
          OR (b.type = 'bale' AND b.bale_webhook_is_set = 1 AND b.bale_bot_status = 'Active')
        THEN '✅'
        ELSE '❌'
    END AS webhook_status,
    COALESCE(user_stats.user_count, 0) AS registered_users,
    COALESCE(log_stats.unique_users, 0) AS active_users_last_30d,
    COALESCE(log_stats.total_requests, 0) AS total_requests_30d,
    log_stats.last_active
FROM bots b
LEFT JOIN (
    SELECT bot_id, COUNT(*) AS user_count
    FROM bot_users
    GROUP BY bot_id
) user_stats ON user_stats.bot_id = b.id
LEFT JOIN (
    SELECT 
        bl.language,
        bl.type,
        COUNT(DISTINCT bl.chat_id) AS unique_users,
        COUNT(*) AS total_requests,
        MAX(bl.created_at) AS last_active
    FROM bot_logs bl
    WHERE bl.webhook_endpoint_uri = 'webhook-quran-word'
      AND bl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY bl.language, bl.type
) log_stats ON log_stats.language = b.language_code AND log_stats.type = b.type
WHERE b.bot_mother_id = 1
ORDER BY COALESCE(log_stats.unique_users, 0) DESC;
