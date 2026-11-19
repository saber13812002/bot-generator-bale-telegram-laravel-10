<?php

namespace App\Services;

use GuzzleHttp;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OneApiTranslationService
{
    private static array $providers = ["google", "microsoft", "targoman", "faraazin"];
    private static int $currentProviderIndex = 0;

    /**
     * Circuit breaker: اگر همه providerها شکست خوردند، تا انقضای این پنجره
     * کل سرویس one-api را می‌شکنیم (fallback مستقیم به LLM/متن اصلی).
     *
     * دلیل: وقتی API یک‌سر 404 می‌دهد (مثل خطای «Bad Request» 404 one-api.ir)،
     * بدون این مکانیزم هر segment به 4 provider × timeout‌های مختلف ضربه می‌زند
     * و pipeline RSS را چند دقیقه طول می‌کشد.
     */
    private const CIRCUIT_BREAKER_TTL = 120;

    /**
     * @throws \Exception|GuzzleHttp\Exception\GuzzleException
     */
    public static function call($text, $language)
    {
        // Circuit breaker — اگر پنجره‌ی شکست هنوز فعال است، سریع شکست می‌دهیم
        if (static::circuitOpen()) {
            throw new \Exception('All translation providers failed (circuit breaker open).');
        }

        $api_key = env("ONE_API_API_TOKEN");
        if (!$api_key) {
            // بدون توکن هیچ‌وقت جواب نخواهیم گرفت — بلافاصله شکست تا لود fallback اضافه نشود
            throw new \Exception('ONE_API_API_TOKEN is not configured.');
        }

        $client = new Client([
            'timeout' => 15,
            'connect_timeout' => 5,
        ]);

        $providersCount = count(self::$providers);
        $consecutiveHardFailures = 0;

        for ($i = 0; $i < $providersCount; $i++) {
            $provider = self::$providers[self::$currentProviderIndex];
            $uri = 'https://one-api.ir/translate/?token=' . $api_key . '&action=' . $provider . '&lang=' . $language . '&q=' . urlencode($text);

            try {
                $response = $client->get($uri);
                if ($response->getStatusCode() == 200) {
                    static::resetCircuitBreaker();
                    self::$currentProviderIndex = (self::$currentProviderIndex + 1) % $providersCount;
                    return json_decode($response->getBody(), true);
                }

                // status != 200 (مثلاً 404/401/403)
                $body = (string) $response->getBody();
                Log::warning('one_api:' . $provider . ' returned HTTP ' . $response->getStatusCode());

                // 4xx به‌جز 408/429 یعنی کل API خراب است (404 صفحه‌ی خطا، 401 توکن نامعتبر، 403 ...)
                // ادامه دادن به provider بعدی بی‌فایده است — همه از همان endpoint استفاده می‌کنند.
                $status = $response->getStatusCode();
                if ($status >= 400 && $status < 500 && $status !== 408 && $status !== 429) {
                    $consecutiveHardFailures = $providersCount; // تمام providerها را dead بشمار
                    break;
                }
            } catch (\Exception $e) {
                // timeout/خطای شبکه: هر provider را یک بار امتحان کرده‌ایم، خطا را ثبت می‌کنیم
                $message = $e->getMessage();
                if (str_contains($message, 'Operation timed out') || str_contains($message, 'Connection timed out')) {
                    $consecutiveHardFailures++;
                }
                Log::warning($provider . ':' . $message);
            }

            // Move to the next provider in the list
            self::$currentProviderIndex = (self::$currentProviderIndex + 1) % $providersCount;
        }

        // همه providerها شکست خوردند → اگر شکست‌های سخت (4xx/timeout) همه را پوشاند، breaker باز می‌شود
        if ($consecutiveHardFailures >= $providersCount) {
            static::tripCircuitBreaker();
        }

        throw new \Exception('All translation providers failed.');
    }

    /**
     * آیا circuit breaker باز است (یعنی one-api به‌تازگی کامل شکست خورده)؟
     */
    private static function circuitOpen(): bool
    {
        try {
            return Cache::get('one_api_circuit_open') === true;
        } catch (\Throwable $e) {
            // اگر خودِ cache شکست (مثل disk quota)، breaker را باز فرض نمی‌کنیم
            return false;
        }
    }

    private static function tripCircuitBreaker(): void
    {
        try {
            Cache::put('one_api_circuit_open', true, self::CIRCUIT_BREAKER_TTL);
        } catch (\Throwable $e) {
            // شکست cache مهم نیست (مثلاً disk quota)
        }
    }

    private static function resetCircuitBreaker(): void
    {
        try {
            Cache::forget('one_api_circuit_open');
        } catch (\Throwable $e) {
            // شکست cache مهم نیست
        }
    }
}
