<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI-PRODUCTION-VERIFICATION-07 / Task B — Book Pixel /help webhook (TDD, RED first).
 *
 * Covers the REAL /help path end-to-end:
 *   POST /api/webhook-book-pixel?origin=telegram&token=...&bot_mother_id=1&bot_id=...
 *   → BookPixelController::webhook() → handleTextMessage('/help') → handleHelp()
 *   → trans('bot.book_pixel_help') → BotHelper::sendMessage().
 *
 * Notes on verification strategy:
 *  - The vendor Telegram client (saber13812002/telegram-bale-bot-php) sends the
 *    outbound message with RAW curl (not the Laravel Http client), so Http::fake
 *    does NOT intercept it. In CI the call to the fake token fails gracefully
 *    (vendor logs to logs/TelegramErrorLogger-*.txt, returns decoded JSON, no
 *    exception) — the webhook must still return {"status":"ok"}.
 *  - Text correctness (real newlines, no raw `\n`, all 9 commands) is proven
 *    deterministically in Tests\Unit\BookPixelHelpTextTest against the SAME
 *    translation key this handler sends.
 *  - DB side effect: a BotUsers row is created for the chat (proves the /help
 *    handler executed and no other branch ran).
 *
 * Regression guard: a plain (non-command) message must take the default branch
 * (welcome + waiting_isbn state), NOT the help handler.
 */
class BookPixelHelpTest extends TestCase
{
    use RefreshDatabase;

    private function makeBot(string $origin = 'telegram'): Bot
    {
        $token = '11111:HELP_TOKEN';

        return Bot::create([
            'telegram_bot_name'  => $origin === 'telegram' ? 'book-pixel-help-test' : null,
            'bale_bot_name'      => $origin === 'bale' ? 'book-pixel-help-test' : null,
            'telegram_bot_token' => $origin === 'telegram' ? $token : null,
            'bale_bot_token'     => $origin === 'bale' ? $token : null,
        ]);
    }

    private function webhookUrl(Bot $bot, string $origin): string
    {
        $token = $origin === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;

        return '/api/webhook-book-pixel?origin=' . $origin
            . '&token=' . rawurlencode($token)
            . '&bot_mother_id=1&bot_id=' . $bot->id;
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

    /*
    |──────────────────────────────────────────────────────────────────────
    | /help — telegram
    |──────────────────────────────────────────────────────────────────────
    */
    public function test_help_command_returns_ok_and_creates_bot_user(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 710000001;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/help', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        // /help branch executed → bot user provisioned, and NO state was set
        // (handleHelp must not push the user into the isbn flow).
        $this->assertDatabaseHas('bot_users', [
            'chat_id'  => $chatId,
            'bot_id'   => $bot->id,
            'origin'   => 'telegram',
            'status'   => 'active',
        ]);
        $this->assertDatabaseCount('bot_user_states', 0);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | /help — Persian alias «راهنما» (same handler)
    |──────────────────────────────────────────────────────────────────────
    */
    public function test_persian_help_alias_returns_ok(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 710000002;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('راهنما', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('bot_users', ['chat_id' => $chatId, 'bot_id' => $bot->id]);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | /help — bale (platform send compatibility: token from DB, tapi.bale.ai)
    |──────────────────────────────────────────────────────────────────────
    */
    public function test_help_command_returns_ok_for_bale(): void
    {
        $bot = $this->makeBot('bale');
        $chatId = 710000003;

        $response = $this->postJson($this->webhookUrl($bot, 'bale'), $this->textUpdate('/help', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('bot_users', [
            'chat_id' => $chatId,
            'bot_id'  => $bot->id,
            'origin'  => 'bale',
        ]);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | Regression guard — non-command text still takes the default flow
    | (welcome + keyboard), NOT the help handler.
    |──────────────────────────────────────────────────────────────────────
    */
    public function test_plain_text_message_still_takes_default_flow_not_help(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 710000004;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('سلام', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        // Default branch: user provisioned AND state set to waiting_isbn.
        $this->assertDatabaseHas('bot_users', ['chat_id' => $chatId, 'bot_id' => $bot->id]);
        $this->assertDatabaseHas('bot_user_states', [
            'bot_mother_id' => 1,
            'state'         => 'waiting_isbn',
        ]);
    }
}
