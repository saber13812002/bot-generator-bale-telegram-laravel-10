<?php

namespace App\Services\BookPixel;

use App\Models\AiJob;
use App\Models\BookPageScan;
use App\Models\Bot;
use App\Services\Ai\AiErrorRedactor;
use App\Services\Ai\AiFeatureGate;
use App\Services\Ai\AiJobDispatcher;
use App\Services\Ai\VisionAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WS-F — OCR MVP در Book Pixel.
 *
 * جریان:
 *  1. triggerForScan() — بعد از ثبت اسکن صفحه: بررسی AiFeatureGate +
 *     dispatch idempotent به ai_jobs/ProcessAiJob (با سیاست زمان تهران).
 *  2. execute() — توسط ProcessAiJob: getFile پلتفرم → دانلود فایل →
 *     VisionAdapter (data-URI) → ذخیره ocr_text روی book_page_scans.
 *
 * اصول:
 *  - شکست OCR هرگز بیزینس را نمی‌شکند (trigger هرگز throw نمی‌کند).
 *  - خروجی جعلی نیست: اگر Vision متن خالی بدهد، ocr_text null می‌ماند
 *    و job با empty_result retry می‌شود.
 *  - error_code هر تلاش ناموفق روی ocr_error ذخیره می‌شود (قابل رصد).
 */
class BookPixelOcrService
{
    /**
     * Trigger OCR برای یک اسکن صفحه (از webhook Bot).
     *
     * @return array{dispatched: bool, job: AiJob|null, reason: ?string}
     *   dispatched=true یعنی job ساخته شد (queued یا deferred).
     */
    public function triggerForScan(BookPageScan $scan, string $origin, bool $isPro = false): array
    {
        try {
            $gate = (new AiFeatureGate())->check(
                AiFeatureGate::FEATURE_OCR,
                $scan->bot_id,
                $scan->user_id,
                $scan->user_id,
                $isPro
            );

            if (! $gate['allowed']) {
                return ['dispatched' => false, 'job' => null, 'reason' => $gate['reason']];
            }

            $result = AiJobDispatcher::dispatch(
                'ocr',
                [
                    'scan_id' => $scan->id,
                    'file_id' => $scan->file_id,
                    'origin'  => $origin,
                    'bot_id'  => $scan->bot_id,
                ],
                [
                    'bot_id' => $scan->bot_id,
                    'user_id' => $scan->user_id,
                    'is_pro'  => $isPro,
                    'model'   => config('ai.vision_model'),
                ]
            );

            return [
                'dispatched' => true,
                'job'        => $result['job'],
                'reason'     => $result['deferred'] ? 'deferred' : 'queued',
            ];
        } catch (Throwable $e) {
            // OCR هرگز نباید جریان Bot را بشکند
            Log::warning('[BookPixelOcr] triggerForScan failed', [
                'scan_id' => $scan->id,
                'error'   => AiErrorRedactor::redact($e->getMessage()),
            ]);

            return ['dispatched' => false, 'job' => null, 'reason' => 'error'];
        }
    }

    /**
     * اجرای OCR (توسط ProcessAiJob).
     *
     * @return array{success: bool, error_code: ?string, message: ?string,
     *               result_reference: ?string, usage: ?array}
     */
    public function execute(AiJob $job): array
    {
        $payload = $job->payload ?? [];
        $scanId  = isset($payload['scan_id']) ? (int) $payload['scan_id'] : 0;
        $origin  = $payload['origin'] ?? 'telegram';

        $scan = $scanId ? BookPageScan::find($scanId) : null;
        if (! $scan) {
            return $this->fail('scan_not_found', "book_page_scans#{$scanId} پیدا نشد.");
        }

        $bot = Bot::find($scan->bot_id);
        $token = $bot ? ($origin === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token) : null;
        if (! $bot || ! $token) {
            return $this->fail('no_bot_token', 'token bot برای دانلود فایل پیدا نشد.');
        }

        $apiBase = $origin === 'bale' ? 'https://tapi.bale.ai' : 'https://api.telegram.org';

        // ۱/۳: getFile — پیدا کردن file_path
        $fileResp = Http::timeout(30)->get(
            $apiBase . '/bot' . $token . '/getFile',
            ['file_id' => $scan->file_id]
        );

        $filePath = $fileResp->json('result.file_path');

        if ($fileResp->status() >= 400 || ! $filePath) {
            $this->persistError($scan, 'file_get_failed');

            return $this->fail('file_get_failed', 'getFile پلتفرم ناموفق بود.');
        }

        // ۲/۳: دانلود فایل تصویر
        $downloadResp = Http::timeout(30)->get($apiBase . '/file/bot' . $token . $filePath);

        if ($downloadResp->status() >= 400 || trim((string) $downloadResp->body()) === '') {
            $this->persistError($scan, 'file_download_failed');

            return $this->fail('file_download_failed', 'دانلود فایل تصویر ناموفق بود.');
        }

        // ۳/۳: Vision OCR روی فایل موقت
        $tmpDir = storage_path('app/ai-tmp');
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $tmpFile = $tmpDir . '/ocr_' . $scan->id . '_' . uniqid() . '.jpg';
        file_put_contents($tmpFile, (string) $downloadResp->body());

        try {
            $vision = (new VisionAdapter())->extractText((string) $tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        if (! $vision['success']) {
            $code = $vision['error_code'] ?? 'provider_error';
            $this->persistError($scan, $code);

            return $this->fail($code, $vision['message'] ?? 'Vision extraction failed.');
        }

        // خروجی خالی = خروجی جعلی نیست؛ retry می‌شود
        $text = trim($vision['text'] ?? '');
        if ($text === '') {
            $this->persistError($scan, 'empty_result');

            return $this->fail('empty_result', 'Vision متن خالی برگرداند.');
        }

        $scan->forceFill([
            'ocr_text'  => $text,
            'ocr_error' => null,
            'ocr_at'    => now(),
        ])->save();

        return [
            'success'          => true,
            'error_code'       => null,
            'message'          => null,
            'result_reference' => 'scan:' . $scan->id,
            'usage'            => $vision['usage'] ?? null,
        ];
    }

    /**
     * ثبت error_code آخرین تلاش روی اسکن (بدون throw).
     */
    private function persistError(BookPageScan $scan, string $code): void
    {
        try {
            $scan->forceFill([
                'ocr_error' => $code,
                'ocr_at'    => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::warning('[BookPixelOcr] persistError failed', [
                'scan_id' => $scan->id,
                'code'    => $code,
                'error'   => AiErrorRedactor::redact($e->getMessage()),
            ]);
        }
    }

    /**
     * @return array{success: bool, error_code: ?string, message: ?string,
     *               result_reference: ?string, usage: ?array}
     */
    private function fail(string $errorCode, string $message): array
    {
        return [
            'success'          => false,
            'error_code'       => $errorCode,
            'message'          => $message,
            'result_reference' => null,
            'usage'            => null,
        ];
    }
}
