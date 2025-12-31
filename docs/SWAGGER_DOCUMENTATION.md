# مستندات Swagger/OpenAPI برای API ماموریت‌ها

این فایل شامل مستندات کامل Swagger/OpenAPI برای تمام endpoints API است.

## 📋 نصب و راه‌اندازی Swagger

### نصب Package

```bash
composer require darkaonline/l5-swagger
```

### Publish Configuration

```bash
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

### Generate Documentation

```bash
php artisan l5-swagger:generate
```

---

## 📄 OpenAPI Specification (YAML)

```yaml
openapi: 3.0.0
info:
  title: Mission Management API
  description: API برای مدیریت ماموریت‌ها، پرامپت‌ها و محتوا
  version: 1.0.0
  contact:
    name: API Support
    email: support@example.com

servers:
  - url: https://your-domain.com/api/api/v1
    description: Production server
  - url: http://localhost:8000/api/api/v1
    description: Local development server

security:
  - BearerAuth: []
  - ApiTokenAuth: []

components:
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
    ApiTokenAuth:
      type: apiKey
      in: header
      name: X-API-Token

  schemas:
    SuccessResponse:
      type: object
      properties:
        success:
          type: boolean
          example: true
        message:
          type: string
        data:
          type: object

    ErrorResponse:
      type: object
      properties:
        success:
          type: boolean
          example: false
        message:
          type: string
        errors:
          type: object

    Mission:
      type: object
      properties:
        id:
          type: integer
          example: 1
        title:
          type: string
          example: "ماموریت تست"
        description:
          type: string
          nullable: true
        points:
          type: integer
          example: 10
        duration:
          type: integer
          nullable: true
          example: 30
        max_personnel:
          type: integer
          example: 5
        status:
          type: string
          enum: [active, inactive]
          example: "active"
        prompt:
          $ref: '#/components/schemas/Prompt'
        content:
          $ref: '#/components/schemas/Content'
        ai:
          $ref: '#/components/schemas/AiLlm'

    Prompt:
      type: object
      properties:
        id:
          type: integer
        content:
          type: string

    Content:
      type: object
      properties:
        id:
          type: integer
        title:
          type: string
        content_type:
          type: string
          enum: [text, video, image, audio, pdf]
        content_url:
          type: string
          nullable: true
        description:
          type: string
          nullable: true

    AiLlm:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
          example: "کلادی"
        slug:
          type: string
          example: "claude"
        url:
          type: string
          nullable: true
          example: "https://claude.ai"

    Tenant:
      type: object
      properties:
        id:
          type: integer
        tenant_name:
          type: string

    CreateMissionRequest:
      type: object
      required:
        - title
        - prompt_content
      properties:
        title:
          type: string
          example: "ماموریت تست"
        description:
          type: string
          nullable: true
        prompt_content:
          type: string
          example: "این پرامپت برای کپی کردن است"
        content_title:
          type: string
          nullable: true
        content_url:
          type: string
          nullable: true
        content_type:
          type: string
          enum: [text, video, image, audio, pdf]
          nullable: true
        content_description:
          type: string
          nullable: true
        points:
          type: integer
          default: 0
        duration:
          type: integer
          nullable: true
        max_personnel:
          type: integer
          default: 1
        ai_id:
          type: integer
          nullable: true
        tenant_id:
          type: integer
          description: "Required for super_admin tokens"

    AddContentRequest:
      type: object
      required:
        - title
      properties:
        title:
          type: string
        content_url:
          type: string
          nullable: true
        content_type:
          type: string
          enum: [text, video, image, audio, pdf]
          nullable: true
        description:
          type: string
          nullable: true
        sort_order:
          type: integer
          default: 1

    SubmitResultRequest:
      type: object
      required:
        - result_link
        - personnel_id
      properties:
        result_link:
          type: string
          format: uri
          example: "https://example.com/my-result"
        personnel_id:
          type: integer
        selected_ai_id:
          type: integer
          nullable: true

    AssignMissionRequest:
      type: object
      required:
        - personnel_id
      properties:
        personnel_id:
          type: integer

paths:
  /metadata:
    get:
      tags:
        - Metadata
      summary: دریافت اطلاعات اولیه
      description: دریافت لیست Tenants، AI/LLMs و سایر اطلاعات لازم
      security: []
      responses:
        '200':
          description: موفق
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    type: object
                    properties:
                      tenants:
                        type: array
                        items:
                          $ref: '#/components/schemas/Tenant'
                      ai_llms:
                        type: array
                        items:
                          $ref: '#/components/schemas/AiLlm'
                      content_types:
                        type: array
                      mission_statuses:
                        type: array
                      assignment_statuses:
                        type: array

  /test-token:
    get:
      tags:
        - Authentication
      summary: تست توکن
      description: بررسی صحت و اعتبار توکن API
      responses:
        '200':
          description: توکن معتبر است
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SuccessResponse'
        '401':
          description: توکن نامعتبر
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'

  /missions:
    post:
      tags:
        - Missions
      summary: ایجاد ماموریت جدید
      description: ایجاد یک ماموریت جدید با پرامپت و محتوا
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateMissionRequest'
      responses:
        '201':
          description: ماموریت با موفقیت ایجاد شد
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SuccessResponse'
        '422':
          description: خطای Validation
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'

    get:
      tags:
        - Missions
      summary: لیست ماموریت‌ها
      description: دریافت لیست ماموریت‌های Tenant
      parameters:
        - name: per_page
          in: query
          schema:
            type: integer
            default: 15
        - name: page
          in: query
          schema:
            type: integer
            default: 1
      responses:
        '200':
          description: موفق
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    type: object
                    properties:
                      data:
                        type: array
                        items:
                          $ref: '#/components/schemas/Mission'

  /missions/{id}:
    get:
      tags:
        - Missions
      summary: مشاهده یک ماموریت
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: موفق
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SuccessResponse'
        '404':
          description: ماموریت یافت نشد

  /missions/{id}/content:
    post:
      tags:
        - Missions
      summary: اضافه کردن محتوای آموزشی
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/AddContentRequest'
      responses:
        '201':
          description: محتوا با موفقیت اضافه شد

  /missions/{id}/assign:
    post:
      tags:
        - Missions
      summary: اختصاص ماموریت به پرسنل
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/AssignMissionRequest'
      responses:
        '200':
          description: ماموریت با موفقیت اختصاص یافت

  /missions/{id}/submit:
    post:
      tags:
        - Missions
      summary: ارسال لینک نتیجه
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/SubmitResultRequest'
      responses:
        '200':
          description: لینک با موفقیت ثبت شد

  /missions/{id}/status:
    get:
      tags:
        - Missions
      summary: بررسی وضعیت تایید
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: موفق
```

---

## 🔧 استفاده از Swagger UI

بعد از نصب و generate، می‌توانید به آدرس زیر دسترسی داشته باشید:

```
http://your-domain.com/api/documentation
```

---

## 📝 مثال‌های Request/Response

### GET /api/v1/metadata

**Request:**
```bash
curl -X GET https://your-domain.com/api/api/v1/metadata
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tenants": [
      {"id": 1, "tenant_name": "Tenant 1"},
      {"id": 2, "tenant_name": "Tenant 2"}
    ],
    "ai_llms": [
      {"id": 1, "name": "کلادی", "slug": "claude", "url": "https://claude.ai"},
      {"id": 2, "name": "چت جی‌بی‌تی", "slug": "chatgpt", "url": "https://chat.openai.com"}
    ],
    "content_types": [
      {"value": "text", "label": "Text"},
      {"value": "video", "label": "Video"}
    ],
    "mission_statuses": [...],
    "assignment_statuses": [...]
  }
}
```

### GET /api/v1/test-token

**Request:**
```bash
curl -X GET https://your-domain.com/api/api/v1/test-token \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "message": "Token is valid",
  "data": {
    "token_id": 1,
    "type": "tenant",
    "tenant_id": 1,
    "tenant_name": "Tenant 1",
    "is_active": true
  }
}
```

---

## 🎯 سناریوی کامل با Metadata

### مرحله 1: دریافت Metadata

```bash
# دریافت تمام اطلاعات لازم
METADATA=$(curl -s -X GET "$BASE_URL/api/api/v1/metadata")
echo $METADATA | jq '.'
```

### مرحله 2: استخراج اطلاعات

```bash
# استخراج Tenant ID
TENANT_ID=$(echo $METADATA | jq -r '.data.tenants[0].id')

# استخراج AI ID
AI_ID=$(echo $METADATA | jq -r '.data.ai_llms[0].id')

echo "Tenant ID: $TENANT_ID"
echo "AI ID: $AI_ID"
```

### مرحله 3: تست توکن

```bash
curl -X GET "$BASE_URL/api/api/v1/test-token" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
```

### مرحله 4: ایجاد ماموریت با استفاده از Metadata

```bash
curl -X POST "$BASE_URL/api/api/v1/missions" \
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

**نسخه:** 1.0.0  
**تاریخ:** 2025-12-26

