<?php

namespace App\Services;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ترجمه با LLM محلی (سرور ISMC) — جایگزین one-api.ir
 *
 * از همان AiProvider استفاده می‌کند که ai:health-check هر ساعت آن را تست می‌کند.
 * در صورت هرگونه خطا null برمی‌گرداند تا driver بتواند به متن اصلی برگردد.
 */
class LlmTranslationService
{
    /**
     * حداکثر توکن خروجی برای یک segment (متن طولانی‌تر → توکن بیشتر)
     */
    protected const MAX_TOKENS = 1024;

    /**
     * timeout مخصوص ترجمه (ثانیه) — کوتاه‌تر از ۳۰ ثانیه‌ی عمومی؛
     * ترجمه‌ی هر segment نباید pipeline را چند دقیقه رها کند.
     */
    protected const CHAT_TIMEOUT_SECONDS = 20;

    /**
     * ترجمه متن به زبان مقصد (پیش‌فرض فارسی)
     *
     * @param  string  $text        متن مبدأ
     * @param  string  $target      زبان مقصد (پیش‌فرض fa)
     * @return string|null          متن ترجمه‌شده یا null در صورت شکست
     */
    public static function translate(string $text, string $target = 'fa'): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        try {
            $provider = self::selectHealthyProvider();
            if (!$provider) {
                // تمام providerهای فعال در health-checkِ ۱ ساعت اخیر «failed» ثبت شده‌اند
                // (یا جدول خالی است) — کوشش کردن فقط timeout ۳۰ ثانیه‌ای هر segment را
                // اضافه می‌کند؛ فست‌تر روی متن اصلی (یا provider بعدی در driver) سقوط می‌کنیم.
                Log::warning('[LlmTranslation] No healthy AI provider available; skipping LLM');

                return null;
            }

            $service = new AiProviderService($provider);
            $result  = $service->chat(
                self::buildPrompt($text, $target),
                self::MAX_TOKENS,
                0.2,
                self::CHAT_TIMEOUT_SECONDS
            );

            if (!($result['success'] ?? false)) {
                Log::warning('[LlmTranslation] LLM chat failed', [
                    'provider_id' => $provider->id,
                    'error'       => $result['response'] ?? '',
                ]);

                return null;
            }

            $translated = self::cleanResponse($result['response'] ?? '');

            if ($translated === '') {
                Log::warning('[LlmTranslation] Empty translation response', [
                    'provider_id' => $provider->id,
                ]);

                return null;
            }

            return $translated;
        } catch (Throwable $e) {
            Log::error('[LlmTranslation] Translation failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * انتخاب provider: فعال‌ترین‌ها را می‌گیرد، اما providerهایی که health-check
     * (ai:health-check) آن‌ها را «failed» ثبت کرده‌اند را رد می‌کند — مگر اینکه
     * هیچ گزینه‌ی سالمی نباشد (آن وقت null برمی‌گردد و ترجمه LLM اصلاً انجام نمی‌شود).
     *
     * @return \App\Models\AiProvider|null
     */
    private static function selectHealthyProvider(): ?AiProvider
    {
        $providers = AiProvider::active()->orderBy('id')->get();

        // جدول خالی است → provider پیش‌فرض از config ساخته می‌شود
        if ($providers->isEmpty()) {
            return AiProviderService::ensureDefaultProvider();
        }

        // «سالم» = statusِ آخرین تست «failed» نباشد (null یعنی هنوز تست نشده → قابل امتحان)
        $healthy = $providers->first(fn ($p) => $p->last_test_status !== 'failed');

        return $healthy;
    }

    /**
     * ساخت پرامپت ترجمه — فقط خود ترجمه، بدون نقل‌قول و پیش‌نویس
     */
    public static function buildPrompt(string $text, string $target = 'fa'): string
    {
        $languageName = match ($target) {
            'fa'  => 'فارسی (پارسی)',
            'en'  => 'English',
            'ar'  => 'العربية',
            'tr'  => 'Türkçe',
            'ur'  => 'اردو',
            'zh'  => '中文',
            'ru'  => 'Русский',
            'de'  => 'Deutsch',
            'fr'  => 'Français',
            'es'  => 'Español',
            'pt'  => 'Português',
            default => $target,
        };

        return "تو یک مترجم حرفه‌ای هستی. متن زیر را به {$languageName} ترجمه کن.\n"
            . 'قواعد:\n'
            . '1. فقط متن ترجمه‌شده را بنویس؛ هیچ توضیح، پیش‌نویس، شماره یا نقل‌قول اضافه نکن.\n'
            . '2. لحن و ساختار جملات را طبیعی و روان نگه دار.\n'
            . "3. اصطلاحات فنی و نام‌های خاص را طبق عرف {$languageName} بنویس.\n\n"
            . "متن:\n" . $text;
    }

    /**
     * پاک‌سازی پاسخ LLM: حذف نقل‌قول‌های دور، بولت‌ها و پیش‌نویس‌های رایج
     */
    public static function cleanResponse(string $response): string
    {
        $response = trim($response);
        if ($response === '') {
            return '';
        }

        // حذف پیش‌نویس‌های رایج مثل «ترجمه:» یا «Translation:»
        $response = preg_replace(
            '/^(ترجمه|Translation|ترجمه‌شده|متن ترجمه‌شده)\s*:\s*/iu',
            '',
            $response
        );

        // حذف «" ... "» یا «« ... »» یا ‹...› دور کل متن
        $response = preg_replace('/^\s*["«„„»\']\s*(.*?)\s*["«»\']\s*$/us', '$1', $response);

        // حذف بولت آغازین
        $response = preg_replace('/^\s*[-*•]\s+/u', '', $response);

        return trim($response);
    }
}
