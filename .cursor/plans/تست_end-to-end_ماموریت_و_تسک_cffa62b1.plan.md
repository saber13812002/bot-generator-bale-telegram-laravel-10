---
name: تست End-to-End ماموریت و تسک
overview: ایجاد یک تست End-to-End کامل که شامل ایجاد 10 ماموریت، چند تسک، 10 پرسنل و شبیه‌سازی کامل فرآیند از درخواست تا تایید است.
todos:
  - id: create_seeder
    content: ایجاد EndToEndTestSeeder برای ایجاد 10 ماموریت، 5-10 تسک، و 10 پرسنل با داده‌های کامل
    status: pending
  - id: create_feature_test
    content: ایجاد MissionTaskEndToEndTest که کل فرآیند را از درخواست تا تایید شبیه‌سازی می‌کند
    status: pending
    dependencies:
      - create_seeder
  - id: test_mission_flow
    content: "تست کامل فرآیند ماموریت: request -> submit -> approve -> verify points"
    status: pending
    dependencies:
      - create_feature_test
  - id: test_task_flow
    content: "تست کامل فرآیند تسک: assign -> submit -> approve -> verify points"
    status: pending
    dependencies:
      - create_feature_test
  - id: test_multiple_personnel
    content: تست همزمان چند پرسنل که ماموریت‌های مختلف را انجام می‌دهند
    status: pending
    dependencies:
      - test_mission_flow
  - id: verify_final_state
    content: "بررسی نهایی: تمام ماموریت‌ها و تسک‌ها تایید شده، امتیازها درست محاسبه شده"
    status: pending
    dependencies:
      - test_mission_flow
      - test_task_flow
      - test_multiple_personnel
---

# تست

End-to-End ماموریت و تسک

## هدف

ایجاد یک تست End-to-End کامل که کل فرآیند سیستم ماموریت و تسک را از ابتدا تا انتها شبیه‌سازی می‌کند.

## فایل‌های مورد نیاز

### 1. Seeder برای داده‌های تست

**مسیر:** `database/seeders/EndToEndTestSeeder.php`این Seeder باید ایجاد کند:

- 1 Tenant (یا استفاده از Tenant موجود)
- 10 Personnel با اطلاعات کامل
- 10 Mission با Prompt و Content
- 5-10 Task با Prompt (اختیاری)
- داده‌های مرتبط (AiLlm، Prompt، Content)

### 2. Feature Test End-to-End

**مسیر:** `tests/Feature/MissionTaskEndToEndTest.php`این تست باید شامل مراحل زیر باشد:

#### مرحله 1: Setup و آماده‌سازی

- اجرای Seeder
- بررسی وجود Tenant، Personnel، Mission، Task

#### مرحله 2: درخواست ماموریت توسط پرسنل

- پرسنل‌ها از طریق `MissionService::requestMission()` ماموریت درخواست می‌کنند
- بررسی assign شدن ماموریت به پرسنل
- بررسی تغییر status به `reserved`
- بررسی افزایش `current_personnel_count` در Mission

#### مرحله 3: Submit نتیجه توسط پرسنل

- پرسنل‌ها از طریق `MissionService::submitResult()` لینک نتیجه را ارسال می‌کنند
- بررسی تغییر status به `pending_approval`
- بررسی ثبت `result_link` در `mission_personnel`

#### مرحله 4: تایید ماموریت توسط سیستم

- استفاده از متد `MissionPersonnel::approve()` برای تایید
- بررسی تغییر status به `approved`
- بررسی ثبت `approved_at` و `approved_by_chat_id`
- بررسی افزایش امتیاز پرسنل (`total_points`)

#### مرحله 5: تست Task (اگر وجود دارد)

- پرسنل تسک را دریافت می‌کند
- Submit نتیجه تسک
- تایید تسک
- بررسی افزایش امتیاز

#### مرحله 6: بررسی نهایی

- بررسی تمام ماموریت‌ها و تسک‌ها تایید شده‌اند
- بررسی امتیاز نهایی پرسنل‌ها
- بررسی لاگ‌ها

## جزئیات پیاده‌سازی

### EndToEndTestSeeder

```php
- ایجاد Tenant (یا استفاده از موجود)
- ایجاد 10 Personnel با national_code منحصر به فرد
- ایجاد 10 Mission با:
    - Prompt مرتبط
    - Content مرتبط
    - points مختلف (50, 100, 150, ...)
    - max_personnel = 2-5
    - status = 'active'
- ایجاد 5-10 Task با:
    - assigned_user_id = یکی از پرسنل‌ها
    - task_status = 'reserved'
    - points مختلف
```



### MissionTaskEndToEndTest

```php
- استفاده از DatabaseTransactions برای isolation
- تست هر مرحله به صورت جداگانه
- Assert برای هر تغییر state
- بررسی relationships و pivot tables
- بررسی لاگ‌ها
```



## وابستگی‌ها

- `MissionService` - برای درخواست و submit ماموریت
- `MissionRepository` - برای دسترسی به داده‌ها
- `MissionPersonnel` Model - برای approve
- `Task` Model - برای تسک‌ها
- `Personnel` Model - برای محاسبه امتیاز

## فایل‌های مرتبط

- `app/Services/MissionServiceImpl.php` - سرویس ماموریت
- `app/Repositories/MissionRepositoryImpl.php` - ریپازیتوری ماموریت