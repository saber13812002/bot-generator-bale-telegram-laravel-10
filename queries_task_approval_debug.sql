-- ============================================
-- کوئری‌های دیباگ ربات تایید ماموریت‌ها
-- ============================================

-- 1. بررسی تمام تسک‌های در انتظار تایید
-- این کوئری تمام تسک‌هایی که باید تایید شوند را نشان می‌دهد
SELECT 
    id,
    task_name,
    assigned_user_id,
    task_status,
    approval_message_id,
    points,
    final_link,
    created_at,
    updated_at
FROM tasks
WHERE task_status = 'pending_approval'
ORDER BY created_at DESC;

-- 2. بررسی تسک‌هایی که approval_message_id دارند (پیام به گروه ارسال شده)
-- این تسک‌ها باید در گروه تایید قابل reply باشند
SELECT 
    t.id,
    t.task_name,
    t.assigned_user_id,
    t.task_status,
    t.approval_message_id,
    t.points,
    t.final_link,
    p.first_name,
    p.last_name,
    p.national_code,
    t.created_at,
    t.updated_at
FROM tasks t
LEFT JOIN personnels p ON t.assigned_user_id = p.id
WHERE t.task_status = 'pending_approval'
    AND t.approval_message_id IS NOT NULL
ORDER BY t.created_at DESC;

-- 3. بررسی تسک‌هایی که approval_message_id ندارند (پیام به گروه ارسال نشده)
-- این تسک‌ها مشکل دارند و باید بررسی شوند
SELECT 
    t.id,
    t.task_name,
    t.assigned_user_id,
    t.task_status,
    t.approval_message_id,
    t.points,
    t.final_link,
    p.first_name,
    p.last_name,
    p.national_code,
    t.created_at,
    t.updated_at
FROM tasks t
LEFT JOIN personnels p ON t.assigned_user_id = p.id
WHERE t.task_status = 'pending_approval'
    AND t.approval_message_id IS NULL
ORDER BY t.created_at DESC;

-- 4. بررسی تمام وضعیت‌های تسک‌ها (آمار کلی)
SELECT 
    task_status,
    COUNT(*) as count,
    COUNT(CASE WHEN approval_message_id IS NOT NULL THEN 1 END) as with_message_id,
    COUNT(CASE WHEN approval_message_id IS NULL THEN 1 END) as without_message_id
FROM tasks
GROUP BY task_status
ORDER BY count DESC;

-- 5. بررسی تسک‌های اخیر (آخرین 20 تسک)
SELECT 
    t.id,
    t.task_name,
    t.assigned_user_id,
    t.task_status,
    t.approval_message_id,
    t.points,
    t.final_link,
    p.first_name,
    p.last_name,
    p.national_code,
    t.created_at,
    t.updated_at,
    t.approved_at,
    t.rejected_at,
    t.rejection_reason
FROM tasks t
LEFT JOIN personnels p ON t.assigned_user_id = p.id
ORDER BY t.created_at DESC
LIMIT 20;

-- 6. بررسی تسک‌های تایید شده و رد شده
SELECT 
    t.id,
    t.task_name,
    t.task_status,
    t.approved_at,
    t.rejected_at,
    t.rejection_reason,
    t.approved_by_chat_id,
    p.first_name,
    p.last_name,
    t.created_at
FROM tasks t
LEFT JOIN personnels p ON t.assigned_user_id = p.id
WHERE t.task_status IN ('approved', 'rejected')
ORDER BY COALESCE(t.approved_at, t.rejected_at) DESC
LIMIT 20;

-- 7. بررسی تسک‌هایی که approval_message_id دارند اما هنوز pending هستند
-- این تسک‌ها باید در گروه تایید قابل reply باشند
SELECT 
    t.id,
    t.task_name,
    t.approval_message_id,
    t.task_status,
    t.points,
    p.first_name,
    p.last_name,
    p.national_code,
    t.created_at,
    TIMESTAMPDIFF(HOUR, t.created_at, NOW()) as hours_since_created
FROM tasks t
LEFT JOIN personnels p ON t.assigned_user_id = p.id
WHERE t.task_status = 'pending_approval'
    AND t.approval_message_id IS NOT NULL
ORDER BY t.created_at DESC;

-- 8. بررسی ربات‌های موجود در جدول bots
-- برای بررسی اینکه آیا ربات تایید ماموریت در دیتابیس ثبت شده یا نه
SELECT 
    id,
    token,
    messenger,
    type,
    language,
    bot_mother_id,
    webhook_url,
    is_active,
    created_at,
    updated_at
FROM bots
WHERE type LIKE '%mission%' OR type LIKE '%approval%' OR type LIKE '%task%'
ORDER BY created_at DESC;

-- 9. بررسی لاگ‌های اخیر (اگر جدول logs وجود دارد)
-- این کوئری برای بررسی لاگ‌های مربوط به task approval است
-- توجه: ممکن است جدول logs وجود نداشته باشد، در این صورت از فایل لاگ استفاده کنید
SELECT 
    id,
    level,
    message,
    context,
    created_at
FROM logs
WHERE message LIKE '%Task Approval%' 
    OR message LIKE '%تایید%'
    OR context LIKE '%approval%'
ORDER BY created_at DESC
LIMIT 50;

-- 10. بررسی BotUsers برای پرسنل‌هایی که تسک دارند
-- برای بررسی اینکه آیا پرسنل‌ها در سیستم ربات ثبت شده‌اند یا نه
SELECT 
    bu.id,
    bu.chat_id,
    bu.origin,
    bu.settings,
    p.id as personnel_id,
    p.first_name,
    p.last_name,
    p.national_code,
    COUNT(t.id) as task_count
FROM bot_users bu
LEFT JOIN personnels p ON JSON_EXTRACT(bu.settings, '$.personnel_id') = p.id
LEFT JOIN tasks t ON t.assigned_user_id = p.id
WHERE JSON_EXTRACT(bu.settings, '$.personnel_id') IS NOT NULL
GROUP BY bu.id, bu.chat_id, bu.origin, p.id, p.first_name, p.last_name, p.national_code
ORDER BY task_count DESC;

-- 11. بررسی تسک‌هایی که باید به گروه تایید ارسال شوند اما ارسال نشده‌اند
-- این کوئری تسک‌هایی را نشان می‌دهد که status آن‌ها pending_approval است
-- اما approval_message_id ندارند (یعنی پیام به گروه ارسال نشده)
SELECT 
    t.id,
    t.task_name,
    t.assigned_user_id,
    t.task_status,
    t.approval_message_id,
    t.points,
    t.final_link,
    p.first_name,
    p.last_name,
    p.national_code,
    TIMESTAMPDIFF(MINUTE, t.created_at, NOW()) as minutes_since_created,
    t.created_at
FROM tasks t
LEFT JOIN personnels p ON t.assigned_user_id = p.id
WHERE t.task_status = 'pending_approval'
    AND t.approval_message_id IS NULL
    AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY t.created_at DESC;

-- 12. بررسی تعداد تسک‌های هر پرسنل بر اساس وضعیت
SELECT 
    p.id,
    p.first_name,
    p.last_name,
    p.national_code,
    COUNT(CASE WHEN t.task_status = 'pending_approval' THEN 1 END) as pending_count,
    COUNT(CASE WHEN t.task_status = 'approved' THEN 1 END) as approved_count,
    COUNT(CASE WHEN t.task_status = 'rejected' THEN 1 END) as rejected_count,
    COUNT(CASE WHEN t.task_status = 'in_progress' THEN 1 END) as in_progress_count,
    COUNT(CASE WHEN t.task_status = 'reserved' THEN 1 END) as reserved_count,
    COUNT(t.id) as total_tasks
FROM personnels p
LEFT JOIN tasks t ON t.assigned_user_id = p.id
GROUP BY p.id, p.first_name, p.last_name, p.national_code
HAVING total_tasks > 0
ORDER BY total_tasks DESC;

-- ============================================
-- نکات مهم برای دیباگ:
-- ============================================
-- 1. بررسی کنید که MISSION_APPROVAL_GROUP_CHAT_ID در .env ست شده باشد
-- 2. بررسی کنید که MISSION_BOT_TOKEN_TELEGRAM یا MISSION_BOT_TOKEN_BALE در .env ست شده باشد
-- 3. بررسی کنید که webhook ربات تایید ماموریت درست ست شده باشد
-- 4. بررسی کنید که route /api/webhook-task-approval درست کار می‌کند
-- 5. بررسی لاگ‌های Laravel برای خطاهای احتمالی
-- 6. بررسی کنید که approval_message_id درست ست می‌شود وقتی تسک به گروه ارسال می‌شود
-- 7. بررسی کنید که reply_to_message_id درست خوانده می‌شود از webhook

