-- ============================================
-- تست‌های SQL برای سیستم دعوت ربات قرآن
-- ============================================
-- این فایل شامل کوئری‌های SQL برای تست و بررسی سیستم دعوت است
-- می‌توانید این کوئری‌ها را در MySQL/MariaDB اجرا کنید

-- ============================================
-- 1. بررسی لاگ‌های `/start` با پارامتر
-- ============================================
-- این کوئری لاگ‌های `/start` که دارای پارامتر هستند را نشان می‌دهد
-- پارامتر معمولاً chat_id دعوت‌کننده است
SELECT 
    id, 
    chat_id, 
    text, 
    type, 
    created_at,
    SUBSTRING_INDEX(SUBSTRING_INDEX(text, ' ', 2), ' ', -1) as extracted_referral_code
FROM bot_logs 
WHERE text LIKE '/start %' 
    AND is_command = 1 
    AND webhook_endpoint_uri = 'webhook-quran-word'
ORDER BY created_at DESC 
LIMIT 100;

-- ============================================
-- 2. بررسی تعداد دعوت‌شدگان هر کاربر
-- ============================================
-- این کوئری نشان می‌دهد هر کاربر چند نفر را دعوت کرده است
SELECT 
    bu.invited_by as inviter_chat_id,
    COUNT(*) as total_invitees,
    COUNT(DISTINCT bu.origin) as invitees_by_platform
FROM bot_users bu
WHERE bu.invited_by IS NOT NULL
GROUP BY bu.invited_by
ORDER BY total_invitees DESC
LIMIT 20;

-- ============================================
-- 3. بررسی آمار دعوت‌شدگان فعال در 7 روز گذشته
-- ============================================
-- این کوئری نشان می‌دهد کدام دعوت‌کنندگان بیشترین دعوت‌شده فعال دارند
SELECT 
    bu.invited_by as inviter_chat_id,
    COUNT(DISTINCT bu.chat_id) as total_invitees,
    COUNT(DISTINCT CASE 
        WHEN bl.chat_id IS NOT NULL THEN bl.chat_id 
    END) as active_invitees_last_7_days,
    COUNT(CASE 
        WHEN bl.chat_id IS NOT NULL THEN 1 
    END) as total_ayahs_by_invitees_last_7_days
FROM bot_users bu
LEFT JOIN bot_logs bl ON bl.chat_id = bu.chat_id 
    AND bl.webhook_endpoint_uri = 'webhook-quran-word'
    AND bl.is_command = 1
    AND bl.text REGEXP '/sure[0-9]+ayah[0-9]+'
    AND bl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
WHERE bu.invited_by IS NOT NULL
GROUP BY bu.invited_by
ORDER BY active_invitees_last_7_days DESC, total_invitees DESC
LIMIT 20;

-- ============================================
-- 4. بررسی آمار روز گذشته (تعداد کاربران و آیات)
-- ============================================
-- این کوئری آمار کلی روز گذشته را نشان می‌دهد
SELECT 
    DATE(created_at) as date,
    COUNT(DISTINCT chat_id) as unique_users,
    COUNT(*) as total_ayahs,
    FLOOR(COUNT(*) / 6236) as complete_rounds,
    ROUND(COUNT(*) / 6236, 2) as partial_rounds
FROM bot_logs 
WHERE webhook_endpoint_uri = 'webhook-quran-word'
    AND is_command = 1
    AND text REGEXP '/sure[0-9]+ayah[0-9]+'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
GROUP BY DATE(created_at);

-- ============================================
-- 5. بررسی آمار هفتگی (7 روز گذشته)
-- ============================================
SELECT 
    COUNT(DISTINCT chat_id) as unique_users,
    COUNT(*) as total_ayahs,
    FLOOR(COUNT(*) / 6236) as complete_rounds,
    ROUND(COUNT(*) / 6236, 2) as partial_rounds
FROM bot_logs 
WHERE webhook_endpoint_uri = 'webhook-quran-word'
    AND is_command = 1
    AND text REGEXP '/sure[0-9]+ayah[0-9]+'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- ============================================
-- 6. بررسی کاربرانی که دعوت شده‌اند اما invited_by ندارند
-- ============================================
-- این کوئری کاربرانی را نشان می‌دهد که احتمالاً از طریق لینک دعوت آمده‌اند
-- اما invited_by در bot_users ثبت نشده است
SELECT 
    bl.chat_id,
    bl.text,
    bl.type,
    bl.created_at,
    bu.invited_by,
    CASE 
        WHEN bu.id IS NULL THEN 'کاربر در bot_users وجود ندارد'
        WHEN bu.invited_by IS NULL THEN 'invited_by خالی است'
        ELSE 'invited_by ثبت شده'
    END as status
FROM bot_logs bl
LEFT JOIN bot_users bu ON bu.chat_id = bl.chat_id AND bu.origin = bl.type
WHERE bl.text LIKE '/start %'
    AND bl.is_command = 1
    AND bl.webhook_endpoint_uri = 'webhook-quran-word'
    AND SUBSTRING_INDEX(SUBSTRING_INDEX(bl.text, ' ', 2), ' ', -1) REGEXP '^[0-9]+$'
    AND (bu.id IS NULL OR bu.invited_by IS NULL)
ORDER BY bl.created_at DESC
LIMIT 50;

-- ============================================
-- 7. بررسی آمار دعوت‌شدگان یک کاربر خاص
-- ============================================
-- این کوئری را با chat_id کاربر مورد نظر اجرا کنید
-- مثال: SET @user_chat_id = '123456789';
SET @user_chat_id = '123456789'; -- این را با chat_id واقعی جایگزین کنید

SELECT 
    @user_chat_id as inviter_chat_id,
    COUNT(DISTINCT bu.chat_id) as total_invitees,
    COUNT(DISTINCT bu.origin) as invitees_by_platform,
    COUNT(DISTINCT CASE 
        WHEN bl.chat_id IS NOT NULL THEN bl.chat_id 
    END) as active_invitees_last_7_days,
    COUNT(CASE 
        WHEN bl.chat_id IS NOT NULL THEN 1 
    END) as total_ayahs_by_invitees_last_7_days,
    COUNT(CASE 
        WHEN bl_user.chat_id IS NOT NULL THEN 1 
    END) as total_ayahs_by_inviter_last_7_days
FROM bot_users bu
LEFT JOIN bot_logs bl ON bl.chat_id = bu.chat_id 
    AND bl.webhook_endpoint_uri = 'webhook-quran-word'
    AND bl.is_command = 1
    AND bl.text REGEXP '/sure[0-9]+ayah[0-9]+'
    AND bl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
LEFT JOIN bot_logs bl_user ON bl_user.chat_id = @user_chat_id
    AND bl_user.webhook_endpoint_uri = 'webhook-quran-word'
    AND bl_user.is_command = 1
    AND bl_user.text REGEXP '/sure[0-9]+ayah[0-9]+'
    AND bl_user.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
WHERE bu.invited_by = @user_chat_id;

-- ============================================
-- 8. بررسی توزیع دعوت‌شدگان بر اساس پلتفرم
-- ============================================
SELECT 
    bu.origin as platform,
    COUNT(*) as total_invitees,
    COUNT(DISTINCT bu.invited_by) as unique_inviters
FROM bot_users bu
WHERE bu.invited_by IS NOT NULL
GROUP BY bu.origin
ORDER BY total_invitees DESC;

-- ============================================
-- 9. بررسی کاربرانی که بیشترین فعالیت را دارند
-- ============================================
-- این کوئری کاربرانی را نشان می‌دهد که بیشترین آیات را در 7 روز گذشته خوانده‌اند
SELECT 
    bl.chat_id,
    COUNT(*) as total_ayahs_last_7_days,
    bu.invited_by,
    bu.origin
FROM bot_logs bl
LEFT JOIN bot_users bu ON bu.chat_id = bl.chat_id AND bu.origin = bl.type
WHERE bl.webhook_endpoint_uri = 'webhook-quran-word'
    AND bl.is_command = 1
    AND bl.text REGEXP '/sure[0-9]+ayah[0-9]+'
    AND bl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY bl.chat_id, bu.invited_by, bu.origin
ORDER BY total_ayahs_last_7_days DESC
LIMIT 20;

-- ============================================
-- 10. بررسی کاربرانی که از طریق لینک دعوت آمده‌اند
-- ============================================
-- این کوئری کاربرانی را نشان می‌دهد که از طریق لینک دعوت آمده‌اند
SELECT 
    bu.chat_id,
    bu.invited_by,
    bu.origin,
    bu.created_at as user_created_at,
    COUNT(DISTINCT bl.id) as total_ayahs_read
FROM bot_users bu
LEFT JOIN bot_logs bl ON bl.chat_id = bu.chat_id 
    AND bl.webhook_endpoint_uri = 'webhook-quran-word'
    AND bl.is_command = 1
    AND bl.text REGEXP '/sure[0-9]+ayah[0-9]+'
WHERE bu.invited_by IS NOT NULL
GROUP BY bu.chat_id, bu.invited_by, bu.origin, bu.created_at
ORDER BY bu.created_at DESC
LIMIT 20;

