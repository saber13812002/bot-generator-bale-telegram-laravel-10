# WS-I — RAG & Embedding readiness

## هدف
آماده‌سازی لوله‌ی embedding بر پایه‌ی LiteLLM مشترک، بدون وابستگی به vector-DB؛
به‌طوری‌که RAG واقعی برای ربات‌های دانشی (قرآن/حدیث) بتواند بعداً مستقیم از همین
لایه استفاده کند. اصل بنیادین: **شکست AI هرگز بیزینس ربات را نمی‌شکند** —
`search()` هرگز نمی‌اندازد و خطا در اکزیکیوتور فقط error_code مناسب برای
retry/backoff صف AI است.

## لوله‌ی کار

```
embedSource()                      execute()                          search()
gate «rag»                          EmbeddingAdapter->embed()          EmbeddingAdapter->embed(query)
  ↓ (check)                         ↓                                  ↓ cosine in PHP
AiJobDispatcher::dispatch           upsert ai_embeddings               [] on any failure
('embedding', idempotent)            on (source_type, source_id)
  ↓                                  result_reference =
ProcessAiJob → executeEmbedding      'ai_embedding:{id}'
```

## فایل‌ها

| فایل | نقش |
|---|---|
| `database/migrations/2026_11_14_170000_create_ai_embeddings_table.php` | جدول `ai_embeddings` — unique `[source_type, source_id]` (upsert-safe)، index `[bot_id, source_type]` |
| `app/Models/AiEmbedding.php` | مدل؛ cast `vector => array` |
| `app/Services/Ai/RagEmbeddingService.php` | `embedSource()` (gate + dispatch idempotent)، `execute()` (adapter + upsert)، `search()` (cosine، never-throw)، `cosine()` |
| `app/Jobs/ProcessAiJob.php` | بازوی `'embedding' => executeEmbedding()` |
| `tests/Feature/AiRagEmbeddingTest.php` | 8 تست (RED→GREEN) |

## قراردادها

- **درِ feature**: `AiFeatureGate::FEATURE_RAG` → `ai_rag_enabled` (L1) +
  kill-switch + flag ربات + test-group + پنجره‌ی deferral + health provider.
- **dispatch**: `AiJobDispatcher::dispatch('embedding', payload, options)` —
  idempotent روی `idempotency_key`؛ تکرار embed همان job قبلی را برمی‌گرداند
  (`created=false`) و job جدید نسازد.
- **خطاها** (طبق `EmbeddingAdapter`): 401/403 → `auth_failed`، 404 →
  `model_not_found`، 429 → `rate_limited`، ≥500 → `provider_error`؛
  همه از طریق `error_code` به `handleFailure` صف AI می‌روند (retry/backoff،
  dead-letter بعد از `ai.queue.max_attempts`).
- **مدل پیش‌فرض**: `config('ai.embedding_model')` → settings provider →
  `text-embedding-3-small`. `dimensions` در هر پاسخ گزارش می‌شود و روی ردیف
  ذخیره می‌شود تا از تغییر ناگهانی ساختار در میانه راه جلوگیری شود
  (تصمیم vector-DB بعد از تأیید ابعاد واقعی با `php artisan ai:discover-models`).

## جست‌وجو (بدون vector-DB)

`search($query, $sourceType, ?$botId, limit=5)`:
1. embed پرسش؛ اگر provider خراب/خطا → `[]` (هرگز exception).
2. تا ۵۰۰ ردیف active هم‌نوع (فیلر اختیاری bot) → cosine similarity در PHP.
3. sort نزولی + `take($limit)` → `[id, source_id, content, score]`.

محدودیت شناخته‌شده: cosine روی ≤۵۰۰ بردار در PHP برای MVP ربات‌های دانشی
کافی است؛ اگر حجم sources بزرگ شد، همان جدول `ai_embeddings` به‌عنوان
source-of-truth برای مهاجرت به vector-DB باقی می‌ماند.

## تعویقی‌ها (WS-I2)

- مصرف‌کننده‌ی RAG واقعی در ربات‌های قرآن/حدیث (پاسخ از روی صفحات embed شده).
- انتخاب/مهاجرت به vector-DB پس از تأیید ابعاد مدل واقعی.
- embed خودکار هنگام OCR/ASR موفق Book Pixel (hook بعدی روی همان لایه).

## شواهد

- `tests/Feature/AiRagEmbeddingTest`: **8/8 سبز، 39 assertion**
  (dispatch + idempotency، executor success/persist، 500→provider_error RETRYING،
  401→auth_failed، cosine ranking، provider-down→[]، gate closed→no job).
- رگرسیون: `--filter Ai` **181/181 (989)**، `--filter "BookPixel|Book"` **45/45 (228)** — همه سبز.
