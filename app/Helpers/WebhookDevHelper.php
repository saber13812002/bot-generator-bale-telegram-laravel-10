<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Helper برای آسان‌تر کردن توسعه و تست webhook‌ها
 */
class WebhookDevHelper
{
    /**
     * شبیه‌سازی webhook بدون نیاز به curl
     * برای استفاده در تست‌ها یا توسعه
     *
     * @param string $endpoint مسیر endpoint (مثلاً '/api/webhook-bot-mother')
     * @param array $updateData داده‌های update
     * @param array $queryParams پارامترهای query string
     * @return Response پاسخ درخواست
     */
    public static function simulateWebhook(
        string $endpoint,
        array $updateData,
        array $queryParams = []
    ): Response {
        $url = url($endpoint);
        
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $request = Request::create($url, 'POST', [], [], [], [], json_encode($updateData));
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Accept', 'application/json');

        $response = app()->handle($request);

        // لاگ کردن در حالت development
        if (config('app.env') === 'local') {
            self::logWebhookRequest($request, $response);
        }

        return $response;
    }

    /**
     * ساختار update برای تست
     *
     * @param string $text متن پیام
     * @param string $type نوع پیام‌رسان ('bale' یا 'telegram')
     * @return array ساختار update
     */
    public static function createTestUpdate(string $text, string $type = 'bale'): array
    {
        if ($type === 'bale') {
            return WebhookMockHelper::mockBaleUpdate($text);
        }

        return WebhookMockHelper::mockTelegramUpdate($text);
    }

    /**
     * ساختار callback query برای تست
     *
     * @param string $callbackData داده callback
     * @return array ساختار callback query
     */
    public static function createCallbackQueryUpdate(string $callbackData): array
    {
        return WebhookMockHelper::mockCallbackQuery($callbackData);
    }

    /**
     * لاگ کردن درخواست webhook در حالت development
     *
     * @param Request $request درخواست
     * @param Response $response پاسخ
     * @return void
     */
    public static function logWebhookRequest(Request $request, Response $response): void
    {
        if (config('app.env') === 'local') {
            $logData = [
                'endpoint' => $request->fullUrl(),
                'method' => $request->method(),
                'query_params' => $request->query(),
                'update' => $request->all(),
                'response_status' => $response->getStatusCode(),
                'response_content' => $response->getContent(),
                'timestamp' => now()->toDateTimeString()
            ];

            Log::info('Webhook Request (Development)', $logData);

            // نمایش در console در حالت local
            if (php_sapi_name() === 'cli') {
                echo "\n";
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                echo "Webhook Request Log\n";
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                echo "Endpoint: " . $logData['endpoint'] . "\n";
                echo "Method: " . $logData['method'] . "\n";
                echo "Status: " . $logData['response_status'] . "\n";
                echo "Update: " . json_encode($logData['update'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                echo "Response: " . $logData['response_content'] . "\n";
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                echo "\n";
            }
        }
    }

    /**
     * تست سریع یک webhook با داده‌های mock
     *
     * @param string $endpoint مسیر endpoint
     * @param string $text متن پیام
     * @param array $queryParams پارامترهای query
     * @return Response پاسخ
     */
    public static function quickTest(
        string $endpoint,
        string $text,
        array $queryParams = []
    ): Response {
        $update = self::createTestUpdate($text);
        return self::simulateWebhook($endpoint, $update, $queryParams);
    }

    /**
     * تست callback query
     *
     * @param string $endpoint مسیر endpoint
     * @param string $callbackData داده callback
     * @param array $queryParams پارامترهای query
     * @return Response پاسخ
     */
    public static function testCallbackQuery(
        string $endpoint,
        string $callbackData,
        array $queryParams = []
    ): Response {
        $update = self::createCallbackQueryUpdate($callbackData);
        return self::simulateWebhook($endpoint, $update, $queryParams);
    }

    /**
     * نمایش اطلاعات webhook برای debugging
     *
     * @param Request $request درخواست
     * @return void
     */
    public static function debugWebhook(Request $request): void
    {
        if (config('app.env') === 'local') {
            $debugInfo = [
                'URL' => $request->fullUrl(),
                'Method' => $request->method(),
                'Query Params' => $request->query(),
                'Update Data' => $request->all(),
                'Headers' => $request->headers->all()
            ];

            Log::debug('Webhook Debug Info', $debugInfo);

            if (php_sapi_name() === 'cli') {
                echo "\n";
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                echo "Webhook Debug Information\n";
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                foreach ($debugInfo as $key => $value) {
                    echo $key . ": " . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                }
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                echo "\n";
            }
        }
    }

    /**
     * بررسی صحت ساختار update
     *
     * @param array $update داده‌های update
     * @return bool true اگر ساختار صحیح باشد
     */
    public static function validateUpdateStructure(array $update): bool
    {
        // بررسی وجود update_id
        if (!isset($update['update_id'])) {
            return false;
        }

        // بررسی وجود message یا callback_query
        if (!isset($update['message']) && !isset($update['callback_query'])) {
            return false;
        }

        // اگر message وجود دارد، باید text یا callback_query.data وجود داشته باشد
        if (isset($update['message']) && !isset($update['message']['text'])) {
            return false;
        }

        // اگر callback_query وجود دارد، باید data وجود داشته باشد
        if (isset($update['callback_query']) && !isset($update['callback_query']['data'])) {
            return false;
        }

        return true;
    }
}


