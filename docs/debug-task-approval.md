# راهنمای دیباگ ربات تایید ماموریت‌ها

## مشکل
ربات تایید ماموریت‌ها هیچ پاسخی نمی‌دهد.

## روش‌های دیباگ

### 1. استفاده از Artisan Command (پیشنهادی)

ساده‌ترین روش استفاده از دستور Artisan است:

```bash
php artisan debug:task-approval
```

برای نمایش جزئیات بیشتر:

```bash
php artisan debug:task-approval --detail
```

این دستور اطلاعات زیر را نمایش می‌دهد:
- تعداد تسک‌های در انتظار تایید
- تسک‌هایی که approval_message_id ندارند (مشکل دارند)
- آمار کلی تسک‌ها
- بررسی تنظیمات محیطی (.env)
- آخرین تسک‌ها (با فلگ --detail)

### 2. استفاده از کوئری‌های SQL

فایل `queries_task_approval_debug.sql` شامل کوئری‌های مفید برای بررسی است.

#### نحوه استفاده:

1. وارد دیتابیس شوید:
```bash
php artisan db
```

یا از MySQL Client:
```bash
mysql -u username -p database_name
```

2. کوئری‌های مورد نیاز را اجرا کنید:

**بررسی تسک‌های در انتظار تایید:**
```sql
SELECT 
    id,
    task_name,
    assigned_user_id,
    task_status,
    approval_message_id,
    points,
    final_link,
    created_at
FROM tasks
WHERE task_status = 'pending_approval'
ORDER BY created_at DESC;
```

**بررسی تسک‌هایی که پیام به گروه ارسال نشده:**
```sql
SELECT 
    t.id,
    t.task_name,
    t.assigned_user_id,
    t.approval_message_id,
    p.first_name,
    p.last_name,
    t.created_at
FROM tasks t
LEFT JOIN personnels p ON t.assigned_user_id = p.id
WHERE t.task_status = 'pending_approval'
    AND t.approval_message_id IS NULL
ORDER BY t.created_at DESC;
```

**بررسی آمار کلی:**
```sql
SELECT 
    task_status,
    COUNT(*) as count
FROM tasks
GROUP BY task_status;
```

### 3. بررسی لاگ‌ها

لاگ‌های Laravel را بررسی کنید:

```bash
tail -f storage/logs/laravel.log | grep "Task Approval"
```

یا در Windows PowerShell:
```powershell
Get-Content storage/logs/laravel.log -Tail 100 | Select-String "Task Approval"
```

### 4. بررسی Webhook

بررسی کنید که webhook ربات تایید ماموریت درست ست شده باشد:

```bash
# برای Telegram
curl https://api.telegram.org/bot{TOKEN}/getWebhookInfo

# برای Bale
curl https://tapi.bale.ai/bot{TOKEN}/getWebhookInfo
```

یا از کد PHP:
```php
use App\Helpers\BotHelper;

$token = env('MISSION_BOT_TOKEN_TELEGRAM');
$type = 'telegram';
$webhookInfo = BotHelper::checkWebhookInfo($token, $type);
dd($webhookInfo);
```

## مشکلات رایج و راه‌حل‌ها

### مشکل 1: تسک‌ها approval_message_id ندارند

**علت:** پیام به گروه تایید ارسال نشده است.

**راه‌حل:**
1. بررسی کنید که `MISSION_APPROVAL_GROUP_CHAT_ID` در `.env` ست شده باشد
2. بررسی کنید که `MISSION_BOT_TOKEN_TELEGRAM` یا `MISSION_BOT_TOKEN_BALE` درست باشد
3. بررسی لاگ‌ها برای خطاهای ارسال پیام
4. بررسی کنید که متد `sendToApprovalGroup` در `MissionBotController` فراخوانی می‌شود

### مشکل 2: ربات هیچ پاسخی نمی‌دهد

**علت‌های احتمالی:**
1. Webhook ست نشده یا اشتباه است
2. Route درست کار نمی‌کند
3. `MISSION_APPROVAL_GROUP_CHAT_ID` اشتباه است
4. Reply به پیام درست تشخیص داده نمی‌شود

**راه‌حل:**
1. بررسی webhook با دستور بالا
2. بررسی route: `/api/webhook-task-approval`
3. بررسی لاگ‌ها برای خطاها
4. بررسی `chat_id` گروه تایید

### مشکل 3: Reply به پیام کار نمی‌کند

**علت:** `reply_to_message_id` درست خوانده نمی‌شود.

**راه‌حل:**
1. بررسی کنید که پیام واقعاً reply است
2. بررسی لاگ‌ها برای `reply_to_message_id`
3. بررسی کنید که `approval_message_id` در دیتابیس درست ذخیره شده باشد

### مشکل 4: تسک پیدا نمی‌شود

**علت:** `approval_message_id` در دیتابیس با `reply_to_message_id` در webhook مطابقت ندارد.

**راه‌حل:**
1. بررسی کنید که `approval_message_id` درست ذخیره شده باشد
2. بررسی کنید که `task_status` برابر `pending_approval` است
3. بررسی لاگ‌ها برای `reply_to_message_id` و `approval_message_id`

## چک‌لیست بررسی

- [ ] `MISSION_APPROVAL_GROUP_CHAT_ID` در `.env` ست شده است
- [ ] `MISSION_BOT_TOKEN_TELEGRAM` یا `MISSION_BOT_TOKEN_BALE` در `.env` ست شده است
- [ ] Webhook ربات تایید ماموریت درست ست شده است
- [ ] Route `/api/webhook-task-approval` درست کار می‌کند
- [ ] تسک‌ها `approval_message_id` دارند
- [ ] `task_status` تسک‌ها `pending_approval` است
- [ ] Reply به پیام درست کار می‌کند
- [ ] لاگ‌ها خطایی نشان نمی‌دهند

## تست دستی

برای تست دستی ربات:

1. یک تسک با وضعیت `pending_approval` ایجاد کنید
2. مطمئن شوید که `approval_message_id` دارد
3. در گروه تایید، به پیام reply کنید با کلمه "تایید"
4. بررسی کنید که تسک تایید می‌شود

یا از curl برای تست webhook:

```bash
curl -X POST https://your-domain.com/api/webhook-task-approval \
  -H "Content-Type: application/json" \
  -d '{
    "origin": "telegram",
    "update": {
      "message": {
        "chat": {
          "id": -1001234567890
        },
        "message_id": 123,
        "text": "تایید",
        "reply_to_message": {
          "message_id": 456
        }
      }
    }
  }'
```

## نکات مهم

1. **chat_id گروه باید منفی باشد** (گروه‌ها chat_id منفی دارند)
2. **approval_message_id باید درست ذخیره شود** وقتی پیام به گروه ارسال می‌شود
3. **reply_to_message_id باید درست خوانده شود** از webhook
4. **task_status باید pending_approval باشد** برای اینکه تسک قابل تایید باشد

## فایل‌های مرتبط

- Controller: `app/Http/Controllers/TaskApprovalController.php`
- Model: `app/Models/Task.php`
- Route: `routes/api.php` (خط 104)
- Command: `app/Console/Commands/DebugTaskApproval.php`
- SQL Queries: `queries_task_approval_debug.sql`

