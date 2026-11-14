# WS-J — Harden Existing AI Consumers

تقویت consumerهای موجود AI (ترجمه RSS، گزارش فعالیت قرآن، متن انگیزشی کانال،
Growth variants) بر روی یک انتخاب مشترک و سخت‌گیرانه‌ی provider، بدون اینکه
flow عادی bot در هیچ سناریویی آسیب ببیند (اصل بنیادین مأموریت).

## ۱. J1 — لایه‌ی L5 دروازه: strict (hook مستند WS-J)

مستندات WS-E در `docs/AI_FEATURE_FLAGS.md` §۵ و docblock `AiFeatureGate`
قول داده بود: «بدون provider row در DB → pass (MVP؛ در WS-J سخت‌گیرتر می‌شود)».

حالا:

| شرط | پیش از WS-J | پس از WS-J |
|---|---|---|
| provider فعال + testConnection موفق | allow | allow |
| provider فعال + testConnection ناموفق | deny `provider_unhealthy` | deny `provider_unhealthy` |
| **بدون provider فعال در DB** | pass (safe-by-default) | **deny `provider_missing`** (strict) |
| exception در health check (مثلاً نبود جدول) | pass | pass (never-throw) |

- کلید: `config('ai.require_active_provider')` ← env `AI_REQUIRE_ACTIVE_PROVIDER`،
  پیش‌فرض **true** (production سخت‌گیر است).
- در `phpunit.xml` مقدار `false` است تا ~۱۵۰ تست موجود که gate را بدون
  provider row می‌زنند (RefreshDatabase) دست‌نخورده بمانند؛ strict جدا
  test شده (TDD).
- `AiFeatureGate::providerHealthy()` حالا بدون provider → `false` فقط در
  strict mode؛ دلیل reject در `check()` با شمارش provider فعال بین
  `provider_missing` و `provider_unhealthy` تفکیک می‌شود.

## ۲. J2 — انتخاب مشترک provider سالم (بدون HTTP اضافی)

قبل از WS-J هر consumer الگوی خود را داشت:

- `LlmTranslationService::selectHealthyProvider()` — failed را رد می‌کرد (خوب)
- `ActivityReportSummaryService::analyze()` — `active()->first() ?? ensureDefaultProvider()` **بدون** چک سلامت
- `ChannelMotivationSend::generateText()` — همان الگوی بدون چک سلامت

یعنی وقتی `ai:health-check` provider را `failed` ثبت می‌کرد، دو consumer بالا
باز هم با timeout کامل به همان provider خراب زدن می‌زدند.

حالا همه سه (و دروازه‌ی L5) از یک helper مشترک استفاده می‌کنند:

```php
AiProviderService::healthyProvider(): ?AiProvider
```

- active providerها به ترتیب id؛ رد کردن هرکدام با `last_test_status='failed'`.
- «null» یعنی هنوز تست نشده → قابل امتحان.
- جدول خالی → `ensureDefaultProvider()`.
- هیچ گزینه‌ی سالمی نباشد → `null` → consumer **بدون هیچ request HTTP**
  fallback می‌کند (اصل: خرابی AI نباید flow عادی را کند/کُند کند).

Consumerها:

| Consumer | رفتار جدید وقتی provider failed است |
|---|---|
| `LlmTranslationService::translate` | null → driver به متن اصلی/درایور بعدی (همان قبل، اما حالا از helper مشترک) |
| `ActivityReportSummaryService::analyze` | null → گزارش با fallback «در دسترس نیست» |
| `ChannelMotivationSend::generateText` | null → llmFailed++ → run FAILURE + notifyAdmins، بدون پست |

## ۳. J3 — Growth LLM: fallback به central LiteLLM

`GrowthLlmProvider` (تولید variants سوال توسط `GrowthCompanionServiceImpl::generateAiVariants`)
کلید جدا داشت (`GROWTH_LLM_API_KEY`) و بدون کلید → `NullGrowthLlmProvider`
(variants خالی → seeder فقط).

حالا زنجیره‌ی binding در `AppServiceProvider`:

1. `growth.llm.api_key` (کلید اختصاصی، اگر ست باشد اولویت دارد)
2. **central LiteLLM**: `ai.default_api_key` + `ai.default_base_url` +
   مدل `ai.growth_companion_model ?? ai.default_model`
3. هیچ‌کدام نبود → `NullGrowthLlmProvider`

همان `HttpGrowthLlmProvider` (Guzzle + never-throw) استفاده می‌شود؛ فقط
credentials از config مرکزی می‌آیند. در `.env` فعلی `AI_DEFAULT_API_KEY`
ست است → در production growth variants حالا بدون کلید جدا از همان LiteLLM
مرکزی تولید می‌شوند (تست `test_growth_binding_falls_back_to_central_litellm`).

## ۴. فایل‌ها

| فایل | تغییر |
|---|---|
| `config/ai.php` | + `require_active_provider` (env `AI_REQUIRE_ACTIVE_PROVIDER`, default true) |
| `phpunit.xml` | + env `AI_REQUIRE_ACTIVE_PROVIDER=false` (تست‌ها) |
| `app/Services/Ai/AiFeatureGate.php` | L5 strict + docblock + reason `provider_missing` |
| `app/Services/AiProviderService.php` | + `healthyProvider()` (static, shared) |
| `app/Services/LlmTranslationService.php` | `selectHealthyProvider` → delegate به helper مشترک |
| `app/Services/ActivityReportSummaryService.php` | `analyze()` از helper مشترک + null-check |
| `app/Console/Commands/ChannelMotivationSend.php` | `generateText()` از helper مشترک + null-check |
| `app/Providers/AppServiceProvider.php` | binding GrowthLlmProvider با fallback central LiteLLM |

## ۵. تست‌ها (TDD)

RED → GREEN:

| تست | فایل |
|---|---|
| strict: بدون provider → deny `provider_missing` | `tests/Feature/AiConsumerHardeningTest.php` |
| strict: provider سالم → allow `ok` | همان |
| non-strict: بدون provider → pass | همان |
| `healthyProvider()` failed را رد می‌کند | همان |
| `healthyProvider()` جدول خالی → ensureDefault | همان |
| binding growth → central LiteLLM (HttpGrowthLlmProvider) | همان |
| binding growth بدون هیچ کلید → Null | همان |
| ActivityReport: provider failed → fallback بدون HTTP | `tests/Feature/ActivityReportFallbackTest.php` |
| ChannelMotivation: provider failed → FAILURE بدون chat/completions | `tests/Feature/ChannelMotivationSendTest.php` |
| Translation: provider failed → متن اصلی بدون HTTP | `tests/Feature/TranslationDriverTest.php` |

سبک تست: provider موجودِ setUp را با `AiProvider::query()->update([...failed])`
failed می‌کنیم (نه provider دوم — helper provider اولِ healthy را برمی‌گزید).
`Http::fake` + `Http::assertNothingSent` / `assertNotSent('/v1/chat/completions')`
اثبات می‌کند request اضافی نمی‌رود.

## ۶. شواهد

- WS-J targeted: **22/22** (64 assertions) — RED اول: 7 failure/error درست
  (`healthyProvider` undefined ×2، strict gate ×1، growth binding ×1،
  no-HTTP ×3).
- Regression `--filter Ai`: **191/191** (1011 assertions) — 181 قبل + 10 جدید.
- Regression `--filter "BookPixel|Book"`: **45/45** (228).
- Regression `--filter Growth`: **43/43** (189) — binding J3 بدون ripple
  (تست‌ها GrowthLlmProvider را `instance` می‌کنند؛ seeder path untouched).
- `php -l` برای همه فایل‌های لمس‌شده: clean.

## ۷. Notes عملیاتی

- strict mode در production از همان deploy بعدی فعال است (default true) —
  اگر `ai_providers` در production خالی باشد، همه capabilityها با
  `provider_missing` رد می‌شوند تا provider default بسازند
  (`AiProviderService::ensureDefaultProvider()` در consumerها، یا Nova seeder).
  برای rollback فوری: `AI_REQUIRE_ACTIVE_PROVIDER=false` + `config:clear`.
- `provider_missing` در `details` دروازه logged می‌شود؛ monitoring روی
  reasonهای deny در logها کافی است.
