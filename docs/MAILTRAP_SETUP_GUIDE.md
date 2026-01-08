# 📧 راهنمای تنظیمات Mailtrap API

## 🎯 دو حالت استفاده از Mailtrap

### 1. Sandbox (برای تست)
ایمیل‌ها در Mailtrap ذخیره می‌شوند و به گیرنده واقعی ارسال نمی‌شوند.

### 2. Transactional (برای Production)
ایمیل‌ها واقعاً به گیرنده ارسال می‌شوند.

---

## 🔧 تنظیمات

### مرحله 1: دریافت API Token

1. وارد [Mailtrap Dashboard](https://mailtrap.io) شوید
2. به بخش **Settings** → **API Tokens** بروید
3. یک Token جدید ایجاد کنید
4. Token را کپی کنید (مثلاً: `5c2835343ece0cc635687a7047b5d338`)

### مرحله 2: تنظیمات `.env`

برای **Transactional API** (Production):

```env
MAILTRAP_API_TOKEN=5c2835343ece0cc635687a7047b5d338
MAIL_FROM_ADDRESS=hello@pardisania.ir
MAIL_FROM_NAME="${APP_NAME}"
```

برای **Sandbox** (تست):

```env
MAILTRAP_API_TOKEN=5c2835343ece0cc635687a7047b5d338
MAILTRAP_INBOX_ID=1439975
MAIL_FROM_ADDRESS=hello@pardisania.ir
MAIL_FROM_NAME="${APP_NAME}"
```

---

## 🧪 تست

### تست با Transactional API (Production):

```bash
php artisan test:mailtrap-api saber.tabatabaee@gmail.com
```

یا با Token مستقیم:

```bash
php artisan test:mailtrap-api saber.tabatabaee@gmail.com --token=5c2835343ece0cc635687a7047b5d338
```

### تست با Sandbox (برای تست):

```bash
php artisan test:mailtrap-api saber.tabatabaee@gmail.com --sandbox
```

یا با Token و Inbox ID:

```bash
php artisan test:mailtrap-api saber.tabatabaee@gmail.com --token=5c2835343ece0cc635687a7047b5d338 --sandbox
```

---

## 📋 تفاوت‌های Sandbox و Transactional

| ویژگی | Sandbox | Transactional |
|-------|---------|---------------|
| URL | `/api/send/{inbox_id}` | `/api/send` |
| Header | `Api-Token` | `Authorization: Bearer` |
| ارسال واقعی | ❌ خیر | ✅ بله |
| ذخیره در Mailtrap | ✅ بله | ❌ خیر |
| نیاز به Inbox ID | ✅ بله | ❌ خیر |

---

## ⚠️ نکات مهم

1. **Token را بدون فاصله** در `.env` قرار دهید
2. برای **Production** از Transactional API استفاده کنید
3. برای **تست** از Sandbox استفاده کنید
4. `MAIL_FROM_ADDRESS` باید یک آدرس ایمیل معتبر باشد
5. برای Transactional API، دامنه باید در Mailtrap تایید شده باشد

---

## 🔍 عیب‌یابی

### خطا: "API Token تنظیم نشده است"

**راه‌حل:**
- Token را در `.env` اضافه کنید: `MAILTRAP_API_TOKEN=your-token`
- یا از option استفاده کنید: `--token=your-token`

### خطا: "API Token دارای فاصله است"

**راه‌حل:**
- فاصله‌ها را از Token حذف کنید
- Token را دوباره از Mailtrap Dashboard کپی کنید

### خطا: "Unauthorized" یا "401"

**راه‌حل:**
- Token را بررسی کنید
- مطمئن شوید Token معتبر است
- برای Transactional API، دامنه را تایید کنید

---

## 📚 منابع

- [Mailtrap API Documentation](https://mailtrap.io/api-docs)
- [Mailtrap Transactional Email](https://mailtrap.io/transactional-email)
