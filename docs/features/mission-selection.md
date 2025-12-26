# سیستم انتخاب ماموریت و تگ‌ها

این مستندات توضیح می‌دهد که چگونه از سیستم انتخاب ماموریت با فیلترها و تگ‌ها استفاده کنیم.

## ویژگی‌های اضافه شده

### 1. فیلد Duration در ماموریت‌ها

- فیلد `duration` (به دقیقه) به جدول `missions` اضافه شده است
- این فیلد اختیاری است و می‌تواند برای فیلتر کردن ماموریت‌ها بر اساس مدت زمان استفاده شود

### 2. سیستم تگ‌ها (Polymorphic)

- مدل `Tag` با polymorphic relationship پیاده‌سازی شده است
- تگ‌ها می‌توانند به مدل‌های زیر متصل شوند:
  - Mission
  - Task
  - Prompt
  - Tenant
  - Personnel

### 3. ربات مدیا - لیست ماموریت‌ها

- دستور `/list_missions` برای نمایش لیست تمام ماموریت‌ها
- نمایش لیست متنی با ID و عنوان ماموریت‌ها
- بعد از آپلود مدیا، دکمه "📥 دانلود" اضافه می‌شود

### 4. ربات ماموریت - دکمه‌های انتخاب

- دکمه "🎲 ماموریت رندوم" برای دریافت ماموریت تصادفی
- دکمه "🔍 انتخاب نوع ماموریت" برای فیلتر کردن:
  - ⏱️ ماموریت 1 دقیقه‌ای
  - 🎯 امتیاز 10 یا بیشتر
  - 🏷️ بر اساس تگ

### 5. ارسال لینک‌های آموزشی

- بعد از اختصاص ماموریت، لینک‌های ویدیو/مدیای آموزشی مرتبط ارسال می‌شود

## استفاده

### برای ادمین (ربات مدیا)

1. **مشاهده لیست ماموریت‌ها:**
   ```
   /list_missions
   ```

2. **آپلود مدیا:**
   ```
   /upload_mission_{id}
   [ارسال فایل‌ها]
   /done
   ```
   بعد از آپلود هر فایل، دکمه "📥 دانلود" برای دریافت لینک دانلود نمایش داده می‌شود.

### برای کاربر (ربات ماموریت)

1. **شروع:**
   ```
   /start
   ```
   دو دکمه نمایش داده می‌شود:
   - 🎲 ماموریت رندوم
   - 🔍 انتخاب نوع ماموریت

2. **انتخاب ماموریت رندوم:**
   - کلیک روی دکمه "🎲 ماموریت رندوم"
   - یک ماموریت تصادفی اختصاص داده می‌شود
   - لینک‌های آموزشی مرتبط ارسال می‌شود

3. **فیلتر کردن ماموریت‌ها:**
   - کلیک روی دکمه "🔍 انتخاب نوع ماموریت"
   - انتخاب یکی از گزینه‌ها:
     - ⏱️ ماموریت 1 دقیقه‌ای
     - 🎯 امتیاز 10 یا بیشتر
     - 🏷️ بر اساس تگ
   - انتخاب ماموریت از لیست نمایش داده شده

## API Methods

### MissionService

```php
// دریافت ماموریت‌ها بر اساس duration
$missions = $missionService->getMissionsByDuration(1, $tenantId);

// دریافت ماموریت‌ها با حداقل امتیاز
$missions = $missionService->getMissionsByMinPoints(10, $tenantId);

// دریافت ماموریت‌ها بر اساس تگ‌ها
$missions = $missionService->getMissionsByTags([1, 2, 3], $tenantId);

// دریافت ماموریت‌ها با فیلترهای ترکیبی
$missions = $missionService->getMissionsByFilters([
    'duration' => 1,
    'min_points' => 10,
    'tag_ids' => [1, 2]
], $tenantId);
```

### MissionRepository

```php
// پیدا کردن ماموریت‌ها بر اساس duration
$missions = $missionRepository->findByDuration(1, $tenantId);

// پیدا کردن ماموریت‌ها با حداقل امتیاز
$missions = $missionRepository->findByMinPoints(10, $tenantId);

// پیدا کردن ماموریت‌ها بر اساس تگ‌ها
$missions = $missionRepository->findByTags([1, 2, 3], $tenantId);

// پیدا کردن ماموریت‌ها با فیلترهای ترکیبی
$missions = $missionRepository->findByFilters([
    'duration' => 1,
    'min_points' => 10,
    'tag_ids' => [1, 2]
], $tenantId);
```

## مدل Tag

```php
// دریافت تگ‌های یک ماموریت
$mission = Mission::find(1);
$tags = $mission->tags;

// اضافه کردن تگ به ماموریت
$mission->tags()->attach($tagId);

// حذف تگ از ماموریت
$mission->tags()->detach($tagId);

// دریافت ماموریت‌های یک تگ
$tag = Tag::find(1);
$missions = $tag->missions;
```

## Migration

برای اجرای migration:

```bash
php artisan migrate
```

این migration فیلد `duration` را به جدول `missions` اضافه می‌کند.

