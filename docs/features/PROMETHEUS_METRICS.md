# متریک پرومته ربات‌ها

## توضیحات

اندپوینت scrape برای Prometheus. متریک‌ها از دادهٔ موجود (`bots`, `webhook_endpoints`, `bot_users`, `bot_logs`, `bot_health_events`) در لحظه محاسبه می‌شوند. Grafana و پروب مصنوعی در این فاز نیستند.

هر سری یک اینستنس ربات × یک پلتفرم است (بله یا تلگرام)، با لیبل تایپ (`endpoint_id`) و اینستنس (`bot_id`). ربات جدید با توکن، خودکار در خروجی ظاهر می‌شود.

## اهداف

- Prometheus بتواند `/api/metrics` را scrape کند
- فیلتر per-bot و per-type با لیبل
- تشخیص بدون یوزر، بدون اینباند، و اینباند بدون اوتباند (با PromQL بعدی)

## مسیر فایل‌ها

- Controller: `app/Http/Controllers/MetricsController.php`
- Services: `app/Services/BotObservabilityService.php`, `app/Services/PrometheusMetricsExporter.php`
- Config: `config/observability.php`
- Route: `routes/api.php` — فقط اگر `METRICS_SECRET` ست شده باشد

## Environment Variables

```env
METRICS_SECRET=
```

خالی = route ثبت نمی‌شود. روی سرور یک رشتهٔ خیلی طولانی بگذار (حداقل ۳۲ کاراکتر، بدون فاصله).

آدرس:

```
https://bots.pardisania.ir/api/metrics?token=METRICS_SECRET
https://bots.pardisania.ir/api/metrics/METRICS_SECRET
Authorization: Bearer METRICS_SECRET
```

نمونه scrape Prometheus:

```yaml
scrape_configs:
  - job_name: pardisania-bots
    metrics_path: /api/metrics
    params:
      token: ['METRICS_SECRET']
    static_configs:
      - targets: ['bots.pardisania.ir']
    scheme: https
```

بعد از تغییر `.env`:

```bash
php artisan config:clear
php artisan route:clear
```

## لیبل‌ها

همهٔ گیج‌ها این لیبل‌ها را دارند:

| لیبل | منبع |
|------|------|
| `endpoint_id` | `bots.endpoint_id` (تایپ ربات) |
| `endpoint_name` | `webhook_endpoints.name` |
| `bot_id` | `bots.id` |
| `bot_name` | `bale_bot_name` یا `telegram_bot_name` |
| `platform` | `bale` یا `telegram` |

`child_bot_id` در این فاز نیست.

## متریک‌ها

| متریک | معنی |
|--------|------|
| `bot_info` | همیشه ۱؛ برای JOIN نام‌ها |
| `bot_users_total` | تعداد ردیف `bot_users` برای همان bot+origin |
| `bot_last_inbound_timestamp` | unix آخرین `bot_logs`؛ ۰ یعنی هرگز |
| `bot_last_outbound_ok_timestamp` | unix آخرین `bot_health_events` با status=ok؛ ۰ یعنی هرگز |
| `bot_last_outbound_fail_timestamp` | unix آخرین fail |
| `bot_webhook_is_set` | ۱ اگر وب‌هوک آن پلتفرم ست شده |
| `bot_status_active` | ۱ اگر status برابر Active باشد |

نمونه خروجی:

```
# HELP bot_users_total Registered bot users for this bot and platform.
# TYPE bot_users_total gauge
bot_users_total{endpoint_id="webhook-hadith",endpoint_name="Hadith Bot",bot_id="12",bot_name="hadith_fa_bot",platform="bale"} 3
```

نمونه PromQL (برای Grafana بعدی؛ الان پیاده نمی‌شود):

```promql
bot_users_total == 0
bot_last_inbound_timestamp == 0
bot_last_inbound_timestamp > bot_last_outbound_ok_timestamp
```

## Rollback

1. `METRICS_SECRET` را خالی کن و `config:clear` / `route:clear`
2. در صورت نیاز routeهای `/metrics` را از `routes/api.php` بردار

توکن را در git نگذار؛ فقط در `.env` سرور.
