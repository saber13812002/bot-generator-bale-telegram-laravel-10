<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\BotUserState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MISSION 09 / Task 3 — Book Pixel command verification suite.
 *
 * VERIFICATION suite (expected GREEN on first run): every real Book Pixel
 * command is exercised through the SAME webhook the production bot uses,
 * asserting 200 + the deterministic DB side effect of the branch that must
 * have run. If any test is RED, it is a discovered production bug: fix it,
 * keep the regression assertion, and record it in the final report
 * (Task 9, five-line bug format).
 *
 * Strategy note (same repo pattern as BookPixelHelpTest): the vendor
 * Telegram/Bale clients send outbound messages with RAW curl (not
 * interceptable by Http::fake); the webhook must still return
 * {"status":"ok"}. /help text correctness (real newlines, all 10 command
 * lines, fa + en) is proven deterministically in
 * Tests\Unit\BookPixelHelpTextTest against the SAME translation key this
 * handler sends — re-asserted here in test 4 for the report.
 */
class BookPixelCommandVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const MOTHER_ID = 1;

    private const FA_COMMANDS = [
        '/start - شروع',
        '/help - راهنما',
        '/cover - ارسال عکس جلد',
        '/search <query> - جستجوی کتاب',
        '/drafts - لیست پیش‌نویس‌ها',
        '/result - پیگیری نتیجه هوش مصنوعی',
        '/cancel - لغو فرآیند',
        '/skip - رد کردن فیلد اختیاری',
        '/score - نمایش امتیاز',
        '/stats - آمار (فقط در گروه)',
    ];

    private const EN_COMMANDS = [
        '/start - Start',
        '/help - Help',
        '/cover - Send cover image',
        '/search <query> - Search books',
        '/drafts - List drafts',
        '/result - Track AI results',
        '/cancel - Cancel process',
        '/skip - Skip optional field',
        '/score - Show score',
        '/stats - Statistics (group only)',
    ];

    private function makeBot(string $origin = 'telegram'): Bot
    {
        $token = '33333:VERIFY_TOKEN';

        return Bot::create([
            'telegram_bot_name'  => $origin === 'telegram' ? 'book-pixel-verify-test' : null,
            'bale_bot_name'      => $origin === 'bale' ? 'book-pixel-verify-test' : null,
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

    private function seedState(Bot $bot, int $chatId, string $state): void
    {
        $botUser = BotUsers::create([
            'chat_id' => $chatId,
            'bot_id'  => $bot->id,
            'origin'  => 'telegram',
            'status'  => 'active',
        ]);

        BotUserState::create([
            'bot_user_id'   => $botUser->id,
            'bot_mother_id' => self::MOTHER_ID,
            'state'         => $state,
        ]);
    }

    public function test_start_sets_waiting_isbn_state(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 730000001;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/start', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('bot_users', ['chat_id' => $chatId, 'bot_id' => $bot->id]);
        $this->assertDatabaseHas('bot_user_states', [
            'bot_mother_id' => self::MOTHER_ID,
            'state'         => 'waiting_isbn',
        ]);
    }

    public function test_help_telegram_returns_ok_and_provisions_user(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 730000002;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/help', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        // /help must not start the ISBN flow.
        $this->assertDatabaseHas('bot_users', [
            'chat_id'  => $chatId,
            'bot_id'   => $bot->id,
            'origin'   => 'telegram',
            'status'   => 'active',
        ]);
        $this->assertDatabaseCount('bot_user_states', 0);
    }

    public function test_help_bale_returns_ok(): void
    {
        $bot = $this->makeBot('bale');
        $chatId = 730000003;

        $response = $this->postJson($this->webhookUrl($bot, 'bale'), $this->textUpdate('/help', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('bot_users', [
            'chat_id' => $chatId,
            'bot_id'  => $bot->id,
            'origin'  => 'bale',
        ]);
    }

    public function test_help_text_has_real_newlines_and_all_commands_in_both_locales(): void
    {
        foreach ([['fa', self::FA_COMMANDS], ['en', self::EN_COMMANDS]] as [$locale, $commands]) {
            $text = (string) trans('bot.book_pixel_help', [], $locale);

            $label = $locale . ' book_pixel_help';

            $this->assertNotSame('', $text, $label . ': translation is empty/missing');
            $this->assertTrue(str_contains($text, "\n"), $label . ': expected a real newline (0x0A)');
            $this->assertFalse(
                str_contains($text, '\\n'),
                $label . ': contains a RAW `\n` (literal backslash-n) instead of a real newline'
            );
            $this->assertGreaterThanOrEqual(2, substr_count($text, "\n"), $label . ': expected multiple real newlines');

            foreach ($commands as $command) {
                $this->assertStringContainsString(
                    $command,
                    $text,
                    $label . ': missing required command line "' . $command . '"'
                );
            }
        }
    }

    public function test_cover_sets_waiting_cover_state(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 730000004;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/cover', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('bot_user_states', [
            'bot_mother_id' => self::MOTHER_ID,
            'state'         => 'waiting_cover',
        ]);
    }

    public function test_search_with_query_returns_ok(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 730000005;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/search کتاب', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        // /search is a read-only lookup; it must not start the ISBN flow.
        $this->assertDatabaseHas('bot_users', ['chat_id' => $chatId, 'bot_id' => $bot->id]);
        $this->assertDatabaseCount('bot_user_states', 0);
    }

    public function test_drafts_with_no_drafts_returns_ok(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 730000006;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/drafts', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
        $this->assertDatabaseCount('bot_user_states', 0);
    }

    public function test_cancel_returns_ok_and_clears_state(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 730000007;
        $this->seedState($bot, $chatId, 'waiting_book_name');

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/cancel', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
        $this->assertDatabaseCount('bot_user_states', 0);
    }

    public function test_skip_from_waiting_isbn_moves_to_waiting_book_name(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 730000008;
        $this->seedState($bot, $chatId, 'waiting_isbn');

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/skip', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('bot_user_states', [
            'bot_mother_id' => self::MOTHER_ID,
            'state'         => 'waiting_book_name',
        ]);
    }

    public function test_score_returns_ok(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 730000009;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/score', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('bot_users', ['chat_id' => $chatId, 'bot_id' => $bot->id]);
        $this->assertDatabaseCount('bot_user_states', 0);
    }

    public function test_stats_in_private_chat_returns_ok(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 730000010;

        // Private chat: the vendor getChat call with the fake token fails
        // gracefully in the test env → non-group branch. No exception, no state.
        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('/stats', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
        $this->assertDatabaseCount('bot_user_states', 0);
    }

    public function test_plain_text_still_takes_default_flow(): void
    {
        $bot = $this->makeBot('telegram');
        $chatId = 730000011;

        $response = $this->postJson($this->webhookUrl($bot, 'telegram'), $this->textUpdate('سلام', $chatId));

        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        // Regression guard: free text must take the default branch
        // (welcome + waiting_isbn), not be swallowed by a command branch.
        $this->assertDatabaseHas('bot_users', ['chat_id' => $chatId, 'bot_id' => $bot->id]);
        $this->assertDatabaseHas('bot_user_states', [
            'bot_mother_id' => self::MOTHER_ID,
            'state'         => 'waiting_isbn',
        ]);
    }
}
