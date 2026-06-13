# Bot Owner Self-Service

سیستم خودثبت‌نام مالک ربات با OTP بله (Safir API).

## ماژول‌ها

| ماژول | مسیر | توضیح |
|-------|------|-------|
| BaleOtp | `app/Modules/BaleOtp/` | احراز Safir، ارسال OTP، نرمال‌سازی شماره |
| BotOwner | `app/Modules/BotOwner/` | ثبت‌نام وب، پنل، Pro |
| BotRegistration | `app/Modules/BotRegistration/` | ساخت ربات با توکن (مشترک) |
| AdminBots | `app/Modules/AdminBots/` | ربات `/bots` و `/admin-bots` |

## مسیرهای وب

- `GET /bots` — صفحه معرفی
- `GET /bots/login` — ورود OTP
- `GET /bots/dashboard` — پنل مالک (نیاز به ورود)
- `GET /bots/create/{endpointId}` — ویزارد ساخت (نیاز به Pro)

## Webhook

```
POST /api/webhook-admin-bots?origin=bale&bot_mother_id=1&token=...
```

## Env

```env
BALE_SAFIR_CLIENT_ID=
BALE_SAFIR_CLIENT_SECRET=
ADMIN_BOTS_TOKEN_BALE=
BOT_OWNER_OTP_TTL=300
```

## جریان کاربر

1. ورود به `/bots` → OTP بله
2. درخواست Pro از پنل
3. تایید توسط ادمین کل: `/owner_pro_confirm {id}` در ربات مادر
4. ساخت ربات با توکن BotFather/بات‌ساز
5. اتصال حساب بله: `/link 09...` در ربات Admin Bots

## تست‌ها

```bash
php artisan test --filter=BaleOtp
php artisan test --filter=BotOwner
php artisan test --filter=AdminBots
```

## Seeder

```bash
php artisan db:seed --class=AdminBotsWebhookEndpointSeeder
```
