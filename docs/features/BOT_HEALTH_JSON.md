# هلث جیسون ربات‌ها

## توضیحات

همان سیگنال‌های `/api/metrics` به صورت JSON برای انسان و کران. Grafana، پروب مصنوعی که دستور ربات را اجرا کند، و ارسال به گروه/کانال در این فاز نیستند.

محاسبه در `BotObservabilityService` با متریک پرومته مشترک است.

## اهداف

- دیدن symptoms هر ربات × پلتفرم
- دستور artisan برای چاپ همان JSON (وصل کردن به کران بعدی)

## مسیر فایل‌ها

- Controller: `app/Http/Controllers/HealthController.php`
- Service: `app/Services/BotObservabilityService.php` (مشترک با متریک)
- Command: `app/Console/Commands/HealthReportCommand.php`
- Route: `routes/api.php` — فقط اگر `HEALTH_SECRET` ست شده باشد

## Environment Variables

```env
HEALTH_SECRET=
```

خالی = route ثبت نمی‌شود. رشتهٔ طولانی در `.env` سرور.

آدرس:

```
https://bots.pardisania.ir/api/health?token=HEALTH_SECRET
https://bots.pardisania.ir/api/health/HEALTH_SECRET
Authorization: Bearer HEALTH_SECRET
```

بعد از تغییر `.env`:

```bash
php artisan config:clear
php artisan route:clear
```

## JSON

هر عنصر `bots[]`:

- `endpoint_id`, `endpoint_name`, `bot_id`, `bot_name`, `platform`
- `users`, `last_inbound_at`, `last_outbound_ok_at`, `last_outbound_fail_at`
- `webhook_is_set`, `status` (`Active` / `DeActive`)
- `symptoms[]`

symptoms:

| مقدار | معنی |
|--------|------|
| `no_users` | هیچ ردیفی در `bot_users` نیست |
| `no_inbound` | هیچ ردیفی در `bot_logs` نیست |
| `inbound_without_outbound` | اینباند هست و outbound ok نیست یا کهنه‌تر است |
| `webhook_not_set` | فلگ وب‌هوک آن پلتفرم خاموش است |
| `deactive` | status آن پلتفرم Active نیست |

## Artisan

کران نمی‌شود؛ فقط دستی (برای وصل بعدی به گزارش ادمین):

```bash
php artisan observability:health-report
php artisan observability:health-report --pretty
```

## Rollback

1. `HEALTH_SECRET` را خالی کن و `config:clear` / `route:clear`
2. در صورت نیاز routeهای `/health` و دستور `observability:health-report` را بردار

توکن را در git نگذار؛ فقط در `.env` سرور.
