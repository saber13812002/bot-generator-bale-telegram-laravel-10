---
name: "نماز قضا: عدد فارسی، ۳۴/۴۴، محاسبه تخمین، کران‌جاب"
overview: "پلان شامل: پشتیبانی عدد فارسی و کدهای ۳۴/۴۴ در ثبت نماز، رفع باگ محاسبه تخمین (ضرب دوباره در ۳.۴ و ماه ۳۰ روزه)، اطمینان از عدم اضافه شدن دکمه گزارش به هر پیام، و یکسان‌سازی مستندات کران‌جاب با سبک README."
todos: []
isProject: false
---

# پلان: بهبود ربات نماز قضا و مستندات کران‌جاب

## ۱. عدد فارسی در ورودی

**مشکل:** در ثبت نماز فقط اعداد انگلیسی 2,3,4 و در حالت تخمین فقط `is_numeric($text)` چک می‌شود؛ اعداد فارسی (مثلاً ۱۲ برای تخمین) پذیرفته نمی‌شوند.

**راه‌حل:**

- `**[app/Helpers/PrayerHelper.php](app/Helpers/PrayerHelper.php)`:** تابع `detectNumberInText` الان فقط ۲،۳،۴ فارسی را برای رکعات می‌شناسد. باید **تمام ارقام فارسی ۰–۹** را به انگلیسی تبدیل کنیم تا هر عدد فارسی (مثلاً ۱۷، ۳۱۰) هم استخراج شود. اضافه کردن یک تابع کمکی مثل `normalizePersianDigitsToEnglish(string $text): string` و استفاده از آن قبل از `preg_match` و در حلقه اعداد فارسی.
- `**[app/Http/Controllers/PrayerBotController.php](app/Http/Controllers/PrayerBotController.php)`** در `handleEstimateValue`: به‌جای `is_numeric($text)` از متن نرمال‌شده با ارقام انگلیسی استفاده شود و سپس `(int) $normalized` برای مقدار تخمین.

---

## ۲. کدهای ۳۴ (مغرب+عشا) و ۴۴ (ظهر+عصر)

**نیاز:**  

- **34** = مغرب + عشا با هم → ۳+۴ = **۷ رکعت** (ثبت دو نماز یا یک رکورد ۷ رکعتی با برچسب مناسب).  
- **44** = ظهر + عصر با هم → ۴+۴ = **۸ رکعت**.

**راه‌حل:**

- `**[app/Helpers/PrayerHelper.php](app/Helpers/PrayerHelper.php)`:**
  - در `detectNumberInText`: بعد از تشخیص 2,3,4، اگر متن دقیقاً `34` یا `44` (یا معادل فارسی) بود، عدد 34 یا 44 برگردانده شود.
  - گسترش `isValidRakatCount`: مقادیر **7** (برای 34) و **8** (برای 44) هم معتبر باشند.
- `**[app/Http/Controllers/PrayerBotController.php](app/Http/Controllers/PrayerBotController.php)`:** در `handleRecordPrayer` یا قبل از آن، اگر عدد 34 بود → ثبت ۷ رکعت با نوع ترکیبی مغرب+عشا؛ اگر 44 بود → ثبت ۸ رکعت با نوع ترکیبی ظهر+عصر. این می‌تواند با دو بار صدا زدن `recordPrayer` (مثلاً 3+4 و 4+4) یا با یک رکورد با `prayer_type` جدید (مثلاً `maghrib_isha` / `dhuhr_asr`) انجام شود. برای سادگی و سازگاری با آمار تفکیک‌شده، **دو رکورد جدا** (مثلاً یک ۳ رکعتی مغرب و یک ۴ رکعتی عشا برای 34، و دو ۴ رکعتی برای 44) تمیزتر است.
- `**[app/Services/PrayerBotServiceImpl.php](app/Services/PrayerBotServiceImpl.php)`:** تابع `detectPrayerType` برای 7 و 8 رکعت نیازی به تغییر ندارد اگر ثبت به صورت دو رکورد جدا باشد؛ در غیر این صورت می‌توان برای یک رکورد ۷/۸ رکعتی یک `prayer_type` ترکیبی تعریف کرد.
- **ترجمه/متن:** در `[lang/fa/bot.php](lang/fa/bot.php)` در صورت استفاده از برچسب ترکیبی، کلیدهای مناسب برای «مغرب و عشا» و «ظهر و عصر» اضافه شود.

---

## ۳. عدم اضافه کردن دکمه گزارش / گزارش به هر پیام

**وضعیت فعلی:** در `[handleRecordPrayer](app/Http/Controllers/PrayerBotController.php)` فقط پیام تأیید و لینک `/remove_{id}` فرستاده می‌شود؛ **دکمه گزارش یا slash report به هر پیام ثبت نماز اضافه نشده است.**  
بنابراین نیازی به تغییر نیست؛ فقط در پیاده‌سازی مراقب باشیم هنگام اضافه کردن قابلیت جدید، برای **هر** پیام ثبت‌شده دکمه گزارش یا دستور slash report نگذاریم (فقط در /help یا یک بار در شروع کافی است).

---

## ۴. رفع باگ محاسبه تخمین نماز قضا

**باگ ۱ – ضرب دوباره در ۳.۴:**  
در `[handleEstimateValue](app/Http/Controllers/PrayerBotController.php)` مقدار `$rakats = convertToRakats($value, $unit)` محاسبه می‌شود (درست)، اما به `setEstimate($chatId, $rakats, ...)` داده می‌شود. در `[PrayerBotServiceImpl::setEstimate](app/Services/PrayerBotServiceImpl.php)` پارامتر به عنوان `totalMissedPrayers` در نظر گرفته شده و دوباره `total_missed_rakats = ceil(totalMissedPrayers * 3.4)` محاسبه می‌شود؛ یعنی رکعات دوباره در ۳.۴ ضرب می‌شوند و عدد حدوداً ۳.۴ برابر بزرگ می‌شود.

**رفع:**  

- در `**PrayerBotServiceImpl::setEstimate`**: مقدار ورودی را به‌عنوان **رکعات کل** در نظر بگیریم:  
`total_missed_rakats = (int) $totalMissedPrayers` (نام پارامتر برای سازگاری با اینترفیس می‌ماند، اما معنای آن در این مسیر «رکعات» است)، و  
`total_missed_prayers = (int) round($totalMissedPrayers / 3.4)` تا نمایش «تعداد نماز» در صورت نیاز یکسان بماند.
- اینترفیس و فراخوانی در کنترلر همان‌طور که هست می‌ماند؛ فقط سمانتیک سرویس اصلاح می‌شود تا وقتی از مسیر تخمین (واحد روز/هفته/ماه/سال/رکعت) صدا زده می‌شود، ورودی رکعات باشد و دیگر ضرب در ۳.۴ انجام نشود.

**باگ ۲ – ماه همیشه ۳۰ روز:**  
در `[PrayerBotServiceImpl::convertToRakats](app/Services/PrayerBotServiceImpl.php)` برای `month` از `$value * 30 * 17` استفاده شده؛ شما خواستید برای ماه ۳۱ روزه (مثلاً ۱۰ ماه = ۳۱۰ روز) در نظر گرفته شود.

**رفع:**  

- در `convertToRakats` مقدار ماه را به **۳۱** روز تغییر دهیم:  
`'month' => $value * 31 * 17`.

**خلاصه تغییرات:**

- `[app/Services/PrayerBotServiceImpl.php](app/Services/PrayerBotServiceImpl.php)`: در `setEstimate` ذخیره مستقیم رکعات و محاسبه نماز از رکعات؛ در `convertToRakats` استفاده از ۳۱ روز برای ماه.
- در `**PrayerEstimateRepositoryImpl::getProgress`** اگر جایی از کلید `estimate` برای «هدف بر اساس رکعات» استفاده می‌شود (مثلاً در کنترلر)، اضافه کردن `'estimate' => $estimate->total_missed_rakats` به آرایه برگشتی تا نمایش درست باشد.

---

## ۵. مستندات کران‌جاب (هم‌سبک README)

**محل فعلی لیست کران‌جاب:** در `**[README.md](README.md)`** زیر عنوان «Cron Jobs» (حدود خط ۵۷۶) جدولی به سبک `Minute | Hour | Day | Month | Weekday | Command` وجود دارد که کران‌جابهای سرور (مثل `schedule:run`، `queue:work`، و دستورات دیگر) لیست شده‌اند.

**کار درخواستی:** یک کران‌جاب به همان سبک به لیست اضافه شود. از آن‌جا که در `**[app/Console/Kernel.php](app/Console/Kernel.php)`** همه چیز زیر `schedule:run` تعریف شده، معمولاً روی سرور فقط یک کران‌جاب برای `php artisan schedule:run` کافی است (مثلاً همان `59 23 10 * `* که الان در README هست). اگر منظورتان یک **دستور جدید** است که باید روی سرور به صورت کران اجرا شود، آن را با همان قالب (Minute, Hour, Day, Month, Weekday, Command) در همان بخش Cron Jobs در README اضافه کنید. اگر منظورتان **مستند کردن دستوراتی است که داخل `schedule` اجرا می‌شوند**، می‌توان یک زیربخش اضافه کرد به این مضمون: «با اجرای `schedule:run` این دستورات به‌صورت زمان‌بندی‌شده اجرا می‌شوند» و لیست از Kernel (مثل SendPrayerWeeklyReports، CheckWeatherAlertsJob، ScheduleBookPublishing، TaskReminderCommand، UsersRankingCommand، RssReadTranslate و غیره) با فرکانس هر کدام آورده شود تا هنگام تنظیم crontab فقط یک خط `schedule:run` لازم باشد و مستندات کامل باشد.

---

## ترتیب پیشنهادی پیاده‌سازی

1. رفع باگ محاسبه تخمین (setEstimate + ماه ۳۱ روزه + کلید `estimate` در getProgress).
2. عدد فارسی در PrayerHelper و در handleEstimateValue.
3. پشتیبانی 34 و 44 در PrayerHelper و ثبت دو رکورد در کنترلر (یا یک رکورد با نوع ترکیبی).
4. به‌روزرسانی مستندات کران‌جاب در README (یک ردیف جدید یا زیربخش schedule).

---

## خلاصه فایل‌های تحت تأثیر


| فایل                                                | تغییر                                                                                       |
| --------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| `app/Services/PrayerBotServiceImpl.php`             | setEstimate: ورودی = رکعات، بدون ضرب ۳.۴؛ convertToRakats: month با ۳۱ روز                  |
| `app/Repositories/PrayerEstimateRepositoryImpl.php` | اضافه کردن `'estimate' => total_missed_rakats` در getProgress در صورت استفاده در UI         |
| `app/Helpers/PrayerHelper.php`                      | نرمال‌سازی اعداد فارسی؛ تشخیص 34/44؛ گسترش isValidRakatCount به 7 و 8                       |
| `app/Http/Controllers/PrayerBotController.php`      | handleEstimateValue: استفاده از متن نرمال‌شده؛ برای 34/44 ثبت دو رکورد (یا یک رکورد ترکیبی) |
| `app/Services/PrayerBotServiceImpl.php` (اختیاری)   | در صورت یک رکورد برای 34/44: تشخیص prayer_type ترکیبی برای ۷ و ۸ رکعت                       |
| `lang/fa/bot.php`                                   | در صورت نیاز: کلیدهای ترجمه برای مغرب+عشا و ظهر+عصر                                         |
| `README.md`                                         | به‌روزرسانی بخش Cron Jobs با یک ردیف یا زیربخش schedule                                     |


