<!-- 7b43ae4e-a811-4726-9f9a-dc3d30e42fbc 0e384010-88d1-4aae-a51d-17920b7e91ed -->
# پلان پیاده‌سازی سیستم انتخاب ماموریت و تگ‌ها

## بررسی نیازمندی‌ها

قبل از شروع پیاده‌سازی، نیاز به پاسخ به سوالات زیر داریم:

1. **فیلد duration برای ماموریت‌ها**: آیا باید فیلد `duration` (به دقیقه) به جدول `missions` اضافه شود؟ بله ولی اجباری نیست
2. **فیلتر امتیاز**: فقط "10 یا بیشتر" یا فیلترهای بیشتر (5-10، 10-20، 20+)؟ همین خوبه و دکمه و بیشتر رندوم بیاره
3. **مدل Tag**: آیا باید مدل `Tag` با polymorphic relationship بسازیم یا از پکیج استفاده کنیم؟ فرقی نمیکنه
4. **دکمه /دان**: inline button با URL مستقیم یا callback query؟ دکمه شیشیه ای کالب بک
5. **ماموریت مرتب**: همان sequential mode موجود یا چیز دیگری؟ خیر اشتباه تایپی است منظور ماموریت و آموزش مرتبط بوده 
6. **نمایش لیست در ربات مدیا**: inline keyboard buttons یا لیست متنی؟ لیست متنی

## ساختار پیشنهادی

### 1. Migration برای فیلدهای جدید

- اضافه کردن `duration` (integer, nullable) به جدول `missions` برای مدت زمان تخمینی ماموریت
- بررسی وجود مدل `Tag` و ساخت آن در صورت نیاز
- اضافه کردن polymorphic relationship برای تگ‌ها به مدل‌های Mission, Task, Prompt, Tenant, Personnel

### 2. ربات مدیا (MissionMediaBotController)

- اضافه کردن دستور `/list_missions` برای نمایش لیست ماموریت‌ها
- نمایش ماموریت‌ها با inline keyboard (هر دکمه یک mission_id)
- بعد از ارسال مدیا، اضافه کردن دکمه `/دان` با URL مستقیم به فایل

### 3. ربات ماموریت (MissionBotController)

- اضافه کردن دکمه "رندوم" برای درخواست ماموریت رندوم
- اضافه کردن دکمه "انتخاب نوع ماموریت" برای فیلتر کردن
- پیاده‌سازی فیلتر بر اساس:
- duration (مثلاً 1 دقیقه‌ای)
- points (مثلاً 10 یا بیشتر)
- tags (نمایش تگ‌ها و انتخاب)
- اضافه کردن callback query handler برای inline buttons
- ارسال لینک ویدیو/مدیای آموزشی برای ماموریت‌های sequential

### 4. مدل Tag و Relationships

- ساخت مدل `Tag` (اگر وجود ندارد)
- اضافه کردن trait `HasTags` یا استفاده از polymorphic relationship
- اتصال به Mission, Task, Prompt, Tenant, Personnel

### 5. Service Layer

- اضافه کردن متدهای جدید به `MissionService`:
- `getMissionsByFilter(array $filters)`
- `getMissionsByTags(array $tagIds)`
- `getMissionsByDuration(int $duration)`
- `getMissionsByPoints(int $minPoints)`

### 6. Repository Layer

- اضافه کردن متدهای جدید به `MissionRepository`:
- `findByTags(array $tagIds)`
- `findByDuration(int $duration)`
- `findByMinPoints(int $minPoints)`
- `findByFilters(array $filters)`

### 7. README و مستندسازی

- به‌روزرسانی README اصلی
- اضافه کردن مستندات برای فیچرهای جدید
- راهنمای استفاده از تگ‌ها

### 8. تست قدم به قدم

- نوشتن راهنمای تست کامل برای نصب روی سرور
- سناریوهای تست برای هر فیچر

## فایل‌های مورد نیاز برای تغییر

- `app/Http/Controllers/MissionMediaBotController.php` - اضافه کردن لیست ماموریت‌ها و دکمه دانلود
- `app/Http/Controllers/MissionBotController.php` - اضافه کردن دکمه‌ها و فیلترها
- `app/Models/Mission.php` - اضافه کردن relationships و scopes
- `app/Models/Tag.php` - ساخت مدل (اگر وجود ندارد)
- `app/Services/MissionServiceImpl.php` - اضافه کردن متدهای فیلتر
- `app/Repositories/MissionRepositoryImpl.php` - اضافه کردن query methods
- `app/Interfaces/Services/MissionService.php` - اضافه کردن interface methods
- `app/Interfaces/Repositories/MissionRepository.php` - اضافه کردن interface methods
- Migration برای فیلد `duration`
- README files

## سوالات برای کاربر

لطفاً به سوالات بالا پاسخ دهید تا پلان دقیق‌تری ارائه دهم.