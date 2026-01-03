# راهنمای سریع API - دستورات یک خطی

این فایل شامل دستورات curl یک خطی برای تست سریع API است.

## 🔧 تنظیمات اولیه

```bash
# تعریف متغیرها (قبل از استفاده)
export BASE_URL="https://your-domain.com"
export TOKEN="YOUR_TOKEN_HERE"
export TENANT_ID=1
export MISSION_ID=1
export PERSONNEL_ID=1
```

---

## 🔑 1. ایجاد توکن (از طریق CLI)

```bash
# برای Tenant
php artisan api:token:generate --type=tenant --tenant-id=1 --name="Test Token"

# برای Super Admin
php artisan api:token:generate --type=super_admin --name="Super Admin Token"
```

**نکته:** توکن را در متغیر `TOKEN` ذخیره کنید:
```bash
export TOKEN="abc123def456..."
```

---

## 📝 2. ایجاد ماموریت

```bash
curl -X POST "$BASE_URL/api/api/v1/missions" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"ماموریت تست","description":"توضیحات","prompt_content":"این پرامپت برای کپی کردن است","points":10,"duration":30,"max_personnel":5,"ai_id":1,"tenant_id":'$TENANT_ID'}'
```

**ذخیره شناسه ماموریت:**
```bash
export MISSION_ID=$(curl -s -X POST "$BASE_URL/api/api/v1/missions" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"ماموریت تست","prompt_content":"پرامپت تست","points":10,"tenant_id":'$TENANT_ID'}' | jq -r '.data.mission.id')
echo "Mission ID: $MISSION_ID"
```

---

## 📋 3. مشاهده لیست ماموریت‌ها

```bash
curl -X GET "$BASE_URL/api/api/v1/missions" -H "Authorization: Bearer $TOKEN"
```

**با pagination:**
```bash
curl -X GET "$BASE_URL/api/api/v1/missions?per_page=10&page=1" -H "Authorization: Bearer $TOKEN"
```

---

## 🔍 4. مشاهده یک ماموریت

```bash
curl -X GET "$BASE_URL/api/api/v1/missions/$MISSION_ID" -H "Authorization: Bearer $TOKEN"
```

---

## 📚 5. اضافه کردن محتوای آموزشی

```bash
curl -X POST "$BASE_URL/api/api/v1/missions/$MISSION_ID/content" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"ویدیو آموزشی","content_url":"https://example.com/video.mp4","content_type":"video","description":"توضیحات ویدیو","sort_order":1}'
```

**مثال: اضافه کردن PDF:**
```bash
curl -X POST "$BASE_URL/api/api/v1/missions/$MISSION_ID/content" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"راهنمای PDF","content_url":"https://example.com/guide.pdf","content_type":"pdf","sort_order":2}'
```

---

## 👤 6. اختصاص ماموریت به پرسنل

```bash
curl -X POST "$BASE_URL/api/api/v1/missions/$MISSION_ID/assign" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"personnel_id":'$PERSONNEL_ID'}'
```

---

## 🔗 7. ارسال لینک نتیجه

```bash
curl -X POST "$BASE_URL/api/api/v1/missions/$MISSION_ID/submit" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"result_link":"https://example.com/my-result","personnel_id":'$PERSONNEL_ID',"selected_ai_id":2}'
```

---

## ✅ 8. بررسی وضعیت تایید

```bash
curl -X GET "$BASE_URL/api/api/v1/missions/$MISSION_ID/status" -H "Authorization: Bearer $TOKEN"
```

**فیلتر کردن فقط assignments با status خاص:**
```bash
curl -s -X GET "$BASE_URL/api/api/v1/missions/$MISSION_ID/status" -H "Authorization: Bearer $TOKEN" | jq '.data.assignments[] | select(.status == "approved")'
```

---

## 🎯 سناریوی کامل (همه در یک خط)

### مرحله 1: ایجاد ماموریت
```bash
MISSION_ID=$(curl -s -X POST "$BASE_URL/api/api/v1/missions" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"ماموریت تست API","prompt_content":"این پرامپت برای کپی کردن است","points":15,"duration":45,"max_personnel":3,"ai_id":1,"tenant_id":'$TENANT_ID'}' | jq -r '.data.mission.id') && echo "Mission ID: $MISSION_ID"
```

### مرحله 2: اضافه کردن محتوای آموزشی
```bash
curl -X POST "$BASE_URL/api/api/v1/missions/$MISSION_ID/content" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"ویدیو آموزشی","content_url":"https://example.com/training.mp4","content_type":"video","sort_order":1}'
```

### مرحله 3: مشاهده ماموریت
```bash
curl -X GET "$BASE_URL/api/api/v1/missions/$MISSION_ID" -H "Authorization: Bearer $TOKEN" | jq '.'
```

### مرحله 4: اختصاص ماموریت به پرسنل
```bash
curl -X POST "$BASE_URL/api/api/v1/missions/$MISSION_ID/assign" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d "{\"personnel_id\":$PERSONNEL_ID}"
```

### مرحله 5: ارسال لینک نتیجه
```bash
curl -X POST "$BASE_URL/api/api/v1/missions/$MISSION_ID/submit" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"result_link":"https://example.com/my-work","personnel_id":'$PERSONNEL_ID',"selected_ai_id":2}'
```

### مرحله 6: بررسی وضعیت
```bash
curl -X GET "$BASE_URL/api/api/v1/missions/$MISSION_ID/status" -H "Authorization: Bearer $TOKEN" | jq '.data.assignments[0].status'
```

---

## 📊 مثال‌های پیشرفته

### ایجاد ماموریت با تمام فیلدها
```bash
curl -X POST "$BASE_URL/api/api/v1/missions" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"نوشتن مقاله","description":"مقاله باید حداقل 1000 کلمه باشد","prompt_content":"مقاله‌ای درباره تاثیر AI بر صنعت بنویسید","content_title":"راهنمای نوشتن","content_url":"https://example.com/guide.pdf","content_type":"pdf","points":20,"duration":60,"max_personnel":10,"ai_id":1,"tenant_id":'$TENANT_ID'}'
```

### اضافه کردن چند محتوای آموزشی
```bash
# محتوای اول
curl -X POST "$BASE_URL/api/api/v1/missions/$MISSION_ID/content" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"ویدیو 1","content_url":"https://example.com/v1.mp4","content_type":"video","sort_order":1}'

# محتوای دوم
curl -X POST "$BASE_URL/api/api/v1/missions/$MISSION_ID/content" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"ویدیو 2","content_url":"https://example.com/v2.mp4","content_type":"video","sort_order":2}'
```

### بررسی وضعیت با jq
```bash
# فقط status
curl -s -X GET "$BASE_URL/api/api/v1/missions/$MISSION_ID/status" -H "Authorization: Bearer $TOKEN" | jq -r '.data.assignments[0].status'

# تمام اطلاعات
curl -s -X GET "$BASE_URL/api/api/v1/missions/$MISSION_ID/status" -H "Authorization: Bearer $TOKEN" | jq '.data'

# فقط assignments تایید شده
curl -s -X GET "$BASE_URL/api/api/v1/missions/$MISSION_ID/status" -H "Authorization: Bearer $TOKEN" | jq '.data.assignments[] | select(.status == "approved")'
```

---

## 🐛 عیب‌یابی

### بررسی صحت توکن
```bash
curl -X GET "$BASE_URL/api/api/v1/missions" -H "Authorization: Bearer $TOKEN" -v
```

### بررسی خطاها
```bash
curl -X POST "$BASE_URL/api/api/v1/missions" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title":"تست"}' -v 2>&1 | grep -A 20 "< HTTP"
```

### تست بدون jq
```bash
# اگر jq نصب نیست، از grep استفاده کنید
curl -s -X GET "$BASE_URL/api/api/v1/missions/$MISSION_ID" -H "Authorization: Bearer $TOKEN" | grep -o '"id":[0-9]*' | head -1
```

---

## 📝 نکات مهم

1. **ذخیره توکن:** همیشه توکن را در متغیر محیطی ذخیره کنید
2. **HTTPS:** همیشه از HTTPS استفاده کنید
3. **jq:** برای پردازش JSON، `jq` را نصب کنید: `sudo apt install jq` یا `brew install jq`
4. **خطاها:** در صورت خطا، از `-v` برای مشاهده جزئیات استفاده کنید

---

## 🔄 مثال کامل با متغیرها

```bash
# تنظیمات
export BASE_URL="https://your-domain.com"
export TOKEN="your-token-here"
export TENANT_ID=1
export PERSONNEL_ID=1

# ایجاد ماموریت
MISSION_ID=$(curl -s -X POST "$BASE_URL/api/api/v1/missions" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"title\":\"تست\",\"prompt_content\":\"پرامپت\",\"points\":10,\"tenant_id\":$TENANT_ID}" \
  | jq -r '.data.mission.id')

echo "✅ Mission created: $MISSION_ID"

# اضافه کردن محتوا
curl -X POST "$BASE_URL/api/api/v1/missions/$MISSION_ID/content" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"آموزش","content_url":"https://example.com","content_type":"text","sort_order":1}'

# مشاهده
curl -X GET "$BASE_URL/api/api/v1/missions/$MISSION_ID" \
  -H "Authorization: Bearer $TOKEN" | jq '.'

# ارسال نتیجه
curl -X POST "$BASE_URL/api/api/v1/missions/$MISSION_ID/submit" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"result_link\":\"https://example.com/result\",\"personnel_id\":$PERSONNEL_ID,\"selected_ai_id\":2}"

# بررسی وضعیت
curl -X GET "$BASE_URL/api/api/v1/missions/$MISSION_ID/status" \
  -H "Authorization: Bearer $TOKEN" | jq '.data.assignments[0]'
```

---

**نسخه:** 1.0.0  
**تاریخ:** 2025-12-26

