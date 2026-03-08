---
name: Psychology and List Bot Fixes
overview: "رفع دو مشکل: (۱) ربات روان‌شناسی بعد از جواب آخر هیچ پاسخ/کارنامه/تشکری نمی‌فرستد و اضافه کردن دکمه «دیدن نتایج قبلی»؛ (۲) ربات فهرست به‌خاطر نبود جدول list_bot_configs خطا می‌دهد."
todos: []
isProject: false
---

# طرح رفع ربات روان‌شناسی و ربات فهرست

## مشکل ۱: ربات روان‌شناسی — بعد از سوال آخر پاسخی نمی‌آید

**علت احتمالی:** در مسیر callback (کلیک روی گزینه آخر)، مقدار `$bot->ChatID()` ممکن است خالی یا اشتباه برگردد (مشکل مشابه ربات فهرست با مصرف نشدن بدنه درخواست توسط پکیج). در [PsychologyTestBotController.php](app/Http/Controllers/PsychologyTestBotController.php) متد `calculateAndShowResults` فقط از `$bot->ChatID()` استفاده می‌کند و با `BotHelper::sendMessage($bot, $message)` پیام را می‌فرستد؛ اگر `ChatID()` خالی باشد ارسال یا به چت اشتباه می‌رود یا خطا می‌دهد و exception باعث می‌شود کاربر چیزی نبیند.

**اقدامات:**

- **پاس دادن صریح `chatId` در مسیر callback تا ارسال نتیجه:**  
در `handleCallbackQuery` مقدار `$chatId` از `$callbackQuery['message']['chat']['id']` گرفته می‌شود (حدود خط ۲۷۵). این `$chatId` را به `handleAnswer` و از آنجا به `calculateAndShowResults` پاس بدهید. داخل `calculateAndShowResults` به‌جای `BotHelper::sendMessage($bot, $message)` از `**BotHelper::sendMessageByChatId($bot, $chatId, $message)`** استفاده شود تا ارسال نتیجه همیشه به همان چتی باشد که callback از آن آمده.
- **try/catch دور محاسبه و ارسال نتیجه:**  
بلوک محاسبه نتیجه و ارسال (از حدود خط ۴۱۰ تا ۵۱۲) را داخل try/catch قرار دهید؛ در صورت exception آن را لاگ کنید و با `sendMessageByChatId` یک پیام کلی («خطا در ثبت/ارسال نتیجه. لطفاً دوباره تلاش کنید.») به همان `$chatId` بفرستید تا کاربر حداقل یک پاسخ ببیند و خطا سایلنت نماند.

---

## مشکل ۲: ربات روان‌شناسی — دکمه «دیدن نتایج قبلی»

**هدف:** کاربر بتواند تست‌های قبلی خود (تمام‌شده و در صورت امکان ناقص) و کارنامه/نتایج آن‌ها را ببیند.

**اقدامات پیشنهادی:**

- **دستور یا دکمه «دیدن نتایج قبلی»:**  
یک مسیر ورود اضافه کنید، مثلاً:
  - دستور `/my_results` یا `/نتایج_من`، یا
  - دکمه اینلاین «دیدن نتایج قبلی» در پاسخ به `/start` (در همان جایی که الان فقط «شروع تست» دارید).
- **منطق نمایش نتایج:**
  - **تست‌های تمام‌شده:** از جدول `psychology_test_results` با فیلتر `chat_id` و `psychology_test_bot_id` (مطابق ربات جاری) رکوردها را بخوانید و به کاربر نشان دهید (مثلاً لیست با تاریخ و خلاصه امتیازها). برای هر مورد یک دکمه/گزینه «مشاهده کارنامه» که متن کامل همان نتیجه (همان فرمت پیام کارنامه در `calculateAndShowResults`) را برای همان `result_id` بفرستد.
  - **تست‌های ناقص:** در وضعیت فعلی، «ناقص» به‌صورت جدا در دیتابیس ذخیره نمی‌شود؛ فقط در تنظیمات `bot_user` (مثل `psychology_test_questions` و `psychology_test_current_question_index`) حالت نیمه‌کاره هست. می‌توان در همان صفحه «دیدن نتایج قبلی» اگر چنین حالتی وجود داشت یک خط مثل «یک تست ناقص دارید — برای شروع مجدد /start بزنید» نشان داد. در صورت نیاز به ذخیره و نمایش چند «تست ناقص» جداگانه، در فاز بعد می‌توان جدول/مدل جدید (مثلاً `psychology_test_attempts`) تعریف کرد.
- **پیاده‌سازی فنی:**  
در [PsychologyTestBotController.php](app/Http/Controllers/PsychologyTestBotController.php):
  - اگر از دکمه استفاده می‌کنید، یک نوع callback جدید (مثلاً `psychology_my_results` و `psychology_show_result_{id}`) در `handleCallbackQuery` هندل شود.
  - متد کمکی (مثلاً `handleMyResults` و `handleShowResult($resultId)`) برای ساخت پیام لیست و پیام کارنامه (مطابق همان قالبی که در `calculateAndShowResults` ساخته می‌شود) و ارسال با `sendMessageByChatId` تا وابسته به `$bot->ChatID()` نباشد.

---

## مشکل ۳: ربات فهرست — خطای جدول `list_bot_configs` وجود ندارد

**علت:** در لاگ (Untitled-2) خطا این است:  
`Table 'pardisa2_bots.list_bot_configs' doesn't exist`  
یعنی جدول `list_bot_configs` روی دیتابیس ساخته نشده است.

**ساختار جدول (از مایگریشن):**  
فایل [2026_03_06_120000_create_list_bot_configs_table.php](database/migrations/2026_03_06_120000_create_list_bot_configs_table.php) جدول را این‌طور تعریف می‌کند:

- `id` (bigint, PK, auto increment)
- `bot_id` (unsigned bigint, unique, FK به `bots.id` با onDelete cascade)
- `menu_json` (json nullable)
- `raw_content` (text nullable)
- `timestamps` (created_at, updated_at)

**اقدام:**

- **شما خودتان جدول را در دیتابیس بسازید (طبق قوانین پروژه):**  
چون دیتابیس شما MSSQL است و شما اجرای کوئری را ترجیح می‌دهید، باید معادل این ساختار را در MSSQL اجرا کنید. مدل [ListBotConfig](app/Models/ListBotConfig.php) جدول را با نام پیش‌فرض لاراول `list_bot_configs` (جمع snake_case مدل) استفاده می‌کند؛ اگر در MSSQL نام جدول را عوض کنید، در مدل با `protected $table = '...'` همان نام را مشخص کنید.
- **نمونه SQL برای MSSQL (معادل مایگریشن):**
  - جدول با ستون‌ها: `id` (BIGINT IDENTITY), `bot_id` (BIGINT NOT NULL UNIQUE), `menu_json` (NVARCHAR(MAX) NULL), `raw_content` (NVARCHAR(MAX) NULL), `created_at` و `updated_at` (DATETIME2).
  - یک FK از `list_bot_configs.bot_id` به `bots.id` با ON DELETE CASCADE (در MSSQL با یک constraint جدا).
- بعد از ایجاد جدول، ربات فهرست باید بدون خطای «table doesn't exist» کار کند؛ در صورت استفاده از MySQL روی سرور (مثلاً pardisa2_bots)، همان مایگریشن لاراول را آنجا اجرا کنید یا معادل MySQL این جدول را بسازید.

---

## خلاصه ترتیب کار

1. **ربات روان‌شناسی — ارسال نتیجه بعد از آخرین سوال:** پاس دادن `chatId` از callback تا `calculateAndShowResults` و استفاده از `sendMessageByChatId` برای پیام نتیجه + try/catch و ارسال پیام خطای کلی در صورت exception.
2. **ربات روان‌شناسی — دکمه دیدن نتایج قبلی:** اضافه کردن دستور یا دکمه «دیدن نتایج قبلی»، هندل callback در صورت دکمه، و متد(های) نمایش لیست نتایج قبلی و کارنامه هر نتیجه با همان قالب فعلی؛ در صورت تمایل، نمایش یک خط برای «تست ناقص» بر اساس وضعیت bot_user.
3. **ربات فهرست:** شما جدول `list_bot_configs` را در دیتابیس (MSSQL یا MySQL بسته به محیط) با ساختار بالا ایجاد کنید؛ در صورت نیاز نام جدول در مدل تنظیم شود.

اگر برای «تست ناقص» فقط همان یک خط راهنما (/start برای شروع مجدد) کافی است، نیازی به جدول جدید در فاز اول نیست؛ در غیر این صورت می‌توان در مرحله بعد مدل/جدول «تست ناقص» را اضافه کرد.