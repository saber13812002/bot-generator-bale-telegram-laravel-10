# مستندات API مدیریت ماموریت‌ها

این مستندات راهنمای کامل استفاده از API برای ایجاد و مدیریت ماموریت‌ها از طریق وب‌سرویس است.

## 🚀 راهنمای سریع

### 1. ایجاد توکن از طریق CLI (پیشنهادی)

```bash
# برای Tenant
php artisan api:token:generate --type=tenant --tenant-id=1 --name="My Token"

# برای Super Admin
php artisan api:token:generate --type=super_admin --name="Super Admin Token"
```

### 2. ایجاد ماموریت

```bash
curl -X POST https://your-domain.com/api/api/v1/missions \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"ماموریت تست","prompt_content":"پرامپت تست","points":10}'
```

### 3. مشاهده لیست

```bash
curl -X GET https://your-domain.com/api/api/v1/missions \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 📋 فهرست مطالب

1. [احراز هویت](#احراز-هویت)
2. [ایجاد توکن API](#ایجاد-توکن-api)
3. [ایجاد ماموریت](#ایجاد-ماموریت)
4. [مشاهده لیست ماموریت‌ها](#مشاهده-لیست-ماموریت‌ها)
5. [مشاهده یک ماموریت](#مشاهده-یک-ماموریت)
6. [اضافه کردن محتوای آموزشی](#اضافه-کردن-محتوای-آموزشی)
7. [ارسال لینک نتیجه](#ارسال-لینک-نتیجه)
8. [بررسی وضعیت تایید](#بررسی-وضعیت-تایید)
9. [سناریوی کامل](#سناریوی-کامل)

---

## 🔐 احراز هویت

تمام درخواست‌های API نیاز به توکن احراز هویت دارند. توکن را می‌توانید به یکی از روش‌های زیر ارسال کنید:

### روش 1: Bearer Token (پیشنهادی)
```bash
Authorization: Bearer YOUR_TOKEN_HERE
```

### روش 2: Header
```bash
X-API-Token: YOUR_TOKEN_HERE
```

### روش 3: Query Parameter
```
?token=YOUR_TOKEN_HERE
```

---

## 🔑 ایجاد توکن API

### برای Tenant

```bash
curl -X POST https://your-domain.com/api/api-tokens/generate \
  -H "Content-Type: application/json" \
  -d '{
    "type": "tenant",
    "tenant_id": 1,
    "name": "Token برای Tenant 1"
  }'
```

**پاسخ:**
```json
{
  "success": true,
  "message": "Token generated successfully",
  "data": {
    "token_id": 1,
    "token": "abc123def456...",
    "type": "tenant",
    "tenant_id": 1,
    "warning": "⚠️ Please save this token securely. It will not be shown again!"
  }
}
```

### برای Super Admin

```bash
curl -X POST https://your-domain.com/api/api-tokens/generate \
  -H "Content-Type: application/json" \
  -d '{
    "type": "super_admin",
    "name": "Super Admin Token"
  }'
```

**نکته:** برای Super Admin، `tenant_id` در هر درخواست باید مشخص شود.

---

## 📝 ایجاد ماموریت

### درخواست

```bash
curl -X POST https://your-domain.com/api/api/v1/missions \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "ماموریت تست",
    "description": "توضیحات ماموریت",
    "prompt_content": "این پرامپت برای کپی کردن است",
    "content_title": "محتوای آموزشی",
    "content_url": "https://example.com/content",
    "content_type": "text",
    "content_description": "توضیحات محتوا",
    "points": 10,
    "duration": 30,
    "max_personnel": 5,
    "ai_id": 1,
    "tenant_id": 1
  }'
```

**پارامترهای اجباری:**
- `title`: عنوان ماموریت
- `prompt_content`: محتوای پرامپت

**پارامترهای اختیاری:**
- `description`: توضیحات ماموریت
- `content_title`: عنوان محتوای آموزشی
- `content_url`: لینک محتوای آموزشی
- `content_type`: نوع محتوا (`text`, `video`, `image`, `audio`, `pdf`)
- `content_description`: توضیحات محتوا
- `points`: امتیاز ماموریت (پیش‌فرض: 0)
- `duration`: مدت زمان به دقیقه
- `max_personnel`: حداکثر تعداد پرسنل (پیش‌فرض: 1)
- `ai_id`: شناسه AI پیشنهادی
- `tenant_id`: برای Super Admin اجباری است

**پاسخ موفق:**
```json
{
  "success": true,
  "message": "Mission created successfully",
  "data": {
    "mission": {
      "id": 1,
      "title": "ماموریت تست",
      "description": "توضیحات ماموریت",
      "points": 10,
      "duration": 30,
      "status": "active",
      "prompt": {
        "id": 1,
        "content": "این پرامپت برای کپی کردن است"
      },
      "content": {
        "id": 1,
        "title": "محتوای آموزشی",
        "content_url": "https://example.com/content"
      },
      "ai": {
        "id": 1,
        "name": "کلادی"
      }
    },
    "prompt": {...},
    "content": {...}
  }
}
```

---

## 📋 مشاهده لیست ماموریت‌ها

```bash
curl -X GET "https://your-domain.com/api/api/v1/missions?per_page=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**پارامترهای Query:**
- `per_page`: تعداد نتایج در هر صفحه (پیش‌فرض: 15)
- `page`: شماره صفحه

**پاسخ:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "title": "ماموریت تست",
        "description": "توضیحات ماموریت",
        "points": 10,
        "status": "active",
        "prompt": {...},
        "content": {...},
        "ai": {...}
      }
    ],
    "total": 10,
    "per_page": 15
  }
}
```

---

## 🔍 مشاهده یک ماموریت

```bash
curl -X GET https://your-domain.com/api/api/v1/missions/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**پاسخ:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "ماموریت تست",
    "description": "توضیحات ماموریت",
    "points": 10,
    "status": "active",
    "prompt": {
      "id": 1,
      "content": "این پرامپت برای کپی کردن است"
    },
    "content": {...},
    "contents": [...],
    "ai": {...}
  }
}
```

---

## 📚 اضافه کردن محتوای آموزشی

```bash
curl -X POST https://your-domain.com/api/api/v1/missions/1/content \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "ویدیو آموزشی",
    "content_url": "https://example.com/video.mp4",
    "content_type": "video",
    "description": "توضیحات ویدیو",
    "sort_order": 2
  }'
```

**پارامترهای اجباری:**
- `title`: عنوان محتوا

**پارامترهای اختیاری:**
- `content_url`: لینک محتوا
- `content_type`: نوع محتوا
- `description`: توضیحات
- `sort_order`: ترتیب نمایش

**پاسخ:**
```json
{
  "success": true,
  "message": "Content added successfully",
  "data": {
    "id": 2,
    "title": "ویدیو آموزشی",
    "content_url": "https://example.com/video.mp4",
    "content_type": "video"
  }
}
```

---

## 👤 اختصاص ماموریت به پرسنل

```bash
curl -X POST https://your-domain.com/api/api/v1/missions/1/assign \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "personnel_id": 1
  }'
```

**پارامترهای اجباری:**
- `personnel_id`: شناسه پرسنل

**پاسخ:**
```json
{
  "success": true,
  "message": "Mission assigned successfully",
  "data": {
    "mission_id": 1,
    "personnel_id": 1
  }
}
```

---

## 🔗 ارسال لینک نتیجه

```bash
curl -X POST https://your-domain.com/api/api/v1/missions/1/submit \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "result_link": "https://example.com/my-result",
    "personnel_id": 1,
    "selected_ai_id": 2
  }'
```

**پارامترهای اجباری:**
- `result_link`: لینک نتیجه کار
- `personnel_id`: شناسه پرسنل

**پارامترهای اختیاری:**
- `selected_ai_id`: شناسه AI انتخابی

**پاسخ:**
```json
{
  "success": true,
  "message": "Result submitted successfully",
  "data": {
    "mission_personnel_id": 1,
    "status": "pending_approval",
    "result_link": "https://example.com/my-result"
  }
}
```

---

## ✅ بررسی وضعیت تایید

```bash
curl -X GET https://your-domain.com/api/api/v1/missions/1/status \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**پاسخ:**
```json
{
  "success": true,
  "data": {
    "mission": {
      "id": 1,
      "title": "ماموریت تست",
      "points": 10
    },
    "assignments": [
      {
        "id": 1,
        "personnel": {
          "id": 1,
          "name": "علی احمدی"
        },
        "status": "approved",
        "result_link": "https://example.com/my-result",
        "selected_ai": {
          "id": 2,
          "name": "چت جی‌بی‌تی"
        },
        "approved_at": "2025-12-26 20:00:00",
        "rejected_at": null,
        "rejection_reason": null
      }
    ]
  }
}
```

**وضعیت‌های ممکن:**
- `reserved`: رزرو شده
- `in_progress`: در حال انجام
- `pending_approval`: در انتظار تایید
- `approved`: تایید شده
- `rejected`: رد شده
- `cancelled`: لغو شده

---

## 🎯 سناریوی کامل

### مرحله 1: ایجاد توکن (از طریق CLI)

```bash
# ایجاد توکن برای Tenant
php artisan api:token:generate --type=tenant --tenant-id=1 --name="My API Token"

# توکن را در متغیر ذخیره کنید
export TOKEN="your-token-here"
```

### مرحله 2: ایجاد ماموریت

```bash
# ایجاد ماموریت و ذخیره شناسه
MISSION_ID=$(curl -s -X POST https://your-domain.com/api/api/v1/missions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "ماموریت تست API",
    "description": "این یک ماموریت تست است",
    "prompt_content": "این پرامپت برای کپی کردن است. لطفا آن را در AI مورد نظر خود کپی کنید.",
    "points": 15,
    "duration": 45,
    "max_personnel": 3,
    "ai_id": 1
  }' | jq -r '.data.mission.id')

echo "✅ Mission created: $MISSION_ID"
```

### مرحله 3: اضافه کردن محتوای آموزشی

```bash
curl -X POST https://your-domain.com/api/api/v1/missions/$MISSION_ID/content \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "ویدیو آموزشی",
    "content_url": "https://example.com/training-video.mp4",
    "content_type": "video",
    "description": "این ویدیو نحوه انجام ماموریت را آموزش می‌دهد",
    "sort_order": 1
  }'
```

### مرحله 4: مشاهده ماموریت در لیست

```bash
curl -X GET "https://your-domain.com/api/api/v1/missions" \
  -H "Authorization: Bearer $TOKEN" | jq '.data.data[] | {id, title, points}'
```

### مرحله 5: اختصاص ماموریت به پرسنل

```bash
export PERSONNEL_ID=1

curl -X POST https://your-domain.com/api/api/v1/missions/$MISSION_ID/assign \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"personnel_id\": $PERSONNEL_ID}"
```

### مرحله 6: ارسال لینک نتیجه

```bash
curl -X POST https://your-domain.com/api/api/v1/missions/$MISSION_ID/submit \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"result_link\": \"https://example.com/my-completed-work\",
    \"personnel_id\": $PERSONNEL_ID,
    \"selected_ai_id\": 2
  }"
```

### مرحله 7: بررسی وضعیت تایید

```bash
# بررسی وضعیت
curl -s -X GET https://your-domain.com/api/api/v1/missions/$MISSION_ID/status \
  -H "Authorization: Bearer $TOKEN" | jq '.data.assignments[0] | {status, approved_at, rejected_at}'
```

**پاسخ (قبل از تایید):**
```json
{
  "assignments": [{
    "status": "pending_approval",
    "approved_at": null
  }]
}
```

**پاسخ (بعد از تایید):**
```json
{
  "assignments": [{
    "status": "approved",
    "approved_at": "2025-12-26 20:30:00"
  }]
}
```

---

## 📝 مثال‌های کامل curl

### مثال 1: ایجاد ماموریت کامل

```bash
curl -X POST https://your-domain.com/api/api/v1/missions \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "نوشتن مقاله درباره AI",
    "description": "مقاله باید حداقل 1000 کلمه باشد",
    "prompt_content": "مقاله‌ای درباره تاثیر هوش مصنوعی بر صنعت بنویسید. مقاله باید شامل مقدمه، بدنه اصلی و نتیجه‌گیری باشد.",
    "content_title": "راهنمای نوشتن مقاله",
    "content_url": "https://example.com/article-guide.pdf",
    "content_type": "pdf",
    "points": 20,
    "duration": 60,
    "max_personnel": 10,
    "ai_id": 1
  }'
```

### مثال 2: اضافه کردن چند محتوای آموزشی

```bash
# محتوای اول
curl -X POST https://your-domain.com/api/api/v1/missions/1/content \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "ویدیو آموزشی - بخش 1",
    "content_url": "https://example.com/video1.mp4",
    "content_type": "video",
    "sort_order": 1
  }'

# محتوای دوم
curl -X POST https://your-domain.com/api/api/v1/missions/1/content \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "ویدیو آموزشی - بخش 2",
    "content_url": "https://example.com/video2.mp4",
    "content_type": "video",
    "sort_order": 2
  }'
```

### مثال 3: ارسال نتیجه با AI انتخابی

```bash
curl -X POST https://your-domain.com/api/api/v1/missions/1/submit \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "result_link": "https://virgool.io/@username/my-article",
    "personnel_id": 1,
    "selected_ai_id": 2
  }'
```

---

## ⚠️ خطاهای رایج

### خطای 401: Unauthorized
```json
{
  "success": false,
  "message": "API token is required"
}
```
**راه حل:** توکن را در header یا query parameter ارسال کنید.

### خطای 403: Forbidden
```json
{
  "success": false,
  "message": "Access denied"
}
```
**راه حل:** مطمئن شوید که توکن شما به Tenant درست متصل است.

### خطای 422: Validation Error
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "title": ["The title field is required."]
  }
}
```
**راه حل:** تمام فیلدهای اجباری را ارسال کنید.

### خطای 404: Not Found
```json
{
  "success": false,
  "message": "Mission not found"
}
```
**راه حل:** شناسه ماموریت را بررسی کنید.

---

## 🔒 امنیت

1. **توکن را محرمانه نگه دارید:** توکن را هرگز در کدهای frontend یا public repositories قرار ندهید.
2. **از HTTPS استفاده کنید:** همیشه از HTTPS برای ارتباط با API استفاده کنید.
3. **توکن را rotate کنید:** در صورت نشت توکن، آن را غیرفعال کرده و توکن جدید ایجاد کنید.
4. **Rate Limiting:** API دارای rate limiting است. در صورت نیاز به درخواست‌های بیشتر، با تیم تماس بگیرید.

---

## 📞 پشتیبانی

در صورت بروز مشکل:
1. لاگ‌های API را بررسی کنید
2. خطای دقیق را ذخیره کنید
3. با تیم توسعه تماس بگیرید

---

**نسخه:** 1.0.0  
**تاریخ به‌روزرسانی:** 2025-12-26

