<?php

namespace App\Services\Ai;

/**
 * پاک‌سازی خطاهای LiteLLM / OpenAI-compatible
 * ------------------------------------------------
 * LiteLLM در متن خطا گاهی «اثر انگشت» کلید را چاپ می‌کند:
 *   - `Key Hash (Token): sk-1234abcd...`
 *   - `Received API Key: sk-1234...`
 *   - `Authorization: Bearer sk-1234...`
 * قبل از لاگ‌نویسی/ارسال به مدیر، این الگوها را ماسک می‌کنیم
 * تا هیچ بخشی از کلید در لاگ‌ها باقی نماند.
 */
class AiErrorRedactor
{
    /**
     * ماسک‌کردن هر متن (بدیِ خطا، پیام، لاگ) از نشت کلید/اثر انگشت کلید
     */
    public static function redact(string $text): string
    {
        // ۱) کلیدهای sk-... را به‌طور کامل ماسک کن
        $text = preg_replace('/\bsk-[A-Za-z0-9\-_\.]{6,}/', '***REDACTED***', $text);

        // ۲) الگوهای key: value — مقدار سمت راست ماسک می‌شود
        $patterns = [
            // Key Hash (Token): xxx  |  Key Hash: xxx  |  Token Hash: xxx
            '/(key\s*hash\s*\(\s*token\s*\)|key\s*hash|token\s*hash|received\s+api\s+key|api\s+key)\s*[:=]\s*[A-Za-z0-9\-_\.]+/iu',
            // Authorization: Bearer xxx
            '/(authorization)\s*[:=]\s*bearer\s+[A-Za-z0-9\-_\.]+/iu',
            // Bearer xxx
            '/(bearer)\s+[A-Za-z0-9]{8,}/iu',
            // master key
            '/(master[_\s-]?key)\s*[:=]\s*[A-Za-z0-9\-_\.]+/iu',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '$1: ***REDACTED***', $text);
        }

        return $text;
    }

    /**
     * ماسک‌کردن کل یک آرایِ پاسخ (json_decode شده یا خام)
     */
    public static function redactValue($value)
    {
        if (is_string($value)) {
            return self::redact($value);
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::redactValue($v);
            }
        }

        return $value;
    }
}
