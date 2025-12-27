# راهنمای استفاده از Metadata API

این راهنما نحوه استفاده از Metadata API را برای دریافت اطلاعات اولیه و تنظیم کلاینت‌ها توضیح می‌دهد.

## 📋 فهرست مطالب

1. [معرفی](#معرفی)
2. [Endpoint Metadata](#endpoint-metadata)
3. [Endpoint Test Token](#endpoint-test-token)
4. [سناریوی کامل](#سناریوی-کامل)
5. [مثال‌های کد](#مثال‌های-کد)

---

## معرفی

Metadata API دو endpoint اصلی دارد:

1. **GET /api/v1/metadata**: دریافت تمام اطلاعات لازم (بدون نیاز به توکن)
2. **GET /api/v1/test-token**: تست توکن و دریافت اطلاعات آن (نیاز به توکن)

این API برای کلاینت‌های Python، Golang یا هر زبان دیگری طراحی شده است تا بتوانند:
- لیست Tenants را دریافت کنند
- لیست AI/LLM ها را دریافت کنند
- انواع محتوا و وضعیت‌ها را ببینند
- توکن خود را تست کنند

---

## Endpoint Metadata

### GET /api/v1/metadata

این endpoint **بدون نیاز به توکن** تمام اطلاعات لازم را برمی‌گرداند.

#### Request

```bash
curl -X GET https://your-domain.com/api/api/v1/metadata
```

#### Response

```json
{
  "success": true,
  "data": {
    "tenants": [
      {
        "id": 1,
        "tenant_name": "Tenant 1"
      },
      {
        "id": 2,
        "tenant_name": "Tenant 2"
      }
    ],
    "ai_llms": [
      {
        "id": 1,
        "name": "کلادی",
        "slug": "claude",
        "description": "هوش مصنوعی کلادی از شرکت Anthropic",
        "url": "https://claude.ai",
        "sort_order": 1
      },
      {
        "id": 2,
        "name": "چت جی‌بی‌تی",
        "slug": "chatgpt",
        "description": "هوش مصنوعی ChatGPT از شرکت OpenAI",
        "url": "https://chat.openai.com",
        "sort_order": 2
      }
    ],
    "content_types": [
      {
        "value": "text",
        "label": "Text"
      },
      {
        "value": "video",
        "label": "Video"
      },
      {
        "value": "image",
        "label": "Image"
      },
      {
        "value": "audio",
        "label": "Audio"
      },
      {
        "value": "pdf",
        "label": "PDF"
      }
    ],
    "mission_statuses": [
      {
        "value": "active",
        "label": "Active"
      },
      {
        "value": "inactive",
        "label": "Inactive"
      }
    ],
    "assignment_statuses": [
      {
        "value": "reserved",
        "label": "Reserved"
      },
      {
        "value": "in_progress",
        "label": "In Progress"
      },
      {
        "value": "pending_approval",
        "label": "Pending Approval"
      },
      {
        "value": "approved",
        "label": "Approved"
      },
      {
        "value": "rejected",
        "label": "Rejected"
      },
      {
        "value": "cancelled",
        "label": "Cancelled"
      }
    ],
    "api_info": {
      "base_url": "https://your-domain.com/api/api/v1",
      "version": "1.0.0",
      "authentication": "Bearer Token or X-API-Token header"
    }
  }
}
```

#### Query Parameters (اختیاری)

- `tenant_id`: اگر می‌خواهید فقط یک Tenant خاص را ببینید
- `is_super_admin`: اگر می‌خواهید به عنوان Super Admin عمل کنید

```bash
# فقط Tenant خاص
curl -X GET "https://your-domain.com/api/api/v1/metadata?tenant_id=1"

# به عنوان Super Admin
curl -X GET "https://your-domain.com/api/api/v1/metadata?is_super_admin=true"
```

---

## Endpoint Test Token

### GET /api/v1/test-token

این endpoint نیاز به توکن دارد و اطلاعات توکن را برمی‌گرداند.

#### Request

```bash
curl -X GET https://your-domain.com/api/api/v1/test-token \
  -H "Authorization: Bearer YOUR_TOKEN"
```

یا

```bash
curl -X GET https://your-domain.com/api/api/v1/test-token \
  -H "X-API-Token: YOUR_TOKEN"
```

#### Response

```json
{
  "success": true,
  "message": "Token is valid",
  "data": {
    "token_id": 1,
    "type": "tenant",
    "tenant_id": 1,
    "tenant_name": "Tenant 1",
    "name": "My Token",
    "is_active": true,
    "last_used_at": "2025-12-26 10:30:00"
  }
}
```

#### Error Response

```json
{
  "success": false,
  "message": "API token is required"
}
```

---

## سناریوی کامل

### مرحله 1: دریافت Metadata

```bash
# دریافت تمام اطلاعات
METADATA=$(curl -s -X GET "https://your-domain.com/api/api/v1/metadata")
echo $METADATA | jq '.'
```

### مرحله 2: استخراج اطلاعات

```bash
# استخراج Tenant ID
TENANT_ID=$(echo $METADATA | jq -r '.data.tenants[0].id')

# استخراج AI ID
AI_ID=$(echo $METADATA | jq -r '.data.ai_llms[0].id')

# استخراج نام AI
AI_NAME=$(echo $METADATA | jq -r '.data.ai_llms[0].name')

echo "Tenant ID: $TENANT_ID"
echo "AI ID: $AI_ID ($AI_NAME)"
```

### مرحله 3: تست توکن

```bash
TOKEN="YOUR_TOKEN_HERE"

# تست توکن
TOKEN_INFO=$(curl -s -X GET "https://your-domain.com/api/api/v1/test-token" \
  -H "Authorization: Bearer $TOKEN")

echo $TOKEN_INFO | jq '.'
```

### مرحله 4: ایجاد ماموریت با استفاده از Metadata

```bash
curl -X POST "https://your-domain.com/api/api/v1/missions" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"title\": \"ماموریت تست\",
    \"prompt_content\": \"پرامپت تست\",
    \"points\": 10,
    \"ai_id\": $AI_ID,
    \"tenant_id\": $TENANT_ID
  }"
```

---

## مثال‌های کد

### Python

```python
import requests

BASE_URL = "https://your-domain.com/api/api/v1"
TOKEN = "YOUR_TOKEN_HERE"

# 1. دریافت Metadata
response = requests.get(f"{BASE_URL}/metadata")
metadata = response.json()

tenants = metadata['data']['tenants']
ai_llms = metadata['data']['ai_llms']

tenant_id = tenants[0]['id']
ai_id = ai_llms[0]['id']

# 2. تست توکن
headers = {'Authorization': f'Bearer {TOKEN}'}
response = requests.get(f"{BASE_URL}/test-token", headers=headers)
token_info = response.json()

# 3. ایجاد ماموریت
mission_data = {
    'title': 'ماموریت تست',
    'prompt_content': 'پرامپت تست',
    'points': 10,
    'ai_id': ai_id,
    'tenant_id': tenant_id
}
response = requests.post(
    f"{BASE_URL}/missions",
    json=mission_data,
    headers=headers
)
mission = response.json()
```

برای مثال کامل، به فایل `examples/python_client_example.py` مراجعه کنید.

### Golang

```go
package main

import (
    "encoding/json"
    "fmt"
    "net/http"
)

func main() {
    baseURL := "https://your-domain.com/api/api/v1"
    token := "YOUR_TOKEN_HERE"
    
    // 1. دریافت Metadata
    resp, _ := http.Get(baseURL + "/metadata")
    var metadata map[string]interface{}
    json.NewDecoder(resp.Body).Decode(&metadata)
    
    // 2. تست توکن
    req, _ := http.NewRequest("GET", baseURL+"/test-token", nil)
    req.Header.Set("Authorization", "Bearer "+token)
    client := &http.Client{}
    resp, _ = client.Do(req)
    var tokenInfo map[string]interface{}
    json.NewDecoder(resp.Body).Decode(&tokenInfo)
    
    fmt.Println("Token is valid:", tokenInfo["success"])
}
```

برای مثال کامل، به فایل `examples/golang_client_example.go` مراجعه کنید.

### JavaScript/Node.js

```javascript
const axios = require('axios');

const BASE_URL = 'https://your-domain.com/api/api/v1';
const TOKEN = 'YOUR_TOKEN_HERE';

// 1. دریافت Metadata
async function getMetadata() {
    const response = await axios.get(`${BASE_URL}/metadata`);
    return response.data;
}

// 2. تست توکن
async function testToken() {
    const response = await axios.get(`${BASE_URL}/test-token`, {
        headers: {
            'Authorization': `Bearer ${TOKEN}`
        }
    });
    return response.data;
}

// استفاده
(async () => {
    const metadata = await getMetadata();
    console.log('Tenants:', metadata.data.tenants);
    console.log('AI/LLMs:', metadata.data.ai_llms);
    
    const tokenInfo = await testToken();
    console.log('Token info:', tokenInfo.data);
})();
```

---

## نکات مهم

1. **Metadata بدون توکن**: endpoint `/metadata` نیاز به توکن ندارد و می‌توانید از آن برای دریافت اطلاعات اولیه استفاده کنید.

2. **تست توکن**: همیشه قبل از استفاده از API، توکن خود را با `/test-token` تست کنید.

3. **استخراج اطلاعات**: از Metadata می‌توانید Tenant ID و AI ID را استخراج کنید و در ایجاد ماموریت استفاده کنید.

4. **Cache کردن**: می‌توانید Metadata را cache کنید چون تغییرات زیادی ندارد.

5. **Error Handling**: همیشه خطاها را handle کنید:

```python
try:
    response = requests.get(f"{BASE_URL}/metadata")
    response.raise_for_status()
    metadata = response.json()
except requests.exceptions.RequestException as e:
    print(f"Error: {e}")
```

---

**نسخه:** 1.0.0  
**تاریخ:** 2025-12-26

