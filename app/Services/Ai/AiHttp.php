<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * ترانسپورت مشترک سرویس‌دهنده‌های AI (LiteLLM / OpenAI-compatible)
 * ---------------------------------------------------------------
 * - اعمال تنظیمات HTTP مرکزی از config/ai.php (Strict TLS، timeout، proxy)
 * - تزریق توکن Bearer
 * - ماسک‌کردن خطاها با AiErrorRedactor قبل از برگشت به فراخواننده
 */
class AiHttp
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct(?AiProvider $provider = null)
    {
        $this->baseUrl = $provider ? $provider->base_url : config('ai.default_base_url');
        $this->apiKey  = $provider ? $provider->api_key  : config('ai.default_api_key');
    }

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * PendingRequest آماده با تنظیمات مرکزی + توکن
     */
    public function pending(?int $timeout = null): PendingRequest
    {
        $request = Http::withOptions(config('ai.http_options'))
            ->withToken($this->apiKey);

        if ($timeout) {
            $request = $request->timeout($timeout);
        }

        return $request;
    }

    /**
     * ساختن پیام خطای یکدست و پاک‌شده (بدون نشت کلید)
     */
    public static function errorMessage(int $status, string $body): string
    {
        return AiErrorRedactor::redact(
            'HTTP ' . $status . ': ' . mb_substr($body, 0, 500)
        );
    }

    public static function exceptionMessage(string $message): string
    {
        return AiErrorRedactor::redact($message);
    }
}
