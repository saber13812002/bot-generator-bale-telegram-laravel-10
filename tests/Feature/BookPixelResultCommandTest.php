<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookPage;
use App\Models\BookPageScan;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\BotUserState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MISSION 09 / Task 2 — Book Pixel /result webhook (TDD, RED first).
 *
 * Covers the REAL /result path end-to-end:
 *   POST /api/webhook-book-pixel?origin=telegram&token=...&bot_mother_id=1&bot_id=...
 *   → BookPixelController::webhook() → handleTextMessage('/result'|'result'|'نتیجه')
 *   → handleResult() → BookPixelResultStatus::forUser() → BotHelper::sendMessage().
 *
 * Verification strategy (same repo pattern as BookPixelHelpTest):
 *  - The vendor Telegram client sends outbound messages with RAW curl (not
 *    intercepted by Http::fake); the webhook must still return {"status":"ok"}.
 *  - Deterministic DB side effects prove the /result branch executed:
 *    a bot user is provisioned and NO state row is created/changed (the command
 *    must not push the user into the ISBN flow, even mid-conversation).
 *  - Result TEXT correctness is pinned by unit tests against the same
 *    translation keys (BookPixelHelpTextTest).
 */
class BookPixelResultCommandTest extends TestCase
{
    use RefreshDatabase;

    private const MOTHER_ID = 1;

    private function makeBot(string $origin = 'telegram'): Bot
    {
        $token = '22222:RESULT_TOKEN';

        return Bot::create([
            'telegram_bot_name'  => $origin === 'telegram' ? 'book-pixel-result-test' : null,
            'bale_bot_name'      => $origin === 'bale' ? 'book-pixel-result-test' : null,
            'telegram_bot_token' => $origin === 'telegram' ? $token : null,
            'bale_bot_token'     => $origin === 'bale' ? $token : null,
        ]);
    }

    private function webhookUrl(Bot $bot, string $origin): string
    {
        $token = $origin === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;

        return '/api/webhook-book-pixel?origin=' . $origin
            . '&token=' . rawurlencode($token)
            . '&bot_mother_id=' . self::MOTHER_ID . '&bot_id=' . $bot->id;
    }

    private function textUpdate(string $text, int $chatId): array
    {
        return [
            'message' => [
                'message_id' => 1,
                'chat'       => ['id' => $chatId, 'first_name' => 'Test', 'type' => 'private'],
                'date'       => 1763100000,
                'text'       => $text,
            ],
        ];
    }

    /** Seed a bot user (via webhook) + a completed OCR scan for that user. */
    private function seedCompletedOcr(Bot $bot, int $chatId): void
    {
        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/start', $chatId));
        $response->assertStatus(200);

        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('bot_id', $bot->id)
            ->where('origin', 'telegram')
            ->first();

        // Reset any state set by /start so the next /result call starts clean.
        BotUserState::where('bot_user_id', $botUser->id)->delete();

        $book = Book::create(['name' => 'کتاب Result Cmd ' . uniqid(), 'bot_id' => $bot->id]);
        $bookPage = BookPage::create(['book_id' => $book->id, 'page_number' => 5]);

        BookPageScan::create([
            'book_page_id'   => $bookPage->id,
            'user_id'        => $botUser->id,
            'bot_id'         => $bot->id,
            'page_number'    => 5,
            'file_id'        => 'RESULT_CMD_FILE_' . uniqid(),
            'status'         => 'approved',
            'points_awarded' => 0,
            'ocr_text'       => 'متن واقعی استخراج‌شده توسط OCR',
        ]);
    }

    private function seedState(Bot $bot, int $chatId, string $state): void
    {
        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/start', $chatId));
        $response->assertStatus(200);

        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('bot_id', $bot->id)
            ->where('origin', 'telegram')
            ->first();

        BotUserState::where('bot_user_id', $botUser->id)->delete();
        BotUserState::create([
            'bot_user_id'   => $botUser->id,
            'bot_mother_id' => self::MOTHER_ID,
            'state'         => $state,
        ]);
    }

    public function test_result_command_with_no_scans_returns_ok(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 720000001;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/result', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        // /result branch executed → bot user provisioned, and NO state was set
        // (the command must not push the user into the ISBN flow).
        $this->assertDatabaseHas('bot_users', [
            'chat_id'  => $chatId,
            'bot_id'   => $bot->id,
            'origin'   => 'telegram',
            'status'   => 'active',
        ]);
        $this->assertDatabaseCount('bot_user_states', 0);
    }

    public function test_result_command_with_completed_ocr_returns_ok(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 720000002;

        $this->seedCompletedOcr($bot, $chatId);

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/result', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        // Must not change conversation state: exactly 0 state rows.
        $this->assertDatabaseCount('bot_user_states', 0);
    }

    public function test_persian_alias_natijeh_works(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 720000003;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('نتیجه', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('bot_users', ['chat_id' => $chatId, 'bot_id' => $bot->id]);
        $this->assertDatabaseCount('bot_user_states', 0);
    }

    public function test_result_command_while_in_waiting_isbn_state_does_not_change_state(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 720000004;

        $this->seedState($bot, $chatId, 'waiting_isbn');

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/result', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        // Mid-conversation /result must NOT consume or alter the pending state.
        $this->assertDatabaseHas('bot_user_states', [
            'bot_mother_id' => self::MOTHER_ID,
            'state'         => 'waiting_isbn',
        ]);
        $this->assertDatabaseCount('bot_user_states', 1);
    }
}
