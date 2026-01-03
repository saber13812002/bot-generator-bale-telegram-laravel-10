# ایمپورت ترجمه‌های قرآن از SQL Dump

## توضیحات

این فیچر امکان ایمپورت ترجمه‌های قرآن از فایل‌های SQL dump (مثل `zh.jian.sql`) به جدول `quran_translations` را فراهم می‌کند.

## ساختار فایل SQL

فایل‌های SQL باید دارای ساختار زیر باشند:

```sql
-- phpMyAdmin SQL Dump
# --------------------------------------------------------------------
#  Quran Translation
#  Name: Ma Jian
#  Translator: Ma Jian
#  Language: Chinese
#  ID: zh.jian
# --------------------------------------------------------------------

CREATE TABLE `zh_jian` (
  `index` int(4) NOT NULL,
  `sura` int(3) NOT NULL,
  `aya` int(3) NOT NULL,
  `text` text NOT NULL
);

INSERT INTO `zh_jian` (`index`, `sura`, `aya`, `text`) VALUES
(1, 1, 1, '...'),
(2, 1, 2, '...');
```

## دستورات

### 1. بررسی فایل‌های موجود

برای بررسی فایل‌های SQL موجود و وضعیت ایمپورت آن‌ها:

```bash
php artisan quran-translations:check
```

یا با مسیر دلخواه:

```bash
php artisan quran-translations:check --path=resources/trans
```

**خروجی:**
- لیست همه فایل‌های `.sql` در پوشه
- وضعیت ایمپورت (ایمپورت شده/نشده)
- تعداد آیات هر ترجمه موجود

### 2. ایمپورت یک فایل خاص

برای ایمپورت یک فایل SQL خاص:

```bash
php artisan quran-translation:import resources/trans/zh.jian.sql
```

برای بازنویسی ترجمه موجود:

```bash
php artisan quran-translation:import resources/trans/zh.jian.sql --force
```

### 3. ایمپورت همه فایل‌های جدید

برای ایمپورت همه فایل‌هایی که قبلاً ایمپورت نشده‌اند:

```bash
php artisan quran-translations:import-all
```

**گزینه‌ها:**
- `--path=resources/trans`: مسیر پوشه فایل‌های SQL
- `--force`: ایمپورت همه فایل‌ها حتی اگر قبلاً ایمپورت شده‌اند
- `--dry-run`: فقط نمایش فایل‌هایی که ایمپورت می‌شوند (بدون ایمپورت واقعی)

**مثال:**
```bash
# فقط فایل‌های جدید
php artisan quran-translations:import-all

# همه فایل‌ها (بازنویسی)
php artisan quran-translations:import-all --force

# فقط نمایش (بدون ایمپورت)
php artisan quran-translations:import-all --dry-run
```

### 4. اسکریپت Bash

برای ایمپورت همه فایل‌ها با یک دستور:

```bash
chmod +x scripts/import-all-translations.sh
./scripts/import-all-translations.sh
```

## فرآیند کار

1. **گام 1: بررسی فایل‌ها**
   ```bash
   php artisan quran-translations:check
   ```

2. **گام 2: تست با یک فایل**
   ```bash
   php artisan quran-translation:import resources/trans/zh.jian.sql
   ```

3. **گام 3: ایمپورت همه فایل‌های جدید**
   ```bash
   php artisan quran-translations:import-all
   ```

4. **گام 4: اجرای اسکریپت (بعد از تست موفق)**
   ```bash
   ./scripts/import-all-translations.sh
   ```

## استخراج اطلاعات

سیستم به صورت خودکار اطلاعات زیر را از فایل SQL استخراج می‌کند:

1. **از کامنت‌های SQL:**
   - `# Language: Chinese` → `language: "zh"`
   - `# Translator: Ma Jian` → `translator: "Ma Jian"`
   - `# ID: zh.jian` → `translate_full_name: "zh.jian"`

2. **از نام فایل:**
   - `zh.jian.sql` → `language: "zh"`, `translator_name: "jian"`

3. **از نام جدول:**
   - `zh_jian` → `language: "zh"`, `translator_name: "jian"`

## ساختار جدول quran_translations

داده‌ها به صورت زیر در جدول `quran_translations` ذخیره می‌شوند:

- `id`: شناسه خودکار
- `translation_id`: شناسه ترجمه (اختیاری)
- `language`: کد زبان (مثلاً "zh")
- `translator_name`: نام مترجم (مثلاً "jian")
- `translate_full_name`: نام کامل (مثلاً "zh.jian")
- `index`: شماره ردیف از فایل SQL
- `sura`: شماره سوره (1-114)
- `aya`: شماره آیه
- `text`: متن ترجمه

## بررسی‌های امنیتی

- بررسی وجود فایل
- بررسی فرمت SQL
- بررسی صحت داده‌ها (sura بین 1-114، aya > 0)
- بررسی تکراری بودن قبل از insert
- استفاده از transaction برای rollback در صورت خطا
- بررسی encoding (UTF-8)

## بهینه‌سازی

- استفاده از batch insert (1000 رکورد در هر batch)
- استفاده از `DB::transaction()` برای کارایی بهتر
- نمایش progress bar برای فایل‌های بزرگ
- Cache کردن لیست ترجمه‌های موجود برای بررسی سریع‌تر

## عیب‌یابی

### خطا: "فایل وجود ندارد"
- بررسی کنید که مسیر فایل درست است
- از مسیر کامل استفاده کنید یا فایل را در `resources/trans/` قرار دهید

### خطا: "نام جدول یافت نشد"
- بررسی کنید که فایل SQL دارای `CREATE TABLE` یا `INSERT INTO` است
- فرمت فایل باید مطابق با ساختار استاندارد باشد

### خطا: "metadata یافت نشد"
- بررسی کنید که فایل دارای کامنت‌های metadata است
- یا نام فایل به فرمت `{language}.{translator}.sql` باشد

### خطا: "ترجمه قبلاً ایمپورت شده"
- از `--force` برای بازنویسی استفاده کنید
- یا ابتدا ترجمه موجود را حذف کنید

## فایل‌های مرتبط

- `app/Services/QuranTranslationImportService.php`: سرویس اصلی برای ایمپورت
- `app/Console/Commands/QuranTranslationCheck.php`: دستور بررسی فایل‌ها
- `app/Console/Commands/ImportQuranTranslation.php`: دستور ایمپورت یک فایل
- `app/Console/Commands/ImportQuranTranslations.php`: دستور ایمپورت همه فایل‌ها
- `scripts/import-all-translations.sh`: اسکریپت bash برای ایمپورت همه

## مثال‌های استفاده

### مثال 1: بررسی و ایمپورت یک فایل

```bash
# بررسی وضعیت
php artisan quran-translations:check

# ایمپورت یک فایل
php artisan quran-translation:import resources/trans/zh.jian.sql

# بررسی دوباره
php artisan quran-translations:check
```

### مثال 2: ایمپورت همه فایل‌های جدید

```bash
# بررسی فایل‌ها
php artisan quran-translations:check

# ایمپورت همه جدید
php artisan quran-translations:import-all

# بررسی نتیجه
php artisan quran-translations:check
```

### مثال 3: استفاده از اسکریپت

```bash
# اجرای اسکریپت
chmod +x scripts/import-all-translations.sh
./scripts/import-all-translations.sh
```

## راهنمای کامل برای ایمپورت ترجمه‌ها

### گام 1: بررسی فایل‌های موجود

قبل از شروع، بررسی کنید که چه فایل‌هایی در پوشه `resources/trans/` وجود دارند:

```bash
php artisan quran-translations:check
```

این دستور لیست کاملی از فایل‌های SQL و وضعیت ایمپورت آن‌ها را نمایش می‌دهد:
- ✅ کامل (6236 آیات): ترجمه کامل است
- ⚠️ ناقص (X آیات): ترجمه ناقص است
- ❌ ایمپورت نشده: هنوز ایمپورت نشده است

### گام 2: تست با یک فایل

قبل از ایمپورت همه فایل‌ها، یک فایل را تست کنید:

```bash
php artisan quran-translation:import resources/trans/zh.jian.sql
```

اگر موفق بود، می‌توانید ادامه دهید.

### گام 3: ایمپورت همه فایل‌های جدید

برای ایمپورت همه فایل‌هایی که قبلاً ایمپورت نشده‌اند:

```bash
php artisan quran-translations:import-all
```

این دستور:
- فایل‌هایی که قبلاً ایمپورت شده‌اند را رد می‌کند
- فقط فایل‌های جدید را ایمپورت می‌کند
- پیش از ایمپورت از شما تأیید می‌گیرد

### گام 4: بررسی نتیجه

بعد از ایمپورت، دوباره بررسی کنید:

```bash
php artisan quran-translations:check
```

### گام 5: استفاده از اسکریپت (اختیاری)

اگر می‌خواهید همه فایل‌ها را با یک دستور ایمپورت کنید:

```bash
chmod +x scripts/import-all-translations.sh
./scripts/import-all-translations.sh
```

## نکات مهم

### فایل‌های ناقص

برخی فایل‌ها ممکن است ناقص باشند (مثلاً 2795 آیات به جای 6236). این فایل‌ها:
- ✅ **نگه داشته می‌شوند**: حتی اگر ناقص باشند، قابل استفاده‌اند
- ⚠️ **علامت‌گذاری می‌شوند**: در لیست ترجمه‌ها با علامت ⚠️ مشخص می‌شوند
- 📊 **درصد کامل بودن نمایش داده می‌شود**: کاربر می‌تواند ببیند که ترجمه چقدر کامل است

### بازنویسی ترجمه موجود

اگر می‌خواهید یک ترجمه موجود را دوباره ایمپورت کنید:

```bash
php artisan quran-translation:import resources/trans/zh.jian.sql --force
```

⚠️ **هشدار**: این کار تمام داده‌های قبلی را حذف می‌کند و دوباره ایمپورت می‌کند.

### تغییر ترجمه پیش‌فرض کاربران

بعد از ایمپورت، می‌توانید ترجمه پیش‌فرض کاربران را تغییر دهید:

```bash
# تغییر ترجمه پیش‌فرض برای همه کاربران فارسی
php artisan quran-translation:set-default --language=fa --translator=ansarian

# تغییر برای یک ربات مادر خاص
php artisan quran-translation:set-default --language=en --translator=yusufali --bot-mother-id=1

# بازنویسی setting های موجود
php artisan quran-translation:set-default --language=ar --translator=jalalayn --force
```

## دستورات خلاصه

| دستور | توضیحات |
|------|---------|
| `php artisan quran-translations:check` | بررسی وضعیت فایل‌ها |
| `php artisan quran-translation:import {file}` | ایمپورت یک فایل |
| `php artisan quran-translations:import-all` | ایمپورت همه فایل‌های جدید |
| `php artisan quran-translation:set-default --language={lang} --translator={name}` | تغییر ترجمه پیش‌فرض |

## استفاده در ربات

بعد از ایمپورت، کاربران می‌توانند از دستور `/translation_list` در ربات قرآن استفاده کنند تا:
- لیست ترجمه‌های موجود برای زبان خود را ببینند
- ترجمه پیش‌فرض خود را تغییر دهند
- ترجمه‌های ناقص را با علامت ⚠️ شناسایی کنند
