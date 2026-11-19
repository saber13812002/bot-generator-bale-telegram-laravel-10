<?php

namespace App\Services\BookPixel;

use App\Models\AiJob;
use App\Models\BookPageVoice;
use App\Models\Bot;
use App\Services\Ai\AiErrorRedactor;
use App\Services\Ai\AiFeatureGate;
use App\Services\Ai\AiJobDispatcher;
use App\Services\Ai\TranscriptionAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WS-G — ASR MVP در Book Pixel.
 *
 * جریان:
 *  1. triggerForVoice() — بعد از ثبت وویس صفحه: بررسی AiFeatureGate +
 *     dispatch idempotent به ai_jobs/ProcessAiJob (با سیاست زمان تهران).
 *  2. execute() — توسط ProcessAiJob: getFile پلتفرم → دانلود فایل صوتی →
 *     TranscriptionAdapter (/v1/audio/transcriptions، زبان fa) →
 *     ذخیره asr_text روی book_page_voices.
 *
 * اصول:
 *  - شکست ASR هرگز بیزینس را نمی‌شکند (trigger هرگز throw نمی‌کند).
 *  - خروجی جعلی نیست: اگر مدل متن خالی بدهد، asr_text null می‌ماند
 *    و job با empty_result retry می‌شود.
 *  - اگر endpoint ASR در LiteLLM موجود نباشد (404/405)، error_code =
 *    endpoint_missing ثبت می‌شود تا ASR «تزئینی» فعال نشود.
 *  - error_code هر تلاش ناموفق روی asr_error ذخیره می‌شود (قابل رصد).
 */
class BookPixelAsrService
{
    /**
     * Trigger ASR برای یک وویس صفحه (از webhook Bot).
     *
     * @return array{dispatched: bool, job: AiJob|null, reason: ?string}
     *   dispatched=true یعنی job ساخته شد (queued یا deferred).
     */
    public function triggerForVoice(BookPageVoice $voice, string $origin, bool $isPro = false): array
    {
        try {
            $gate = (new AiFeatureGate())->check(
                AiFeatureGate::FEATURE_ASR,
                $voice->bot_id,
                $voice->user_id,
                $voice->user_id,
                $isPro
            );

            if (! $gate['allowed']) {
                return ['dispatched' => false, 'job' => null, 'reason' => $gate['reason']];
            }

            $result = AiJobDispatcher::dispatch(
                'asr',
                [
                    'voice_id' => $voice->id,
                    'file_id'  => $voice->file_id,
                    'origin'   => $origin,
                    'bot_id'   => $voice->bot_id,
                ],
                [
                    'bot_id' => $voice->bot_id,
                    'user_id' => $voice->user_id,
                    'is_pro'  => $isPro,
                    'model'   => config('ai.asr_model'),
                ]
            );

            return [
                'dispatched' => true,
                'job'        => $result['job'],
                'reason'     => $result['deferred'] ? 'deferred' : 'queued',
            ];
        } catch (Throwable $e) {
            // ASR هرگز نباید جریان Bot را بشکند
            Log::warning('[BookPixelAsr] triggerForVoice failed', [
                'voice_id' => $voice->id,
                'error'    => AiErrorRedactor::redact($e->getMessage()),
            ]);

            return ['dispatched' => false, 'job' => null, 'reason' => 'error'];
        }
    }

    /**
     * اجرای ASR (توسط ProcessAiJob).
     *
     * @return array{success: bool, error_code: ?string, message: ?string,
     *               result_reference: ?string, usage: ?array}
     */
    public function execute(AiJob $job): array
    {
        $payload  = $job->payload ?? [];
        $voiceId  = isset($payload['voice_id']) ? (int) $payload['voice_id'] : 0;
        $origin   = $payload['origin'] ?? 'telegram';

        $voice = $voiceId ? BookPageVoice::find($voiceId) : null;
        if (! $voice) {
            return $this->fail('voice_not_found', "book_page_voices#{$voiceId} پیدا نشد.");
        }

        $bot = Bot::find($voice->bot_id);
        $token = $bot ? ($origin === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token) : null;
        if (! $bot || ! $token) {
            return $this->fail('no_bot_token', 'token bot برای دانلود فایل پیدا نشد.');
        }

        $apiBase = $origin === 'bale' ? 'https://tapi.bale.ai' : 'https://api.telegram.org';

        // ۱/۳: getFile — پیدا کردن file_path
        $fileResp = Http::timeout(30)->get(
            $apiBase . '/bot' . $token . '/getFile',
            ['file_id' => $voice->file_id]
        );

        $filePath = $fileResp->json('result.file_path');

        if ($fileResp->status() >= 400 || ! $filePath) {
            $this->persistError($voice, 'file_get_failed');

            return $this->fail('file_get_failed', 'getFile پلتفرم ناموفق بود.');
        }

        // ۲/۳: دانلود فایل صوتی
        $downloadResp = Http::timeout(30)->get($apiBase . '/file/bot' . $token . $filePath);

        if ($downloadResp->status() >= 400 || trim((string) $downloadResp->body()) === '') {
            $this->persistError($voice, 'file_download_failed');

            return $this->fail('file_download_failed', 'دانلود فایل صوتی ناموفق بود.');
        }

        // ۳/۳: Transcription (زبان fa) روی فایل موقت
        $tmpDir = storage_path('app/ai-tmp');
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $tmpFile = $tmpDir . '/asr_' . $voice->id . '_' . uniqid() . '.ogg';
        file_put_contents($tmpFile, (string) $downloadResp->body());

        try {
            $asr = (new TranscriptionAdapter())->transcribe((string) $tmpFile, 'fa');
        } finally {
            @unlink($tmpFile);
        }

        if (! $asr['success']) {
            $code = $asr['error_code'] ?? 'provider_error';
            $this->persistError($voice, $code);

            return $this->fail($code, $asr['message'] ?? 'Transcription failed.');
        }

        // خروجی خالی = خروجی جعلی نیست؛ retry می‌شود
        $text = trim($asr['text'] ?? '');
        if ($text === '') {
            $this->persistError($voice, 'empty_result');

            return $this->fail('empty_result', 'مدل ASR متن خالی برگرداند.');
        }

        $voice->forceFill([
            'asr_text'  => $text,
            'asr_error' => null,
            'asr_at'    => now(),
        ])->save();

        return [
            'success'          => true,
            'error_code'       => null,
            'message'          => null,
            'result_reference' => 'voice:' . $voice->id,
            'usage'            => $asr['usage'] ?? null,
        ];
    }

    /**
     * ثبت error_code آخرین تلاش روی وویس (بدون throw).
     */
    private function persistError(BookPageVoice $voice, string $code): void
    {
        try {
            $voice->forceFill([
                'asr_error' => $code,
                'asr_at'    => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::warning('[BookPixelAsr] persistError failed', [
                'voice_id' => $voice->id,
                'code'     => $code,
                'error'    => AiErrorRedactor::redact($e->getMessage()),
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
