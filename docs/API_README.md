# مستندات کامل API ماموریت‌ها

این مستندات راهنمای کامل استفاده از API برای مدیریت ماموریت‌ها، پرامپت‌ها و محتوا است.

## 📋 فهرست مطالب

1. [راهنمای سریع](#راهنمای-سریع)
2. [مستندات کامل](#مستندات-کامل)
3. [Metadata API](#metadata-api)
4. [Swagger Documentation](#swagger-documentation)
5. [مثال‌های کد](#مثال‌های-کد)
6. [نکات مهم](#نکات-مهم)

---

## راهنمای سریع

### 1. دریافت Metadata

```bash
curl -X GET https://your-domain.com/api/api/v1/metadata
```

### 2. تست توکن

```bash
curl -X GET https://your-domain.com/api/api/v1/test-token \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. ایجاد ماموریت

```bash
curl -X POST https://your-domain.com/api/api/v1/missions \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "ماموریت تست",
    "prompt_content": "پرامپت تست",
    "points": 10
  }'
```

---

## مستندات کامل

### 📄 [مستندات اصلی API](API_MISSION_DOCUMENTATION.md)

مستندات کامل تمام endpoints و نحوه استفاده از آن‌ها.

**شامل:**
- احراز هویت
- ایجاد توکن API
- ایجاد ماموریت
- مشاهده لیست ماموریت‌ها
- اضافه کردن محتوای آموزشی
- ارسال لینک نتیجه
- بررسی وضعیت تایید

### 📄 [راهنمای سریع API](API_QUICK_START.md)

دستورات curl یک خطی برای تست سریع API.

---

## Metadata API

### 📄 [راهنمای Metadata API](API_METADATA_GUIDE.md)

راهنمای کامل استفاده از Metadata API برای دریافت اطلاعات اولیه.

**شامل:**
- دریافت لیست Tenants
- دریافت لیست AI/LLM ها
- دریافت انواع محتوا و وضعیت‌ها
- تست توکن

**Endpoints:**
- `GET /api/v1/metadata` - دریافت تمام metadata (بدون نیاز به توکن)
- `GET /api/v1/test-token` - تست توکن (نیاز به توکن)

---

## Swagger Documentation

### 📄 [راهنمای نصب Swagger](SWAGGER_SETUP.md)

راهنمای نصب و راه‌اندازی Swagger/OpenAPI برای مستندات تعاملی.

### 📄 [مستندات Swagger](SWAGGER_DOCUMENTATION.md)

مستندات کامل Swagger/OpenAPI با تمام endpoints و schemas.

**دسترسی به Swagger UI:**
```
http://your-domain.com/api/documentation
```

**فایل OpenAPI Specification:**
- `swagger.yaml` - فایل اصلی OpenAPI Specification

---

## مثال‌های کد

### 🐍 Python

**فایل:** `examples/python_client_example.py`

کلاینت کامل Python برای استفاده از API شامل:
- دریافت Metadata
- تست توکن
- ایجاد ماموریت
- اضافه کردن محتوا
- اختصاص ماموریت
- ارسال نتیجه

**استفاده:**

```bash
# نصب dependencies
pip install requests

# اجرای مثال
python examples/python_client_example.py
```

### 🐹 Golang

**فایل:** `examples/golang_client_example.go`

کلاینت کامل Golang برای استفاده از API.

**استفاده:**

```bash
# اجرای مثال
go run examples/golang_client_example.go
```

### 📝 JavaScript/Node.js

مثال JavaScript در [راهنمای Metadata API](API_METADATA_GUIDE.md#مثال‌های-کد) موجود است.

---

## نکات مهم

### 🔐 احراز هویت

تمام endpoints (به جز `/metadata`) نیاز به توکن دارند:

```bash
# روش 1: Bearer Token
Authorization: Bearer YOUR_TOKEN

# روش 2: X-API-Token Header
X-API-Token: YOUR_TOKEN
```

### 🎯 ایجاد توکن

```bash
# برای Tenant
php artisan api:token:generate --type=tenant --tenant-id=1 --name="My Token"

# برای Super Admin
php artisan api:token:generate --type=super_admin --name="Super Admin Token"
```

### 📊 Metadata API

- **بدون نیاز به توکن**: endpoint `/metadata` نیاز به توکن ندارد
- **Cache کردن**: می‌توانید Metadata را cache کنید
- **استخراج اطلاعات**: از Metadata می‌توانید Tenant ID و AI ID را استخراج کنید

### 🚀 سناریوی کامل

1. دریافت Metadata برای استخراج Tenant ID و AI ID
2. تست توکن برای اطمینان از صحت
3. ایجاد ماموریت با استفاده از اطلاعات Metadata
4. اضافه کردن محتوای آموزشی
5. اختصاص ماموریت به پرسنل
6. ارسال لینک نتیجه

### ⚠️ Error Handling

همیشه خطاها را handle کنید:

```python
try:
    response = requests.post(url, json=data, headers=headers)
    response.raise_for_status()
    return response.json()
except requests.exceptions.HTTPError as e:
    print(f"HTTP Error: {e}")
    print(f"Response: {e.response.text}")
except requests.exceptions.RequestException as e:
    print(f"Request Error: {e}")
```

### 📝 Validation

- تمام فیلدهای required باید ارسال شوند
- `tenant_id` برای Super Admin اجباری است
- `prompt_content` باید به صورت خالص ارسال شود (بدون توضیحات اضافی)

---

## 🔗 لینک‌های مفید

- [مستندات اصلی API](API_MISSION_DOCUMENTATION.md)
- [راهنمای سریع](API_QUICK_START.md)
- [راهنمای Metadata API](API_METADATA_GUIDE.md)
- [راهنمای نصب Swagger](SWAGGER_SETUP.md)
- [مستندات Swagger](SWAGGER_DOCUMENTATION.md)
- [مثال Python](examples/python_client_example.py)
- [مثال Golang](examples/golang_client_example.go)

---

## 📞 پشتیبانی

برای سوالات و مشکلات:
- بررسی [مستندات کامل](API_MISSION_DOCUMENTATION.md)
- بررسی [Swagger UI](http://your-domain.com/api/documentation)
- بررسی [مثال‌های کد](examples/)

---

**نسخه:** 1.0.0  
**تاریخ:** 2025-12-26  
**آخرین به‌روزرسانی:** 2025-12-26

