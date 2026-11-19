<?php

namespace Tests\Feature\Modules\BotMother;

use App\Helpers\BotMotherStateHelper;
use App\Models\Bot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Phase 4 — shared mother-bot capabilities for Soroush Plus and Rubika.
 *
 * Exercises the native branch of BotMotherController::botMotherWebhook
 * (POST /api/webhook-bot-mother?origin=soroush|rubika):
 *
 *   /start → endpoint number → language → child token
 *     → BotRegistrationService::registerBot
 *        → getMe → bots row with the per-platform columns
 *        → setWebhook (soroush) / updateBotEndpoints (rubika)
 *
 * All messenger API calls are mocked with Http::fake (MOCK_TESTED).
 * The mother-bot tokens come from the reserved env vars
 * SOROUSH_BOT_TOKEN / RUBIKA_BOT_TOKEN; the admin chat id 485750575
 * is wired through the AdminHelper env keys in setUp().
 */
class BotMotherSoroushRubikaWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT_ID = 485750575;

    private const MOTHER_SOROUSH_TOKEN = '1112223:MotherSoroushTokenABCDEF-GHIJKLMNOPQ-012';
    private const CHILD_SOROUSH_TOKEN = '2233444:ChildSoroushTokenABCDEF-GHIJKLMNOPQ-012';
    private const MOTHER_RUBIKA_TOKEN = 'RubikaMotherToken_abc123';
    private const CHILD_RUBIKA_TOKEN = 'RubikaChildToken_abc1234';

    private const SOROUSH_URL = '/api/webhook-bot-mother?origin=soroush&token=' . self::MOTHER_SOROUSH_TOKEN . '&bot_mother_id=1';
    private const RUBIKA_URL = '/api/webhook-bot-mother?origin=rubika&token=' . self::MOTHER_RUBIKA_TOKEN . '&bot_mother_id=1';

    private const ADMIN_KEYS = [
        'CHAT_ID_ACCOUNT_1_SABER',
        'CHAT_ID_ACCOUNT_2_SABER',
        'SUPER_ADMIN_CHAT_ID_TELEGRAM',
        'SUPER_ADMIN_CHAT_ID_BALE',
        'SUPER_ADMIN_CHAT_ID_BALE2',
        'SUPER_ADMIN_CHAT_ID_GAP',
    ];

    /** @var array<string, array{putenv: string|false, env: string|null, server: string|null}> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Admin check (AdminHelper::isAdmin) and the mother client both read
        // env() directly — wire the mock chat as admin and provide mother tokens.
        foreach (self::ADMIN_KEYS as $key) {
            $this->setEnv($key, (string) self::CHAT_ID);
        }
        $this->setEnv('SOROUSH_BOT_TOKEN', self::MOTHER_SOROUSH_TOKEN);
        $this->setEnv('RUBIKA_BOT_TOKEN', self::MOTHER_RUBIKA_TOKEN);

        // One active endpoint for the wizard to pick.
        DB::table('webhook_endpoints')->insert([
            'endpoint_id' => 'p4-test-endpoint',
            'name' => 'P4 Test Endpoint',
            'route' => 'api/webhook-p4-test',
            'description' => 'phase 4 shared-capabilities test endpoint',
            'requires_bot_mother_id' => true,
            'requires_token' => true,
            'requires_language' => true,
            'supports_multiple_languages' => true,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();
        BotMotherStateHelper::clearState('soroush:' . self::CHAT_ID);
        BotMotherStateHelper::clearState('rubika:' . self::CHAT_ID);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->savedEnv) as $key => $saved) {
            if ($saved['putenv'] === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $saved['putenv']);
            }
            if ($saved['env'] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $saved['env'];
            }
            if ($saved['server'] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $saved['server'];
            }
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // soroush
    // ------------------------------------------------------------------

    public function test_soroush_start_shows_menu_sets_state_and_logs(): void
    {
        $this->fakeApis();

        $response = $this->postSoroush('/start');

        $response->assertStatus(200);
        $this->assertSame(
            BotMotherStateHelper::STATE_WAITING_ENDPOINT_SELECTION,
            BotMotherStateHelper::getCurrentState('soroush:' . self::CHAT_ID)
        );
        $this->assertDatabaseHas('bot_logs', ['type' => 'soroush', 'text' => '/start']);

        // The mother bot answered with the endpoint list.
        Http::assertSent(
            fn (Request $r) => str_contains($r->url(), 'api.splus.ir')
                && str_contains((string) ($r['text'] ?? ''), 'لیست endpoint')
        );
    }

    public function test_soroush_full_flow_registers_bot_and_sets_webhook(): void
    {
        $this->fakeApis();

        $this->postSoroush('/start')->assertStatus(200);
        $this->postSoroush('1')->assertStatus(200);
        $this->postSoroush('fa')->assertStatus(200);
        $this->postSoroush(self::CHILD_SOROUSH_TOKEN)->assertStatus(200);

        $this->assertDatabaseHas('bots', [
            'soroush_bot_token' => self::CHILD_SOROUSH_TOKEN,
            'soroush_bot_name' => 'soroush_test_bot',
            'type' => 'soroush',
            'endpoint_id' => 'p4-test-endpoint',
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'soroush_bot_status' => 'Active',
        ]);

        $bot = Bot::where('soroush_bot_token', self::CHILD_SOROUSH_TOKEN)->first();
        $this->assertNotNull($bot);
        $this->assertTrue((bool) $bot->soroush_webhook_is_set);
        $this->assertEquals(self::CHAT_ID, $bot->soroush_owner_chat_id);

        // State machine finished.
        $this->assertNull(BotMotherStateHelper::getCurrentState('soroush:' . self::CHAT_ID));

        // Webhook was registered with the per-platform token/language/mother id.
        Http::assertSent(function (Request $r) {
            if (!str_contains($r->url(), self::CHILD_SOROUSH_TOKEN . '/setWebhook')) {
                return false;
            }
            $url = (string) ($r['url'] ?? '');

            return str_contains($url, 'origin=soroush')
                && str_contains($url, urlencode(self::CHILD_SOROUSH_TOKEN))
                && str_contains($url, 'bot_mother_id=1')
                && str_contains($url, 'language=fa');
        });

        // getWebhookInfo read-back was attempted (soroush supports it).
        Http::assertSent(
            fn (Request $r) => str_contains($r->url(), self::CHILD_SOROUSH_TOKEN . '/getWebhookInfo')
        );
    }

    public function test_soroush_child_getme_failure_reports_failure_and_creates_no_bot(): void
    {
        $this->fakeApis(
            soroushChildGetMe: fn () => Http::response(['ok' => false, 'description' => 'Unauthorized'])
        );

        $this->postSoroush('/start')->assertStatus(200);
        $this->postSoroush('1')->assertStatus(200);
        $this->postSoroush('fa')->assertStatus(200);
        $this->postSoroush(self::CHILD_SOROUSH_TOKEN)->assertStatus(200);

        $this->assertDatabaseCount('bots', 0);
        $this->assertNull(BotMotherStateHelper::getCurrentState('soroush:' . self::CHAT_ID));

        Http::assertSent(
            fn (Request $r) => str_contains($r->url(), 'api.splus.ir')
                && str_contains((string) ($r['text'] ?? ''), '❌')
        );
    }

    public function test_soroush_invalid_child_token_is_rejected_without_api_call(): void
    {
        $this->fakeApis();

        $this->postSoroush('/start')->assertStatus(200);
        $this->postSoroush('1')->assertStatus(200);
        $this->postSoroush('fa')->assertStatus(200);
        $this->postSoroush('not-a-valid-token')->assertStatus(200);

        $this->assertDatabaseCount('bots', 0);
        $this->assertSame(
            BotMotherStateHelper::STATE_WAITING_TOKEN,
            BotMotherStateHelper::getCurrentState('soroush:' . self::CHAT_ID)
        );

        // No child-API call happened at all.
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), self::CHILD_SOROUSH_TOKEN));
    }

    // ------------------------------------------------------------------
    // rubika
    // ------------------------------------------------------------------

    public function test_rubika_start_shows_menu_sets_state_and_logs(): void
    {
        $this->fakeApis();

        $response = $this->postRubika('/start');

        $response->assertStatus(200);
        $this->assertSame(
            BotMotherStateHelper::STATE_WAITING_ENDPOINT_SELECTION,
            BotMotherStateHelper::getCurrentState('rubika:' . self::CHAT_ID)
        );
        $this->assertDatabaseHas('bot_logs', ['type' => 'rubika', 'text' => '/start']);

        Http::assertSent(
            fn (Request $r) => str_contains($r->url(), 'botapi.rubika.ir')
                && str_contains((string) ($r['text'] ?? ''), 'لیست endpoint')
        );
    }

    public function test_rubika_full_flow_registers_bot_and_updates_endpoint(): void
    {
        $this->fakeApis();

        $this->postRubika('/start')->assertStatus(200);
        $this->postRubika('1')->assertStatus(200);
        $this->postRubika('fa')->assertStatus(200);
        $this->postRubika(self::CHILD_RUBIKA_TOKEN)->assertStatus(200);

        $this->assertDatabaseHas('bots', [
            'rubika_bot_token' => self::CHILD_RUBIKA_TOKEN,
            'rubika_bot_name' => 'rubika_test_bot',
            'type' => 'rubika',
            'endpoint_id' => 'p4-test-endpoint',
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'rubika_bot_status' => 'Active',
        ]);

        $bot = Bot::where('rubika_bot_token', self::CHILD_RUBIKA_TOKEN)->first();
        $this->assertNotNull($bot);
        $this->assertTrue((bool) $bot->rubika_webhook_is_set);
        $this->assertEquals(self::CHAT_ID, $bot->rubika_owner_chat_id);

        $this->assertNull(BotMotherStateHelper::getCurrentState('rubika:' . self::CHAT_ID));

        // Webhook endpoint registered with type=ReceiveUpdate (rubika plural method).
        Http::assertSent(function (Request $r) {
            if (!str_contains($r->url(), self::CHILD_RUBIKA_TOKEN . '/updateBotEndpoints')) {
                return false;
            }
            $url = (string) ($r['url'] ?? '');

            return ($r['type'] ?? null) === 'ReceiveUpdate'
                && str_contains($url, 'origin=rubika')
                && str_contains($url, urlencode(self::CHILD_RUBIKA_TOKEN))
                && str_contains($url, 'bot_mother_id=1')
                && str_contains($url, 'language=fa');
        });
    }

    public function test_rubika_getme_without_bot_id_reports_failure(): void
    {
        // Envelope is unverified (BLOCKED) — but a missing bot_id must fail.
        $this->fakeApis(
            rubikaChildGetMe: fn () => Http::response(['result' => ['username' => 'some_bot']])
        );

        $this->postRubika('/start')->assertStatus(200);
        $this->postRubika('1')->assertStatus(200);
        $this->postRubika('fa')->assertStatus(200);
        $this->postRubika(self::CHILD_RUBIKA_TOKEN)->assertStatus(200);

        $this->assertDatabaseCount('bots', 0);
        $this->assertNull(BotMotherStateHelper::getCurrentState('rubika:' . self::CHAT_ID));
    }

    public function test_rubika_http_401_reports_failure_and_creates_no_bot(): void
    {
        $this->fakeApis(
            rubikaChildGetMe: fn () => Http::response(['error' => 'unauthorized'], 401)
        );

        $this->postRubika('/start')->assertStatus(200);
        $this->postRubika('1')->assertStatus(200);
        $this->postRubika('fa')->assertStatus(200);
        $this->postRubika(self::CHILD_RUBIKA_TOKEN)->assertStatus(200);

        $this->assertDatabaseCount('bots', 0);
        $this->assertNull(BotMotherStateHelper::getCurrentState('rubika:' . self::CHAT_ID));

        Http::assertSent(
            fn (Request $r) => str_contains($r->url(), 'botapi.rubika.ir')
                && str_contains((string) ($r['text'] ?? ''), '❌')
        );
    }

    public function test_rubika_non_new_message_update_is_ignored(): void
    {
        $this->fakeApis();

        $response = $this->postJson(self::RUBIKA_URL, [
            'update' => [
                'type' => 'StartedBot',
                'chat_id' => (string) self::CHAT_ID,
            ],
        ]);

        $response->assertStatus(200);
        Http::assertNothingSent();
        $this->assertDatabaseCount('bot_logs', 0);
        $this->assertNull(BotMotherStateHelper::getCurrentState('rubika:' . self::CHAT_ID));
    }

    // ------------------------------------------------------------------
    // shared guards
    // ------------------------------------------------------------------

    public function test_non_admin_chat_is_rejected(): void
    {
        // Move every admin id away from the mock chat.
        foreach (self::ADMIN_KEYS as $key) {
            $this->setEnv($key, '999');
        }
        $this->fakeApis();

        $this->postSoroush('/start')->assertStatus(200);

        $this->assertDatabaseCount('bots', 0);
        $this->assertNull(BotMotherStateHelper::getCurrentState('soroush:' . self::CHAT_ID));

        Http::assertSent(
            fn (Request $r) => str_contains($r->url(), 'api.splus.ir')
                && str_contains((string) ($r['text'] ?? ''), '⛔')
        );
    }

    public function test_missing_mother_token_returns_error_without_api_call(): void
    {
        $this->setEnv('SOROUSH_BOT_TOKEN', null);
        $this->fakeApis();

        $response = $this->postSoroush('/start');

        $response->assertStatus(200);
        $response->assertJsonPath('ok', false);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    private function postSoroush(string $text): TestResponse
    {
        return $this->postJson(self::SOROUSH_URL, [
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => self::CHAT_ID, 'first_name' => 'S', 'type' => 'private'],
                'from' => ['id' => self::CHAT_ID, 'is_bot' => false],
                'text' => $text,
            ],
        ]);
    }

    private function postRubika(string $text): TestResponse
    {
        return $this->postJson(self::RUBIKA_URL, [
            'update' => [
                'type' => 'NewMessage',
                'chat_id' => (string) self::CHAT_ID,
                'new_message' => [
                    'message_id' => '1',
                    'text' => $text,
                    'time' => '1700000000',
                    'sender_type' => 'User',
                    'sender_id' => self::CHAT_ID,
                ],
            ],
        ]);
    }

    /**
     * Mock both messenger APIs. Child-token URLs can be overridden
     * (e.g. a failing getMe); everything else returns a minimal 2xx JSON.
     */
    private function fakeApis(?callable $soroushChildGetMe = null, ?callable $rubikaChildGetMe = null): void
    {
        Http::fake(function (Request $request) use ($soroushChildGetMe, $rubikaChildGetMe) {
            $url = $request->url();
            $isSoroushChild = str_contains($url, self::CHILD_SOROUSH_TOKEN);
            $isRubikaChild = str_contains($url, self::CHILD_RUBIKA_TOKEN);

            if (str_contains($url, 'api.splus.ir')) {
                if ($isSoroushChild) {
                    if (str_ends_with($url, '/getMe')) {
                        return $soroushChildGetMe
                            ? $soroushChildGetMe($request)
                            : Http::response([
                                'ok' => true,
                                'result' => ['id' => 77, 'is_bot' => true, 'first_name' => 'TestBot', 'username' => 'soroush_test_bot'],
                            ]);
                    }
                    if (str_ends_with($url, '/setWebhook')) {
                        return Http::response(['ok' => true, 'result' => true]);
                    }
                    if (str_ends_with($url, '/getWebhookInfo')) {
                        return Http::response(['ok' => true, 'result' => ['url' => 'https://example.com', 'pending_update_count' => 0]]);
                    }
                }

                // Mother bot replies (wizard messages).
                return Http::response(['ok' => true, 'result' => ['message_id' => 1, 'chat' => ['id' => self::CHAT_ID]]]);
            }

            if (str_contains($url, 'botapi.rubika.ir')) {
                if ($isRubikaChild) {
                    if (str_ends_with($url, '/getMe')) {
                        return $rubikaChildGetMe
                            ? $rubikaChildGetMe($request)
                            : Http::response([
                                'result' => ['bot_id' => 555, 'username' => 'rubika_test_bot', 'bot_title' => 'Rubika Test Bot'],
                            ]);
                    }
                    if (str_ends_with($url, '/updateBotEndpoints')) {
                        return Http::response(['status' => 'ok']);
                    }
                }

                // Mother bot replies (wizard messages).
                return Http::response(['message_id' => '999']);
            }

            return Http::response(['ok' => true]);
        });
    }

    /**
     * Set an env var via all three channels (putenv/$_ENV/$_SERVER),
     * remembering the previous value for tearDown. Pass null to clear.
     */
    private function setEnv(string $key, ?string $value): void
    {
        if (!array_key_exists($key, $this->savedEnv)) {
            $this->savedEnv[$key] = [
                'putenv' => getenv($key),
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
            ];
        }

        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
