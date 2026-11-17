<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\Book;
use App\Models\BookPage;
use App\Models\BookPageScan;
use App\Models\BookPageVoice;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Services\BookPixel\BookPixelResultStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MISSION 09 / Task 2 — BookPixelResultStatus (pure read-only status service).
 *
 * Contract:
 *  - No scans  → empty list.
 *  - ocr_text set  → completed + preview (whitespace-collapsed, ≤200 chars).
 *  - active AI job (PENDING/QUEUED/PROCESSING/RETRYING/DEFERRED) → pending.
 *  - no active job + ocr_error → failed with the error code.
 *  - terminal jobs do NOT count as pending.
 *  - voice entry mirrors the ASR state; entries are latest-first, capped at $limit.
 */
class BookPixelResultStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(int $suffix = 1): array
    {
        $bot = Bot::create([
            'telegram_bot_name'  => 'result-' . uniqid(),
            'telegram_bot_token' => '12345:RESULT_TOKEN',
        ]);

        $botUser = BotUsers::create([
            'chat_id' => 800000000 + random_int(1, 999999) + $suffix,
            'bot_id'  => $bot->id,
            'origin'  => 'telegram',
            'status'  => 'active',
        ]);

        $book = Book::create(['name' => 'کتاب Result ' . uniqid(), 'bot_id' => $bot->id]);

        return ['bot' => $bot, 'botUser' => $botUser, 'book' => $book];
    }

    private function makeScan(array $f, int $page, array $attrs = []): BookPageScan
    {
        $bookPage = BookPage::create(['book_id' => $f['book']->id, 'page_number' => $page]);

        return BookPageScan::create(array_merge([
            'book_page_id'   => $bookPage->id,
            'user_id'        => $f['botUser']->id,
            'bot_id'         => $f['bot']->id,
            'page_number'    => $page,
            'file_id'        => 'RESULT_FILE_' . uniqid(),
            'status'         => 'pending_approval',
            'points_awarded' => 0,
        ], $attrs));
    }

    private function makeVoice(BookPageScan $scan, array $f, array $attrs = []): BookPageVoice
    {
        return BookPageVoice::create(array_merge([
            'book_page_scan_id' => $scan->id,
            'user_id'           => $f['botUser']->id,
            'bot_id'            => $f['bot']->id,
            'file_id'           => 'RESULT_VOICE_' . uniqid(),
            'points_awarded'    => 0,
        ], $attrs));
    }

    private function makeActiveOcrJob(BookPageScan $scan, array $f, string $state = AiJob::STATE_QUEUED): AiJob
    {
        return AiJob::create([
            'job_id'          => 'ocr-job-' . uniqid(),
            'idempotency_key' => 'ocr-key-' . uniqid(),
            'bot_id'          => $f['bot']->id,
            'user_id'         => $f['botUser']->id,
            'feature'         => 'ocr',
            'priority'        => AiJob::PRIORITY_REGULAR,
            'state'           => $state,
            'provider'        => 'litellm',
            'model'           => 'qwen38',
            'payload'         => [
                'scan_id' => $scan->id,
                'file_id' => $scan->file_id,
                'origin'  => 'telegram',
                'bot_id'  => $f['bot']->id,
            ],
        ]);
    }

    /** 300+ char Persian sample for the preview truncation test. */
    private function longText(): string
    {
        return str_repeat('متن آزمایشی هوش مصنوعی برای برش پیش‌نمایش. ', 20);
    }

    public function test_no_scans_returns_empty_list(): void
    {
        $f = $this->makeUser(1);

        $this->assertSame([], app(BookPixelResultStatus::class)->forUser($f['botUser']->id, $f['bot']->id));
    }

    public function test_completed_ocr_shows_completed_with_truncated_preview(): void
    {
        $f = $this->makeUser(2);
        $scan = $this->makeScan($f, 3, ['ocr_text' => $this->longText()]);

        $entries = app(BookPixelResultStatus::class)->forUser($f['botUser']->id, $f['bot']->id);

        $this->assertCount(1, $entries);
        $this->assertSame('scan', $entries[0]['type']);
        $this->assertSame($scan->id, $entries[0]['id']);
        $this->assertSame(3, $entries[0]['page_number']);
        $this->assertSame('completed', $entries[0]['status']);
        $this->assertNull($entries[0]['code']);
        $this->assertNotNull($entries[0]['preview']);
        $this->assertLessThanOrEqual(200, mb_strlen($entries[0]['preview']));
    }

    public function test_failed_ocr_shows_failed_with_code(): void
    {
        $f = $this->makeUser(3);
        $this->makeScan($f, 7, ['ocr_error' => 'auth_failed']);

        $entries = app(BookPixelResultStatus::class)->forUser($f['botUser']->id, $f['bot']->id);

        $this->assertCount(1, $entries);
        $this->assertSame('failed', $entries[0]['status']);
        $this->assertSame('auth_failed', $entries[0]['code']);
        $this->assertNull($entries[0]['preview']);
    }

    public function test_active_job_shows_pending(): void
    {
        $f = $this->makeUser(4);
        $scan = $this->makeScan($f, 9);
        $this->makeActiveOcrJob($scan, $f, AiJob::STATE_QUEUED);

        $entries = app(BookPixelResultStatus::class)->forUser($f['botUser']->id, $f['bot']->id);

        $this->assertCount(1, $entries);
        $this->assertSame('pending', $entries[0]['status']);
    }

    public function test_terminal_job_does_not_show_pending(): void
    {
        $f = $this->makeUser(5);
        $scan = $this->makeScan($f, 11, ['ocr_error' => 'provider_error']);
        $this->makeActiveOcrJob($scan, $f, AiJob::STATE_FAILED);

        $entries = app(BookPixelResultStatus::class)->forUser($f['botUser']->id, $f['bot']->id);

        $this->assertCount(1, $entries);
        $this->assertSame('failed', $entries[0]['status']);
        $this->assertSame('provider_error', $entries[0]['code']);
    }

    public function test_voice_entry_reflects_asr_state(): void
    {
        $f = $this->makeUser(6);
        $scan = $this->makeScan($f, 13);
        $this->makeVoice($scan, $f, ['asr_text' => 'متن وویس استخراج شده']);

        $entries = app(BookPixelResultStatus::class)->forUser($f['botUser']->id, $f['bot']->id);

        $this->assertCount(2, $entries);

        $this->assertSame('scan', $entries[0]['type']);
        $this->assertSame('not_requested', $entries[0]['status']);

        $this->assertSame('voice', $entries[1]['type']);
        $this->assertSame(13, $entries[1]['page_number']);
        $this->assertSame('completed', $entries[1]['status']);
        $this->assertSame('متن وویس استخراج شده', $entries[1]['preview']);
    }

    public function test_limit_is_respected_and_latest_first(): void
    {
        $f = $this->makeUser(7);
        $ids = [];
        for ($page = 1; $page <= 6; $page++) {
            $ids[] = $this->makeScan($f, $page)->id;
        }

        $entries = app(BookPixelResultStatus::class)->forUser($f['botUser']->id, $f['bot']->id);

        $this->assertCount(5, $entries);
        $this->assertSame($ids[5], $entries[0]['id'], 'latest scan must come first');
        $this->assertSame($ids[1], $entries[4]['id']);
        $this->assertNotContains($ids[0], array_column($entries, 'id'));
    }
}
