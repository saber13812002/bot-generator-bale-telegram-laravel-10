---
name: افزودن دستورات آمار و help به ربات approval
overview: افزودن دستورات /help و آمار (/pending, /today, /top, /stats) به TaskApprovalController و نمایش تعداد تسک‌های pending در پیام تایید
todos:
  - id: add_pending_count_to_approval
    content: افزودن تعداد pending به پیام تایید در approveTask و approveMission
    status: completed
  - id: add_help_command
    content: افزودن دستور /help به TaskApprovalController
    status: completed
  - id: add_pending_command
    content: پیاده‌سازی دستور /pending برای نمایش تعداد و لیست pending
    status: completed
  - id: add_today_command
    content: پیاده‌سازی دستور /today برای آمار امروز
    status: completed
  - id: add_top_command
    content: پیاده‌سازی دستور /top برای برترین کاربران
    status: completed
  - id: add_stats_command
    content: پیاده‌سازی دستور /stats برای آمار کامل
    status: completed
---

# افز

ودن دستورات آمار و help به ربات approval

## تغییرات مورد نیاز

### 1. افزودن تعداد تسک‌های pending به پیام تایید

**فایل:** `app/Http/Controllers/TaskApprovalController.php`

- در متد `approveTask` (خط ~282)، بعد از پیام تایید، تعداد تسک‌های `pending_approval` را اضافه کنیم
- در متد `approveMission` (خط ~481)، همین کار را برای ماموریت‌ها انجام دهیم
```php
// بعد از خط 284
$pendingTasksCount = Task::where('task_status', 'pending_approval')->count();
$pendingMissionsCount = MissionPersonnel::where('status', 'pending_approval')->count();
$message .= "\n\n📊 تعداد تسک‌های در انتظار: {$pendingTasksCount}";
$message .= "\n📊 تعداد ماموریت‌های در انتظار: {$pendingMissionsCount}";
```




### 2. افزودن دستور /help

**فایل:** `app/Http/Controllers/TaskApprovalController.php`

- در متد `index` (بعد از خط ~106)، چک کنیم اگر پیام `/help` است، دستورات را نمایش دهیم
- دستورات شامل: `/help`, `/pending`, `/today`, `/top`, `/stats`

### 3. افزودن دستورات آمار

**فایل:** `app/Http/Controllers/TaskApprovalController.php`

#### 3.1 دستور `/pending`

- تعداد تسک‌های `pending_approval`
- تعداد ماموریت‌های `pending_approval`
- لیست کوتاه (مثلاً 5 تا) از آخرین تسک‌ها/ماموریت‌های pending

#### 3.2 دستور `/today`

- تعداد تسک‌های تایید شده امروز
- تعداد تسک‌های رد شده امروز
- تعداد تسک‌های pending
- تعداد ماموریت‌های تایید شده امروز
- تعداد ماموریت‌های رد شده امروز
- تعداد ماموریت‌های pending

#### 3.3 دستور `/top`

- برترین کاربران امروز بر اساس تعداد تسک‌های تایید شده
- برترین کاربران امروز بر اساس امتیاز کسب شده
- نمایش 5-10 کاربر برتر

#### 3.4 دستور `/stats`

- همه آمارهای بالا به صورت جامع
- آمار کلی (کل تسک‌ها، کل ماموریت‌ها)
- آمار امروز
- برترین کاربران

### 4. ساختار پیاده‌سازی

```php
// در متد index، بعد از چک کردن approval group
if ($text == '/help') {
    $this->handleHelp($bot, $type);
    return;
} elseif ($text == '/pending') {
    $this->handlePendingStats($bot, $type);
    return;
} elseif ($text == '/today') {
    $this->handleTodayStats($bot, $type);
    return;
} elseif ($text == '/top') {
    $this->handleTopUsers($bot, $type);
    return;
} elseif ($text == '/stats') {
    $this->handleAllStats($bot, $type);
    return;
}
```



### 5. متدهای جدید

- `handleHelp($bot, $type)`: نمایش لیست دستورات
- `handlePendingStats($bot, $type)`: آمار pending
- `handleTodayStats($bot, $type)`: آمار امروز
- `handleTopUsers($bot, $type)`: برترین کاربران
- `handleAllStats($bot, $type)`: آمار کامل

### 6. کوئری‌های مورد نیاز

- `Task::where('task_status', 'pending_approval')->count()`
- `MissionPersonnel::where('status', 'pending_approval')->count()`
- `Task::whereDate('approved_at', today())->count()`
- `Task::whereDate('rejected_at', today())->count()`
- `Personnel::withCount(['tasks' => function($q) { $q->whereDate('approved_at', today()); }])->orderBy('tasks_count', 'desc')->limit(10)->get()`
- `Personnel::orderBy('total_points', 'desc')->limit(10)->get()`

### 7. فرمت پیام‌ها

پیام‌ها باید به فارسی و با emoji مناسب باشند:

- استفاده از 📊 برای آمار
- استفاده از ✅ برای تایید شده
- استفاده از ❌ برای رد شده
- استفاده از ⏳ برای pending
- استفاده از 🏆 برای برترین کاربران

## فایل‌های تغییر یافته

1. `app/Http/Controllers/TaskApprovalController.php` - افزودن دستورات و متدهای جدید

## تست

- تست دستور `/help` در گروه approval
- تست دستورات آمار