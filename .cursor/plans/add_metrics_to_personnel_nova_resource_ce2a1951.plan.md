---
name: Add Metrics to Personnel Nova Resource
overview: "افزودن آمارهای مشابه User به صفحه Personnel در Nova شامل: تعداد پرسنل جدید، پیشرفت ثبت‌نام، آمار روزانه، آمار بر اساس رتبه و tenant، و آمار فعالیت‌ها (تسک‌ها، ماموریت‌ها، امتیازها)"
todos: []
---

# افزودن آمار به

صفحه Personnel در Nova

## بررسی وضعیت فعلی

در حال حاضر صفحه `Personnel` در Nova هیچ Metric ندارد (متد `cards()` خالی است)، در حالی که صفحه `User` دارای 5 Metric است:

- `NewUsers` (Value)
- `NewUsersProgress` (Progress)
- `UsersPerDay` (Trend)
- `UsersPerPlan` (Partition)
- `NewReleases` (Table)

## Metric های پیشنهادی برای Personnel

### 1. Metric های پایه (مشابه User)

#### 1.1 NewPersonnel (Value)

- **فایل**: `app/Nova/Metrics/NewPersonnel.php`
- **نوع**: Value metric
- **عملکرد**: نمایش تعداد پرسنل جدید در بازه زمانی انتخاب شده
- **مشابه**: `NewUsers.php`

#### 1.2 NewPersonnelProgress (Progress)

- **فایل**: `app/Nova/Metrics/NewPersonnelProgress.php`
- **نوع**: Progress metric
- **عملکرد**: نمایش پیشرفت ثبت‌نام پرسنل با target قابل تنظیم
- **مشابه**: `NewUsersProgress.php`

#### 1.3 PersonnelPerDay (Trend)

- **فایل**: `app/Nova/Metrics/PersonnelPerDay.php`
- **نوع**: Trend metric
- **عملکرد**: نمایش تعداد پرسنل ثبت شده در هر روز
- **مشابه**: `UsersPerDay.php`

#### 1.4 PersonnelPerRank (Partition)

- **فایل**: `app/Nova/Metrics/PersonnelPerRank.php`
- **نوع**: Partition metric
- **عملکرد**: نمایش تعداد پرسنل بر اساس رتبه (سرباز صفر، سرباز یک، سرباز دو، سرباز سه)
- **مشابه**: `UsersPerPlan.php` اما بر اساس فیلد `rank`

#### 1.5 PersonnelPerTenant (Partition)

- **فایل**: `app/Nova/Metrics/PersonnelPerTenant.php`
- **نوع**: Partition metric
- **عملکرد**: نمایش تعداد پرسنل بر اساس tenant
- **جدید**: برای نمایش توزیع پرسنل در tenant های مختلف

### 2. Metric های فعالیت (جدید)

#### 2.1 PersonnelTasksStats (Value)

- **فایل**: `app/Nova/Metrics/PersonnelTasksStats.php`
- **نوع**: Value metric
- **عملکرد**: نمایش آمار تسک‌های پرسنل (تایید شده، رد شده، در انتظار)
- **جدید**: برای نمایش فعالیت پرسنل در تسک‌ها

#### 2.2 PersonnelMissionsStats (Value)

- **فایل**: `app/Nova/Metrics/PersonnelMissionsStats.php`
- **نوع**: Value metric
- **عملکرد**: نمایش آمار ماموریت‌های پرسنل (تایید شده، رد شده، در انتظار)
- **جدید**: برای نمایش فعالیت پرسنل در ماموریت‌ها

#### 2.3 PersonnelPointsStats (Value)

- **فایل**: `app/Nova/Metrics/PersonnelPointsStats.php`
- **نوع**: Value metric
- **عملکرد**: نمایش مجموع امتیازهای پرسنل
- **جدید**: برای نمایش عملکرد کلی پرسنل

## فایل‌های مورد نیاز

### فایل‌های جدید (8 فایل)

1. `app/Nova/Metrics/NewPersonnel.php`
2. `app/Nova/Metrics/NewPersonnelProgress.php`
3. `app/Nova/Metrics/PersonnelPerDay.php`
4. `app/Nova/Metrics/PersonnelPerRank.php`
5. `app/Nova/Metrics/PersonnelPerTenant.php`
6. `app/Nova/Metrics/PersonnelTasksStats.php`
7. `app/Nova/Metrics/PersonnelMissionsStats.php`
8. `app/Nova/Metrics/PersonnelPointsStats.php`

### فایل‌های ویرایشی (1 فایل)

1. `app/Nova/Personnel.php` - اضافه کردن Metric ها به متد `cards()`

## جزئیات پیاده‌سازی

### ساختار Metric های پایه

- همه Metric های پایه از ساختار مشابه `User` metrics استفاده می‌کنند
- استفاده از `Personnel::class` به جای `User::class`
- استفاده از فیلد `rank` به جای `plan` در Partition metric
- Cache time: 12 hours (مشابه User metrics)

### ساختار Metric های فعالیت

- استفاده از روابط `tasks()` و `missionPersonnel()` در مدل Personnel
- فیلتر بر اساس status (approved, rejected, pending_approval)
- محاسبه مجموع امتیازها از تسک‌ها و ماموریت‌های تایید شده

### تغییرات در Personnel.php

```php
public function cards(NovaRequest $request)
{
    return [
        new NewPersonnel,
        new NewPersonnelProgress,
        new PersonnelPerDay,
        new PersonnelPerRank,
        new PersonnelPerTenant,
        new PersonnelTasksStats,
        new PersonnelMissionsStats,
        new PersonnelPointsStats,
    ];
}
```



## ترتیب اجرا

1. ایجاد Metric های پایه (5 فایل)
2. ایجاد Metric های فعالیت (3 فایل)
3. ویرایش `Personnel.php` برای اضافه کردن Metric ها
4. تست نمایش Metric ها در Nova dashboard

## نکات مهم

- همه Metric ها باید `uriKey()` منحصر به فرد داشته باشند
- Cache time برای همه Metric ها 12 ساعت است