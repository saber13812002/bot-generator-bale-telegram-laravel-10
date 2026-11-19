<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\Book;
use App\Models\BookDraft;
use App\Models\BookPage;
use App\Models\BookPageScan;
use App\Models\BookPageVoice;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Services\Ai\AiFeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * MISSION 09 / Task 1 — 50 MB media cap at the Book Pixel webhook (photo + voice).
 *
 * Contract (golden rule, docs/AGENT_HANDOFF.md): media files over 50 MB are
 * rejected at the webhook layer — no draft, no scan/voice record, no AI job —
 * and the conversation state is PRESERVED so the user can retry. Files at
 * exactly 50 MB are accepted. Files with missing `file_size` metadata cannot
 * be validated → accepted, with a Log::warning.
 *
 * All capability flags are OFF in these tests: this suite validates the
 * WEBHOOK layer independently of the AI feature gate.
 *
 * Outbound vendor Telegram sends are raw-curl (not Http::fake-able); with the
 * fake token they fail gracefully in the test environment (established
 * pattern, see BookPixelOcrTest webhook tests).
 */
class BookPixelMediaValidationTest extends TestCase
{
    use RefreshDatabase;

    private const MEDIA_MAX = 50 * 1024 * 1024; // 50 MB — golden rule

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        AiFeatureGate::clearHealthCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | Photo
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_photo_over_50mb_is_rejected_without_scan_draft_or_job(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));

        $fixture = $this->makeScanFixture('telegram');
        $this->setWaitingScanState($fixture['botUser'], $fixture['book']->id, 5);

        $this->postJson(
            $this->webhookUrl($fixture['bot']),
            $this->photoUpdateWithSize('BIG_PHOTO_A', (int) $fixture['botUser']->chat_id, self::MEDIA_MAX + 1)
        )->assertStatus(200)->assertJson(['status' => 'ok']);

        $this->assertSame(0, BookPageScan::count(), 'oversized photo must not create a scan');
        $this->assertSame(
            0,
            BookDraft::where('file_id', 'BIG_PHOTO_A')->count(),
            'oversized photo must not be saved to drafts'
        );
        $this->assertSame(0, AiJob::count(), 'oversized photo must not dispatch an AI job');

        // state preserved → user can retry (assertDatabaseHas has no message param)
        $this->assertDatabaseHas('bot_user_states', [
            'bot_user_id' => $fixture['botUser']->id,
            'state'       => 'waiting_scan',
        ]);
    }

    public function test_photo_at_exactly_50mb_is_accepted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));

        $fixture = $this->makeScanFixture('telegram');
        $this->setWaitingScanState($fixture['botUser'], $fixture['book']->id, 5);

        $this->postJson(
            $this->webhookUrl($fixture['bot']),
            $this->photoUpdateWithSize('EDGE_PHOTO_A', (int) $fixture['botUser']->chat_id, self::MEDIA_MAX)
        )->assertStatus(200)->assertJson(['status' => 'ok']);

        // Normal flow intact: scan created, state cleared.
        $scan = BookPageScan::where('file_id', 'EDGE_PHOTO_A')->first();
        $this->assertNotNull($scan, 'photo at exactly 50 MB must be accepted');
        $this->assertSame('pending_approval', $scan->status);
        $this->assertSame(
            0,
            BotUserState::where('bot_user_id', $fixture['botUser']->id)->count(),
            'state must be cleared after a successful scan flow'
        );
    }

    public function test_photo_with_missing_file_size_is_accepted_with_warning(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));

        // LogManager dispatches level methods (info/error/warning/...) via
        // __call, so a partial mock CANNOT pass them through — every level the
        // webhook flow may use must be stubbed. The specific expectation comes
        // FIRST (Mockery matches in definition order), then catch-alls.
        Log::shouldReceive('warning')
            ->withArgs(fn ($message) => str_contains((string) $message, 'file_size'))
            ->once();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        foreach (['info', 'error', 'notice', 'debug', 'log', 'critical', 'alert', 'emergency'] as $level) {
            Log::shouldReceive($level)->zeroOrMoreTimes();
        }

        $fixture = $this->makeScanFixture('telegram');
        $this->setWaitingScanState($fixture['botUser'], $fixture['book']->id, 5);

        $this->postJson(
            $this->webhookUrl($fixture['bot']),
            $this->photoUpdateWithSize('NOSIZE_PHOTO_A', (int) $fixture['botUser']->chat_id, null)
        )->assertStatus(200)->assertJson(['status' => 'ok']);

        // Cannot validate → accepted (no exception, normal flow).
        $scan = BookPageScan::where('file_id', 'NOSIZE_PHOTO_A')->first();
        $this->assertNotNull($scan, 'photo without file_size must be accepted');
        $this->assertDatabaseHas('book_drafts', [
            'file_id' => 'NOSIZE_PHOTO_A',
            'type'    => 'scan',
        ]);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | Voice
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_voice_over_50mb_is_rejected_without_voice_record_or_job(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));

        $fixture = $this->makeVoiceFixture('telegram');
        $this->setVoiceState($fixture['botUser'], $fixture['scan']->id);

        $this->postJson(
            $this->webhookUrl($fixture['bot']),
            $this->voiceUpdateWithSize('BIG_VOICE_A', (int) $fixture['botUser']->chat_id, self::MEDIA_MAX + 1)
        )->assertStatus(200)->assertJson(['status' => 'ok']);

        $this->assertSame(0, BookPageVoice::count(), 'oversized voice must not create a voice record');
        $this->assertSame(0, AiJob::count(), 'oversized voice must not dispatch an AI job');

        // voice state preserved → user can retry
        $this->assertDatabaseHas('bot_user_states', [
            'bot_user_id' => $fixture['botUser']->id,
            'state'       => 'sending_voice',
        ]);
    }

    public function test_voice_at_exactly_50mb_is_accepted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));

        $fixture = $this->makeVoiceFixture('telegram');
        $this->setVoiceState($fixture['botUser'], $fixture['scan']->id);

        $this->postJson(
            $this->webhookUrl($fixture['bot']),
            $this->voiceUpdateWithSize('EDGE_VOICE_A', (int) $fixture['botUser']->chat_id, self::MEDIA_MAX)
        )->assertStatus(200)->assertJson(['status' => 'ok']);

        $voice = BookPageVoice::where('file_id', 'EDGE_VOICE_A')->first();
        $this->assertNotNull($voice, 'voice at exactly 50 MB must be accepted');
        $this->assertSame(
            0,
            BotUserState::where('bot_user_id', $fixture['botUser']->id)->count(),
            'state must be cleared after a successful voice flow'
        );
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | Fixtures (same shape as BookPixelOcrTest / BookPixelAsrTest)
    |──────────────────────────────────────────────────────────────────────
    */

    private function makeScanFixture(string $origin = 'telegram'): array
    {
        $token = $origin === 'bale' ? '65432:BALE_TOKEN' : '12345:OCR_TOKEN';
        $name  = 'mediaval-' . $origin . '-' . uniqid();

        $bot = Bot::create([
            'telegram_bot_name'  => $origin === 'telegram' ? $name : null,
            'bale_bot_name'      => $origin === 'bale' ? $name : null,
            'telegram_bot_token' => $origin === 'telegram' ? $token : null,
            'bale_bot_token'     => $origin === 'bale' ? $token : null,
        ]);

        $botUser = BotUsers::create([
            'chat_id' => 900000000 + random_int(1, 999999),
            'bot_id'  => $bot->id,
            'origin'  => $origin,
            'status'  => 'active',
        ]);

        $book = Book::create(['name' => 'کتاب MediaVal ' . uniqid(), 'bot_id' => $bot->id]);
        $page = BookPage::create(['book_id' => $book->id, 'page_number' => 1]);

        return ['bot' => $bot, 'botUser' => $botUser, 'book' => $book, 'page' => $page];
    }

    private function makeVoiceFixture(string $origin = 'telegram'): array
    {
        $fixture = $this->makeScanFixture($origin);

        $scan = BookPageScan::create([
            'book_page_id'   => $fixture['page']->id,
            'user_id'        => $fixture['botUser']->id,
            'bot_id'         => $fixture['bot']->id,
            'page_number'    => 1,
            'file_id'        => 'ASR_SCAN_' . uniqid(),
            'status'         => 'pending_approval',
            'points_awarded' => 0,
        ]);

        $fixture['scan'] = $scan;

        return $fixture;
    }

    private function setWaitingScanState(BotUsers $botUser, int $bookId, int $pageNumber): void
    {
        BotUserState::create([
            'bot_user_id'    => $botUser->id,
            'bot_mother_id'  => 1,
            'state'          => 'waiting_scan',
            'data'           => ['book_id' => $bookId, 'page_number' => $pageNumber],
            'expires_at'     => now()->addHours(2),
        ]);
    }

    private function setVoiceState(BotUsers $botUser, int $scanId): void
    {
        BotUserState::create([
            'bot_user_id'   => $botUser->id,
            'bot_mother_id' => 1,
            'state'         => 'sending_voice',
            'data'          => ['scan_id' => $scanId],
            'expires_at'    => now()->addHours(2),
        ]);
    }

    private function webhookUrl(Bot $bot): string
    {
        return '/api/webhook-book-pixel?origin=telegram&token=' . $bot->telegram_bot_token
            . '&bot_mother_id=1&bot_id=' . $bot->id;
    }

    /**
     * @param int|null $fileSize null → file_size key omitted entirely
     */
    private function photoUpdateWithSize(string $fileId, int $chatId, ?int $fileSize): array
    {
        $photo = [
            'file_id'        => $fileId,
            'file_unique_id' => $fileId . '_large',
            'width'          => 1000,
            'height'         => 1000,
        ];

        if ($fileSize !== null) {
            $photo['file_size'] = $fileSize;
        }

        return [
            'message' => [
                'message_id' => 100,
                'chat'       => ['id' => $chatId, 'first_name' => 'Test', 'type' => 'private'],
                'date'       => 1763100000,
                'photo'      => [$photo],
            ],
        ];
    }

    /**
     * @param int|null $fileSize null → file_size key omitted entirely
     */
    private function voiceUpdateWithSize(string $fileId, int $chatId, ?int $fileSize): array
    {
        $voice = [
            'file_id'        => $fileId,
            'file_unique_id' => $fileId . '_v',
            'duration'       => 8,
        ];

        if ($fileSize !== null) {
            $voice['file_size'] = $fileSize;
        }

        return [
            'message' => [
                'message_id' => 100,
                'chat'       => ['id' => $chatId, 'first_name' => 'Test', 'type' => 'private'],
                'date'       => 1763100000,
                'voice'      => $voice,
            ],
        ];
    }
}
