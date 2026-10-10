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
            $provider = AiProvider::active()->orderBy('id')->first()
                ?? AiProviderService::ensureDefaultProvider();

            $service = new AiProviderService($provider);
            $result  = $service->chat(
                self::buildPrompt($text, $target),
                self::MAX_TOKENS,
                0.2
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
