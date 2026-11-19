<?php

namespace App\Services\BookPixel;

use App\Models\AiJob;
use App\Models\BookPageScan;
use App\Models\BookPageVoice;
use Illuminate\Support\Str;

/**
 * MISSION 09 — وضعیت زنده‌ی درخواست‌های هوش مصنوعی (OCR/ASR) برای کاربر.
 *
 * سرویس کاملاً خواندنی (read-only) است: نه job می‌سازد، نه رکوردی را
 * تغییر می‌دهد. ورودی: id کاربر bot (bot_users) + bot_id. خروجی: فهرست
 * تازه‌ترین اسکن‌ها/وویس‌ها به‌همراه وضعیت هرکدام:
 *
 *  - completed     : ocr_text / asr_text پر شده (خروجی واقعی)
 *  - pending       : job فعال (PENDING/QUEUED/PROCESSING/RETRYING/DEFERRED)
 *  - failed        : بدون خروجی + ocr_error / asr_error ثبت شده
 *  - not_requested : هنوز درخواستی ثبت نشده
 *
 * اولویت: completed > pending > failed > not_requested.
 * Jobهای terminal (COMPLETED/FAILED/CANCELLED/EXPIRED) هرگز «pending» شمرده نمی‌شوند.
 */
class BookPixelResultStatus
{
    /** Stateهایی که نشانه‌ی «هنوز در حال پردازش» هستند. */
    private const ACTIVE_JOB_STATES = [
        AiJob::STATE_PENDING,
        AiJob::STATE_QUEUED,
        AiJob::STATE_PROCESSING,
        AiJob::STATE_RETRYING,
        AiJob::STATE_DEFERRED,
    ];

    /**
     * وضعیت تازه‌ترین اسکن‌ها (و وویسِ وابسته‌شان) برای یک کاربر.
     *
     * @return array<int, array{type: 'scan'|'voice', id: int, page_number: int|null,
     *     status: 'completed'|'failed'|'pending'|'not_requested',
     *     code: string|null, preview: string|null}>
     */
    public function forUser(int $botUserId, int $botId, int $limit = 5): array
    {
        $scans = BookPageScan::where('user_id', $botUserId)
            ->where('bot_id', $botId)
            ->with('bookPage')
            ->with('voice.bookPageScan.bookPage')
            ->latest('id')
            ->take($limit)
            ->get();

        $entries = [];

        foreach ($scans as $scan) {
            $entries[] = $this->scanEntry($scan);

            // وویسِ همان صفحه (اگر ثبت شده) بلافاصله بعد از اسکنش می‌آید.
            if ($scan->voice !== null) {
                $entries[] = $this->voiceEntry($scan->voice);
            }
        }

        return $entries;
    }

    private function scanEntry(BookPageScan $scan): array
    {
        return [
            'type'        => 'scan',
            'id'          => $scan->id,
            'page_number' => $scan->bookPage?->page_number,
            'status'      => $this->status(
                (string) ($scan->ocr_text ?? ''),
                (string) ($scan->ocr_error ?? ''),
                'ocr',
                'scan_id',
                $scan->id,
                $scan->bot_id
            ),
            'code'    => $this->errorCode($scan->ocr_error),
            'preview' => $this->preview((string) ($scan->ocr_text ?? '')),
        ];
    }

    private function voiceEntry(BookPageVoice $voice): array
    {
        return [
            'type'        => 'voice',
            'id'          => $voice->id,
            'page_number' => $voice->bookPageScan?->bookPage?->page_number,
            'status'      => $this->status(
                (string) ($voice->asr_text ?? ''),
                (string) ($voice->asr_error ?? ''),
                'asr',
                'voice_id',
                $voice->id,
                $voice->bot_id
            ),
            'code'    => $this->errorCode($voice->asr_error),
            'preview' => $this->preview((string) ($voice->asr_text ?? '')),
        ];
    }

    /**
     * اولویت وضعیت: completed → pending (job فعال) → failed → not_requested.
     */
    private function status(string $text, string $error, string $feature, string $payloadKey, int $entityId, int $botId): string
    {
        if ($text !== '') {
            return 'completed';
        }

        if ($this->hasActiveJob($feature, $botId, $payloadKey, $entityId)) {
            return 'pending';
        }

        if ($error !== '') {
            return 'failed';
        }

        return 'not_requested';
    }

    private function hasActiveJob(string $feature, int $botId, string $payloadKey, int $entityId): bool
    {
        return AiJob::where('feature', $feature)
            ->where('bot_id', $botId)
            ->whereIn('state', self::ACTIVE_JOB_STATES)
            ->where("payload->{$payloadKey}", $entityId)
            ->exists();
    }

    /** کد خطا فقط وقتی وضعیت failed است معنا دارد؛ در غیر این‌صورت null. */
    private function errorCode(?string $error): ?string
    {
        return $error !== null && $error !== '' ? $error : null;
    }

    /**
     * پیش‌نمایش خروجی: فقط برای رکورد موفق (متن غیرخالی) و حداکثر 200 کاراکتر،
     * با فشرده‌سازی whitespace‌های پیاپی.
     */
    private function preview(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        return Str::limit(preg_replace('/\s+/u', ' ', $text), 200, '');
    }
}
