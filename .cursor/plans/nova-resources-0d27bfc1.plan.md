---
name: ساخت Nova Resources برای مدل‌های جدید
overview: ""
todos: []
isProject: false
---

# ساخت Nova Resources برای مدل‌های جدید

## بررسی وضعیت فعلی

از بررسی کدها مشخص شد که مدل‌های زیر Nova Resource ندارند:

- Mission
- Content
- Task
- Personnel
- Tenant
- Prompt
- Training
- MissionPersonnel
- PersonnelMessageQueue

## فایل‌های مورد نیاز

### 1. Mission Resource

**مسیر:** `app/Nova/Mission.php`

**فیلدها:**

- ID
- BelongsTo: Tenant
- Text: title
- Textarea: description
- BelongsTo: Prompt (nullable)
- BelongsTo: Content (nullable)
- Number: points
- Number: duration (nullable)
- Number: max_personnel
- Number: current_personnel_count
- Select: status (active, inactive)
- DateTime: created_at, updated_at

### 2. Content Resource

**مسیر:** `app/Nova/Content.php`

**فیلدها:**

- ID
- BelongsTo: Tenant
- Text: title
- Select: content_type (text, video, image, audio, pdf)
- Text: content_url
- Textarea: description (nullable)
- Number: sort_order
- DateTime: created_at, updated_at

### 3. Task Resource

**مسیر:** `app/Nova/Task.php`

**فیلدها:**

- ID
- Text: task_name
- BelongsTo: Personnel (assigned_user_id)
- Select: task_status (reserved, in_progress, pending_approval, approved, rejected)
- Number: points
- DateTime: task_time, assigned_time, reserved_time (nullable)
- Textarea: final_link (nullable)
- Textarea: rejection_reason (nullable)
- Number: approved_by_chat_id (nullable)
- DateTime: approved_at, rejected_at (nullable)
- DateTime: created_at, updated_at

### 4. Personnel Resource

**مسیر:** `app/Nova/Personnel.php`

**فیلدها:**

- ID
- Text: first_name
- Text: last_name
- Text: national_code (unique)
- Text: phone_number
- BelongsTo: Tenant
- Select: rank (سرباز صفر, سرباز یک, سرباز دو, سرباز سه)
- DateTime: created_at, updated_at

### 5. Tenant Resource

**مسیر:** `app/Nova/Tenant.php`

**فیلدها:**

- ID
- Text: tenant_name
- DateTime: created_at, updated_at

### 6. Prompt Resource

**مسیر:** `app/Nova/Prompt.php`

**فیلدها:**

- ID
- Textarea: content
- BelongsTo: Task (nullable)
- BelongsTo: Tenant (nullable)
- BelongsTo: Mission (nullable)
- DateTime: created_at, updated_at

### 7. Training Resource

**مسیر:** `app/Nova/Training.php`

**فیلدها:**

- ID
- BelongsTo: Task
- Textarea: training_content (nullable)
- Text: training_url (nullable)
- DateTime: created_at, updated_at

### 8. MissionPersonnel Resource

**مسیر:** `app/Nova/MissionPersonnel.php`

**فیلدها:**

- ID
- BelongsTo: Mission
- BelongsTo: Personnel
- Select: status (reserved, in_progress, pending_approval, approved, rejected, cancelled)
- Textarea: result_link (nullable)
- Number: approval_message_id (nullable)
- Textarea: rejection_reason (nullable)
- Number: approved_by_chat_id (nullable)
- DateTime: approved_at, rejected_at, started_at, completed_at (nullable)
- DateTime: created_at, updated_at

### 9. PersonnelMessageQueue Resource

**مسیر:** `app/Nova/PersonnelMessageQueue.php`

**فیلدها:**

- ID
- BelongsTo: Personnel
- Textarea: message_content (nullable)
- Select: status (queue, sent, error)
- Text: error_message (nullable)
- DateTime: created_at, updated_at

## الگوهای استفاده شده

- استفاده از `BelongsTo` برای relationships
- استفاده از `Select` برای enum fields
- استفاده از `Textarea` برای فیلدهای متنی طولانی
- استفاده از `Number` برای فیلدهای عددی
- استفاده از `DateTime` برای فیلدهای تاریخ و زمان
- استفاده از `sortable()` برای فیلدهای قابل مرتب‌سازی
- تعریف `$search` برای فیلدهای قابل جستجو
- تعریف `$title` برای نمایش در لیست

## نکات مهم

1. همه Resources باید از `App\Nova\Resource` extend کنند
2. همه Resources باید `$model` را تعریف کنند
3. فیلدهای nullable باید با `->nullable()` مشخص شوند
4. Relationships باید با `BelongsTo::make()` تعریف شوند
5. Enum fields باید با `Select::make()` و `->options()` تعریف شوند
6. فیلدهای مهم برای جستجو باید در `$search` اضافه شوند