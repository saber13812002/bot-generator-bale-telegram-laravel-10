<!-- fc69bbcf-9668-40fc-8182-6ab0c6401bf1 a1e1798f-e40d-425c-b617-a67273c92f88 -->
# پیاده‌سازی سیستم انتخاب و ثبت هوش مصنوعی برای ماموریت‌ها

## بررسی وضعیت فعلی

سیستم Mission موجود است و شامل:

- جدول `missions` با فیلدهای `prompt_id` و `content_id`
- جدول `mission_personnel` برای ارتباط Mission و Personnel
- ارسال Prompt و Content با توضیحات اضافی (مثل "📋 دستورالعمل ماموریت:")

## نیازمندی‌ها

1. **ارسال متن خالص**: Prompt و Content باید بدون توضیحات اضافی ارسال شوند
2. **جدول AI/LLM**: ساخت جدول برای ذخیره لیست هوش مصنوعی‌ها
3. **فیلد AI در Mission**: اضافه کردن فیلد `ai_id` (اختیاری) به جدول `missions`
4. **ثبت AI انتخابی**: اضافه کردن فیلد `selected_ai_id` به جدول `mission_personnel`
5. **انتخاب AI**: امکان انتخاب AI از لیست برای کاربر
6. **مدیریت AI**: امکان اضافه کردن AI جدید توسط ربات ادمین هر تننت

## مراحل پیاده‌سازی

### 1. ساخت جدول AI/LLM

**Migration**: `create_ai_llms_table.php`

- `id`: شناسه یکتا
- `name`: نام هوش مصنوعی (مثل "کلادی", "چت جی‌بی‌تی")
- `slug`: نامک برای استفاده در URL/API
- `description`: توضیحات (اختیاری)
- `is_active`: فعال/غیرفعال
- `sort_order`: ترتیب نمایش
- `created_at`, `updated_at`

**Model**: `app/Models/AiLlm.php`

- Relationships: `hasMany(Mission::class)`, `hasMany(MissionPersonnel::class)`
- Scopes: `scopeActive()`

### 2. اضافه کردن فیلد AI به Mission

**Migration**: `add_ai_id_to_missions_table.php`

- `ai_id`: `unsignedBigInteger()->nullable()`
- Foreign key به `ai_llms`
- Index

**Model**: `app/Models/Mission.php`

- Relationship: `belongsTo(AiLlm::class)`
- اضافه کردن `ai_id` به `$fillable`

### 3. اضافه کردن فیلد AI انتخابی به MissionPersonnel

**Migration**: `add_selected_ai_id_to_mission_personnel_table.php`

- `selected_ai_id`: `unsignedBigInteger()->nullable()`
- Foreign key به `ai_llms`
- Index

**Model**: `app/Models/MissionPersonnel.php`

- Relationship: `belongsTo(AiLlm::class, 'selected_ai_id')`
- اضافه کردن `selected_ai_id` به `$fillable`

### 4. تغییر روش ارسال Prompt و Content

**Controller**: `app/Http/Controllers/MissionBotController.php`

- متد `sendMissionContent()`: ارسال Prompt و Content به صورت خالص
- حذف توضیحات اضافی مثل "📋 دستورالعمل ماموریت:"
- ارسال در قالب قابل کپی برای استفاده در AI

**Job**: `app/Jobs/SendMissionMediaJob.php`

- تغییر متد ارسال Prompt و Content به صورت خالص

### 5. اضافه کردن انتخاب AI

**Controller**: `app/Http/Controllers/MissionBotController.php`

- متد `handleSelectAi()`: نمایش لیست AI‌ها و انتخاب
- Callback handler برای انتخاب AI
- ذخیره `selected_ai_id` در `mission_personnel`

**UI**:

- دکمه "انتخاب هوش مصنوعی" در پیام ماموریت
- لیست AI‌ها به صورت Inline Keyboard
- نمایش AI پیشنهادی (اگر `mission.ai_id` وجود داشته باشد)

### 6. مدیریت AI توسط ربات ادمین

**Controller**: `app/Http/Controllers/PersonnelAdminBotController.php`

- دستور `/add_ai`: اضافه کردن AI جدید
- دستور `/list_ai`: نمایش لیست AI‌ها
- دستور `/edit_ai`: ویرایش AI
- دستور `/delete_ai`: حذف AI (soft delete)

**Service**: `app/Services/AiLlmService.php` (اختیاری)

- منطق مدیریت AI

### 7. Seed داده‌های اولیه

**Seeder**: `database/seeders/AiLlmSeeder.php`

- کلادی
- چت جی‌بی‌تی
- دیب سیک
- مونیکا
- جمنای
- کوبالیت
- نوت بک ال ام

آموزش مربوط به کپی کردن همه اینها به مقصد مشابه است و فقط لینک دسترسی به هر کدام فرق دارد که خوب است که با توجه به این در فیلد یو آر ال در همین جدول برای کاربر بعد از پرامپت در ماموریت ارسال شود

### 8. Repository و Service (اختیاری)

**Interface**: `app/Interfaces/Repositories/AiLlmRepository.php`
**Implementation**: `app/Repositories/AiLlmRepositoryImpl.php`
**Interface**: `app/Interfaces/Services/AiLlmService.php`
**Implementation**: `app/Services/AiLlmServiceImpl.php`

### 9. به‌روزرسانی Nova 

**Resource**: `app/Nova/AiLlm.php`

- مدیریت AI از طریق Nova Admin Panel

## فایل‌های مورد نیاز

### Migrations

- `database/migrations/YYYY_MM_DD_HHMMSS_create_ai_llms_table.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_add_ai_id_to_missions_table.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_add_selected_ai_id_to_mission_personnel_table.php`

### Models

- `app/Models/AiLlm.php`
- به‌روزرسانی `app/Models/Mission.php`
- به‌روزرسانی `app/Models/MissionPersonnel.php`

### Controllers

- به‌روزرسانی `app/Http/Controllers/MissionBotController.php`
- به‌روزرسانی `app/Http/Controllers/PersonnelAdminBotController.php`

اگر بتوان این ای پی آی ها که به این کنترل ها متصل هست را از طریق یک کلید استاتیک مدیریت کرد
یا برای هر تننت توکن ساخته شود که از طریق همان توکن بتوانند محتوا و پرامپت هایشان را در صف از طریق وب سرویس قرار دهند. یا من از طریق توکن سوپر ادمین خودم بتوانم در هر تننت اطلاعات خودشان را با وب سرویس اضافه کنم
و اگر توکن تننت بود که آی دی همراهش در بانک وجود دارد 
و اگر توکن من بود که باید تننت را مشخص کنم و اجباری باشد

### Jobs

- به‌روزرسانی `app/Jobs/SendMissionMediaJob.php`

### Seeders

- `database/seeders/AiLlmSeeder.php`

### Services/Repositories )

- `app/Interfaces/Repositories/AiLlmRepository.php`
- `app/Repositories/AiLlmRepositoryImpl.php`
- `app/Interfaces/Services/AiLlmService.php`
- `app/Services/AiLlmServiceImpl.php`

## نکات مهم

1. **ارسال متن خالص**: Prompt و Content باید بدون هیچ توضیح اضافی ارسال شوند تا کاربر بتواند به راحتی کپی کند
2. **فیلد اختیاری**: `ai_id` در Mission اختیاری است (ممکن است ماموریت AI پیشنهادی نداشته باشد)
3. **ثبت AI انتخابی**: `selected_ai_id` در MissionPersonnel ثبت می‌شود تا در گزارش‌ها قابل استفاده باشد
4. **مدیریت AI**: ربات ادمین باید بتواند AI جدید اضافه کند (فقط اضافه کردن، نه ویرایش/حذف) لینک از طریق بنل نوا هم میتواند اضافه شود و فعلا به فرستادن نام اکتفا کند
5. **UI/UX**: انتخاب AI باید ساده و قابل فهم باشد

## سوالات باز

1. آیا کاربر باید AI را در زمان دریافت ماموریت انتخاب کند یا هنگام ارسال نتیجه؟ اگر ما فیلد را خالی بگذاریم هنگام نتیجه پس از ارسال لینک اگر این فیلد خالی است میتوانیم بپرسیم 
2. آیا می‌خواهیم AI پیشنهادی (از `mission.ai_id`) را به صورت پیش‌فرض انتخاب کنیم؟ خیر
3. آیا می‌خواهیم امکان تغییر AI را بعد از انتخاب بدهیم؟ بله

### To-dos

- [ ] ساخت جدول ai_llms با migration و model
- [ ] اضافه کردن فیلد ai_id به جدول missions
- [ ] اضافه کردن فیلد selected_ai_id به جدول mission_personnel
- [ ] به‌روزرسانی Model Mission برای relationship با AiLlm
- [ ] به‌روزرسانی Model MissionPersonnel برای relationship با AiLlm
- [ ] تغییر روش ارسال Prompt و Content به صورت خالص در MissionBotController
- [ ] اضافه کردن UI انتخاب AI در MissionBotController
- [ ] اضافه کردن دستورات مدیریت AI در PersonnelAdminBotController
- [ ] به‌روزرسانی SendMissionMediaJob برای ارسال خالص Prompt و Content
- [ ] ساخت Seeder برای داده‌های اولیه AI