<?php

namespace Tests\Feature\Modules\BotMother;

use App\Models\Bot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * BM-RUBIKA-DIAGNOSTIC-BOT — Phase 5: Rubika Diagnostic Bot.
 *
 * Exercises the new BotMother bot type "rubika-diagnostic":
 *   POST /api/webhook-rubika-diagnostic  →  RubikaDiagnosticBotController::webhook
 * and the companion testing command:
 *   php artisan rubika:diagnostic-poll {bot_id}
 *
 * Scenarios covered (per the mission):
 *  - private / group / channel messages with privacy routing
 *    (private → sender, group → sender only if owner, channel → owner only)
 *  - forwarded messages: origin (type_from / from_chat_id / from_sender_id /
 *    from_message_id) is reported SEPARATELY from the receiver chat —
 *    «مبدأ فوروارد ≠ چت گیرنده»
 *  - BotJoined: admin with SendMessages (welcome posted + confirmed),
 *    no SendMessages (honest report, no attempt), unknown permissions
 *    (attempt + honest confirmation), failed send (dedup cleared)
 *  - BotRemoved (join-dedup cleared so a rejoin welcomes again)
 *  - BotPermissionsChanged (reported; identical change deduplicated,
 *    a real change re-reported)
 *  - unknown events (reported, never silently dropped)
 *  - commands: /start /id /chat /forward
 *  - duplicate update prevention (atomic Cache::add)
 *  - cross-platform interference (telegram token / soroush payload on the
 *    Rubika endpoint must not trigger any Rubika flow)
 *  - poll command: offset contract (next_offset_id), start_id is NOT an
 *    offset, polling/webhook conflict guard + --force
 *
 * All Rubika API calls are mocked with Http::fake (MOCK_TESTED). No live
 * API call is ever made. ⚠️ Exact live API shapes remain BLOCKED until a
 * live test; fixtures follow the local docs
 * (docs/features/rubika/sources/*).
 */
class RubikaDiagnosticBotWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT_ID = 485750575;
    private const DIAG_TOKEN = 'DiagRubikaToken_abc123';

    private const URL = '/api/webhook-rubika-diagnostic?origin=rubika&token=' . self::DIAG_TOKEN . '&bot_mother_id=1';

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

        // Admin check (AdminHelper::isAdmin / ::getAdmins) reads env()
        // directly — collapse owner + all admin ids to ONE chat so
        // notifyOwner() sends exactly one message.
        foreach (self::ADMIN_KEYS as $key) {
            $this->setEnv($key, (string) self::CHAT_ID);
        }

        Cache::flush();
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
    // webhook — basic guards
    // ------------------------------------------------------------------

    public function test_non_rubika_payloads_are_acked_without_processing(): void
    {
        $this->makeBot();
        $this->fakeApis();

        // Telegram-style update: no string `type` field.
        $this->postJson(self::URL, [
            'update' => ['message_id' => 1, 'chat' => ['id' => 5, 'first_name' => 'X']],
        ])->assertStatus(200)->assertJsonPath('ok', true);

        // Soroush-style payload: no `update` key at all.
        $this->postJson(self::URL, [
            'message' => ['message_id' => 1, 'chat' => ['id' => 5]],
        ])->assertStatus(200)->assertJsonPath('ok', true);

        Http::assertNothingSent();
        $this->assertDatabaseCount('bot_logs', 0);
    }

    public function test_unknown_token_is_acked_without_processing(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $this->postJson(
            '/api/webhook-rubika-diagnostic?origin=rubika&token=UnknownToken_123456789&bot_mother_id=1',
            ['update' => $this->privateUpdate('hi')]
        )->assertStatus(200)->assertJsonPath('ok', true);

        Http::assertNothingSent();
        $this->assertDatabaseCount('bot_logs', 0);
    }

    public function test_foreign_tokens_and_payloads_do_not_interfere(): void
    {
        // A Rubika diagnostic bot AND a pure-telegram bot both exist.
        $this->makeBot();
        $telegramBot = Bot::create([
            'type' => 'telegram',
            'telegram_bot_token' => '111222333:AABBCCDDEEFF0011223344556677889900',
        ]);
        $this->assertNotNull($telegramBot->id);

        $this->fakeApis();

        // 1) A telegram token on the Rubika endpoint resolves to nothing
        //    (rubika_bot_token column only).
        $this->postJson(
            '/api/webhook-rubika-diagnostic?origin=rubika&token=111222333:AABBCCDDEEFF0011223344556677889900&bot_mother_id=1',
            ['update' => $this->privateUpdate('cross?')]
        )->assertStatus(200)->assertJsonPath('ok', true);

        // 2) A soroush-shaped payload on the Rubika endpoint is not a
        //    Rubika update.
        $this->postJson(self::URL, [
            'message' => ['message_id' => 1, 'chat' => ['id' => 5]],
        ])->assertStatus(200)->assertJsonPath('ok', true);

        Http::assertNothingSent();
        $this->assertDatabaseCount('bot_logs', 0);
    }

    // ------------------------------------------------------------------
    // private messages
    // ------------------------------------------------------------------

    public function test_private_message_from_owner_replies_full_report(): void
    {
        $bot = $this->makeBot();
        $this->fakeApis();

        $this->postRubikaUpdate($this->privateUpdate('سلام'))
            ->assertStatus(200)->assertJsonPath('ok', true);

        // Reply goes to the same private chat.
        $this->assertCount(1, $this->sentTo('chatUser123'));
        $text = (string) $this->sentTo('chatUser123')->first()->data()['text'];
        $this->assertStringContainsString('🩺 گزارش تشخیصی روبیکا', $text);
        $this->assertStringContainsString('Chat ID: chatUser123', $text);
        $this->assertStringContainsString('نوع چت: User (خصوصی کاربر)', $text);
        $this->assertStringContainsString('Sender ID: ' . self::CHAT_ID, $text);
        $this->assertStringContainsString('Sender Type: User', $text);
        $this->assertStringContainsString('Message ID: 5001', $text);
        $this->assertStringContainsString('Time: 1700000000', $text);
        $this->assertStringContainsString('نام: Saber Tabatabaei', $text);
        $this->assertStringContainsString('Username: saber', $text);
        $this->assertStringContainsString('متن: سلام', $text);

        // Inline chat object in the update → getChat is NOT needed.
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/getChat'));

        // Defensive bot_logs row: opaque chat id → 0, numeric ids kept.
        $this->assertDatabaseHas('bot_logs', [
            'webhook_endpoint_uri' => 'webhook-rubika-diagnostic',
            'type' => 'rubika',
            'bot_id' => $bot->id,
            'text' => 'سلام',
            'is_command' => false,
            'chat_id' => 0,
            'message_id' => 5001,
            'from_id' => self::CHAT_ID,
        ]);
    }

    public function test_private_message_from_stranger_reports_only_to_owner(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $update = $this->privateUpdate('hello stranger');
        $update['chat_id'] = 'strChat1';
        $update['chat'] = ['id' => 'strChat1', 'type' => 'User'];
        $update['new_message']['sender_id'] = '777000111';
        $update['new_message']['first_name'] = null;
        $update['new_message']['last_name'] = null;

        $this->postRubikaUpdate($update)->assertStatus(200);

        // No reply to the stranger's chat.
        Http::assertNotSent(
            fn (Request $r) => str_ends_with($r->url(), '/sendMessage')
                && ($r->data()['chat_id'] ?? null) === 'strChat1'
        );

        // ...but the owner is notified with the full report.
        $this->assertCount(1, $this->sentTo((string) self::CHAT_ID));
        $text = (string) $this->sentTo((string) self::CHAT_ID)->first()->data()['text'];
        $this->assertStringContainsString('🩺 گزارش تشخیصی روبیکا', $text);
        $this->assertStringContainsString('Chat ID: strChat1', $text);
        $this->assertStringContainsString('Sender ID: 777000111', $text);
        // Missing name fields → honest "not found", never guessed.
        $this->assertStringContainsString('نام: — (فیلد نام در پاسخ API یافت نشد)', $text);
    }

    // ------------------------------------------------------------------
    // group messages + commands
    // ------------------------------------------------------------------

    public function test_id_command_in_group_from_owner_replies_in_group(): void
    {
        $bot = $this->makeBot();
        $this->fakeApis(getChat: fn () => Http::response(['chat' => [
            'id' => 'grpOwner', 'type' => 'Group', 'title' => 'Owner Group', 'member_count' => 42,
        ]]));

        $update = [
            'type' => 'NewMessage',
            'chat_id' => 'grpOwner',
            'chat_type' => 'Group',
            'new_message' => [
                'message_id' => '5002',
                'text' => '/id',
                'sender_type' => 'User',
                'sender_id' => (string) self::CHAT_ID,
            ],
        ];

        $this->postRubikaUpdate($update)->assertStatus(200);

        $this->assertCount(1, $this->sentTo('grpOwner'));
        $text = (string) $this->sentTo('grpOwner')->first()->data()['text'];
        $this->assertStringContainsString('🆔 شناسه‌ها', $text);
        $this->assertStringContainsString('Chat ID: grpOwner', $text);
        $this->assertStringContainsString('نوع چت: Group (گروه)', $text);
        $this->assertStringContainsString('Sender ID: ' . self::CHAT_ID, $text);
        $this->assertStringContainsString('Message ID: 5002', $text);

        $this->assertDatabaseHas('bot_logs', [
            'type' => 'rubika',
            'bot_id' => $bot->id,
            'text' => '/id',
            'is_command' => true,
        ]);
    }

    public function test_group_message_from_non_owner_reports_only_to_owner(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $update = [
            'type' => 'NewMessage',
            'chat_id' => 'grpNonOwner',
            'chat_type' => 'Group',
            'new_message' => [
                'message_id' => '5003',
                'text' => 'plain group msg',
                'sender_type' => 'User',
                'sender_id' => '999000123',
            ],
        ];

        $this->postRubikaUpdate($update)->assertStatus(200);

        Http::assertNotSent(
            fn (Request $r) => str_ends_with($r->url(), '/sendMessage')
                && ($r->data()['chat_id'] ?? null) === 'grpNonOwner'
        );

        $this->assertCount(1, $this->sentTo((string) self::CHAT_ID));
        $text = (string) $this->sentTo((string) self::CHAT_ID)->first()->data()['text'];
        $this->assertStringContainsString('🩺 گزارش تشخیصی روبیکا', $text);
        $this->assertStringContainsString('Chat ID: grpNonOwner', $text);
        $this->assertStringContainsString('Sender ID: 999000123', $text);
    }

    public function test_group_command_from_non_owner_is_not_handled(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $update = [
            'type' => 'NewMessage',
            'chat_id' => 'grpCmd',
            'chat_type' => 'Group',
            'new_message' => [
                'message_id' => '5004',
                'text' => '/id',
                'sender_type' => 'User',
                'sender_id' => '999000123',
            ],
        ];

        $this->postRubikaUpdate($update)->assertStatus(200);

        // Commands are only handled in private chats or by owner/admin.
        // No message is sent anywhere (getChat detection is allowed).
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/sendMessage'));
        $this->assertDatabaseHas('bot_logs', ['type' => 'rubika', 'text' => '/id']);
    }

    // ------------------------------------------------------------------
    // channel messages
    // ------------------------------------------------------------------

    public function test_channel_message_reports_to_owner_only(): void
    {
        $bot = $this->makeBot();
        $this->fakeApis();

        $update = [
            'type' => 'NewMessage',
            'chat_id' => 'chChannel88',
            'chat' => ['id' => 'chChannel88', 'type' => 'Channel', 'title' => 'Test Channel', 'username' => 'test_channel'],
            'new_message' => [
                'message_id' => '5005',
                'text' => 'canal post',
                'sender_type' => 'Bot',
                'sender_id' => 'bot777',
            ],
        ];

        $this->postRubikaUpdate($update)->assertStatus(200);

        // The bot can never reply in a channel → owner only.
        Http::assertNotSent(
            fn (Request $r) => str_ends_with($r->url(), '/sendMessage')
                && ($r->data()['chat_id'] ?? null) === 'chChannel88'
        );

        $this->assertCount(1, $this->sentTo((string) self::CHAT_ID));
        $text = (string) $this->sentTo((string) self::CHAT_ID)->first()->data()['text'];
        $this->assertStringContainsString('🩺 گزارش تشخیصی روبیکا', $text);
        $this->assertStringContainsString('Chat ID: chChannel88', $text);
        $this->assertStringContainsString('نوع چت: Channel (کانال)', $text);
        $this->assertStringContainsString('عنوان چت: Test Channel', $text);
        $this->assertStringContainsString('Username: test_channel', $text);
        $this->assertStringContainsString('Sender ID: bot777', $text);

        $this->assertDatabaseHas('bot_logs', [
            'webhook_endpoint_uri' => 'webhook-rubika-diagnostic',
            'type' => 'rubika',
            'bot_id' => $bot->id,
            'text' => 'canal post',
        ]);
    }

    // ------------------------------------------------------------------
    // commands: /start /chat /forward
    // ------------------------------------------------------------------

    public function test_start_command_returns_menu(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $this->postRubikaUpdate($this->privateUpdate('/start'))->assertStatus(200);

        $this->assertCount(1, $this->sentTo('chatUser123'));
        $text = (string) $this->sentTo('chatUser123')->first()->data()['text'];
        $this->assertStringContainsString('🩺 ربات تشخیصی روبیکا', $text);
        $this->assertStringContainsString('دستورات:', $text);
        $this->assertStringContainsString('/id — نمایش شناسه‌های چت و فرستنده', $text);
        $this->assertStringContainsString('/chat — اطلاعات کامل چت (getChat)', $text);
        $this->assertStringContainsString('/forward — اطلاعات پیام فورواردشده', $text);
    }

    public function test_id_command_in_private_chat(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $this->postRubikaUpdate($this->privateUpdate('/id'))->assertStatus(200);

        $text = (string) $this->sentTo('chatUser123')->first()->data()['text'];
        $this->assertStringContainsString('🆔 شناسه‌ها', $text);
        $this->assertStringContainsString('Chat ID: chatUser123', $text);
        $this->assertStringContainsString('نوع چت: User (خصوصی کاربر)', $text);
        $this->assertStringContainsString('Sender ID: ' . self::CHAT_ID, $text);
        $this->assertStringContainsString('Message ID: 5001', $text);
    }

    public function test_chat_command_uses_getchat(): void
    {
        $this->makeBot();
        $this->fakeApis(getChat: fn () => Http::response(['chat' => [
            'id' => 'chatUser123',
            'type' => 'User',
            'title' => 'Saber Chat',
            'username' => 'saber',
            'description' => 'a private chat',
        ]]));

        $this->postRubikaUpdate($this->privateUpdate('/chat'))->assertStatus(200);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/getChat'));

        $text = (string) $this->sentTo('chatUser123')->first()->data()['text'];
        $this->assertStringContainsString('💬 اطلاعات چت (getChat)', $text);
        $this->assertStringContainsString('Chat ID: chatUser123', $text);
        $this->assertStringContainsString('نوع چت: User (خصوصی کاربر)', $text);
        $this->assertStringContainsString('عنوان: Saber Chat', $text);
        $this->assertStringContainsString('Username: saber', $text);
        // Missing field → honest dash, never guessed.
        $this->assertStringContainsString('تعداد اعضا: —', $text);
        $this->assertStringContainsString('توضیحات: a private chat', $text);
    }

    public function test_chat_command_reports_failure_when_getchat_unavailable(): void
    {
        $this->makeBot();
        $this->fakeApis(getChat: fn () => Http::response('Internal Server Error', 500));

        $this->postRubikaUpdate($this->privateUpdate('/chat'))->assertStatus(200);

        $text = (string) $this->sentTo('chatUser123')->first()->data()['text'];
        $this->assertStringContainsString('⚠️ getChat در دسترس نبود', $text);
        $this->assertStringContainsString('Chat ID: chatUser123', $text);
    }

    // ------------------------------------------------------------------
    // forwarded messages — origin ≠ receiver chat
    // ------------------------------------------------------------------

    public function test_forwarded_message_shows_origin_separately_from_receiver(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $update = $this->privateUpdate('forwarded content');
        $update['new_message']['message_id'] = '6001';
        // Documented mechanism (official docs screenshot): direct fields.
        $update['new_message']['type_from'] = 'User';
        $update['new_message']['from_chat_id'] = 'originalChat999';
        $update['new_message']['from_sender_id'] = '123456789';
        $update['new_message']['from_message_id'] = '777001';

        $this->postRubikaUpdate($update)->assertStatus(200);

        $text = (string) $this->sentTo('chatUser123')->first()->data()['text'];
        // Receiver chat
        $this->assertStringContainsString('Chat ID: chatUser123', $text);
        // Forward origin — explicitly separated from the receiver
        $this->assertStringContainsString('↪️ این پیام فورواردشده است — ⚠️ مبدأ فوروارد ≠ چت گیرنده', $text);
        $this->assertStringContainsString('نوع مبدأ: User', $text);
        $this->assertStringContainsString('Chat ID مبدأ: originalChat999', $text);
        $this->assertStringContainsString('Sender ID اصلی: 123456789', $text);
        $this->assertStringContainsString('Message ID اصلی: 777001', $text);
    }

    public function test_forward_command_reads_forwarded_from_channel(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $update = $this->privateUpdate('/forward');
        $update['new_message']['message_id'] = '6002';
        // Defensive mechanism: forwarded_from array.
        $update['new_message']['forwarded_from'] = [
            'type_from' => 'Channel',
            'from_chat_id' => 'origChannel555',
            'from_sender_id' => 'chOwner42',
            'from_message_id' => '888777',
        ];

        $this->postRubikaUpdate($update)->assertStatus(200);

        $text = (string) $this->sentTo('chatUser123')->first()->data()['text'];
        $this->assertStringContainsString('↪️ اطلاعات فورواردشدهٔ این پیام', $text);
        $this->assertStringContainsString('⚠️ مبدأ فوروارد ≠ چت گیرنده', $text);
        $this->assertStringContainsString('Chat ID گیرنده: chatUser123', $text);
        $this->assertStringContainsString('نوع مبدأ: Channel', $text);
        $this->assertStringContainsString('Chat ID مبدأ: origChannel555', $text);
        $this->assertStringContainsString('Sender ID اصلی: chOwner42', $text);
        $this->assertStringContainsString('Message ID اصلی: 888777', $text);
        $this->assertStringContainsString('Message ID این پیام: 6002', $text);
    }

    public function test_forward_command_on_plain_message_says_not_forwarded(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $this->postRubikaUpdate($this->privateUpdate('/forward'))->assertStatus(200);

        $text = (string) $this->sentTo('chatUser123')->first()->data()['text'];
        $this->assertStringContainsString('این پیام فورواردشده نیست', $text);
    }

    // ------------------------------------------------------------------
    // chat events: BotJoined / BotRemoved / BotPermissionsChanged
    // ------------------------------------------------------------------

    public function test_bot_joined_with_send_permissions_posts_welcome_and_confirms(): void
    {
        $bot = $this->makeBot();
        $this->fakeApis(getChat: fn () => Http::response(['chat' => [
            'id' => 'chChannel88', 'type' => 'Channel', 'title' => 'Test Channel', 'username' => 'test_channel',
        ]]));

        $update = [
            'type' => 'BotJoined',
            'chat_id' => 'chChannel88',
            'permissions' => ['SendMessages' => true, 'Admin' => true],
        ];

        $this->postRubikaUpdate($update)->assertStatus(200);

        // Persian welcome posted IN the chat with Chat ID / type / title / bot / access.
        $this->assertCount(1, $this->sentTo('chChannel88'));
        $welcome = (string) $this->sentTo('chChannel88')->first()->data()['text'];
        $this->assertStringContainsString('📢 ربات به این چت پیوست', $welcome);
        $this->assertStringContainsString('Chat ID: chChannel88', $welcome);
        $this->assertStringContainsString('نوع چت: Channel (کانال)', $welcome);
        $this->assertStringContainsString('عنوان چت: Test Channel', $welcome);
        $this->assertStringContainsString('ربات: @diagtestbot', $welcome);
        $this->assertStringContainsString('SendMessages فعال', $welcome);

        // Owner confirmed (no false success: send was confirmed via analyzeResponse).
        $this->assertCount(1, $this->sentTo((string) self::CHAT_ID));
        $this->assertStringContainsString(
            '✅ BotJoined — خوش‌آمدگویی در چت ارسال شد',
            (string) $this->sentTo((string) self::CHAT_ID)->first()->data()['text']
        );

        // The event itself is logged.
        $this->assertDatabaseHas('bot_logs', [
            'type' => 'rubika',
            'bot_id' => $bot->id,
            'text' => 'BotJoined',
            'is_command' => false,
        ]);
    }

    public function test_bot_joined_without_send_permissions_reports_honestly(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $update = [
            'type' => 'BotJoined',
            'chat_id' => 'grp222',
            'permissions' => 'NoSendMessages',
        ];

        $this->postRubikaUpdate($update)->assertStatus(200);

        // No welcome attempt at all — the bot does not even try.
        Http::assertNotSent(
            fn (Request $r) => str_ends_with($r->url(), '/sendMessage')
                && ($r->data()['chat_id'] ?? null) === 'grp222'
        );

        $this->assertCount(1, $this->sentTo((string) self::CHAT_ID));
        $text = (string) $this->sentTo((string) self::CHAT_ID)->first()->data()['text'];
        $this->assertStringContainsString('🚫 BotJoined — ربات به چت پیوست اما SendMessages غیرفعال است', $text);
        $this->assertStringContainsString('Chat ID: grp222', $text);
        $this->assertStringContainsString('خوش‌آمدگویی ارسال نشد (دلیل: SendMessages غیرفعال).', $text);
    }

    public function test_bot_joined_with_unknown_permissions_attempts_send(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $update = [
            'type' => 'BotJoined',
            'chat_id' => 'grp333',
            'permissions' => [], // present but unreadable → null → attempt
        ];

        $this->postRubikaUpdate($update)->assertStatus(200);

        $welcome = (string) $this->sentTo('grp333')->first()->data()['text'];
        $this->assertStringContainsString('📢 ربات به این چت پیوست', $welcome);
        $this->assertStringContainsString('SendMessages قابل‌تشخیص نبود (فرض: فعال)', $welcome);

        $this->assertCount(1, $this->sentTo((string) self::CHAT_ID));
        $this->assertStringContainsString(
            '✅ BotJoined — خوش‌آمدگویی در چت ارسال شد',
            (string) $this->sentTo((string) self::CHAT_ID)->first()->data()['text']
        );
    }

    public function test_bot_joined_failed_send_reports_and_clears_dedup(): void
    {
        $this->makeBot();
        $this->fakeApis(
            getChat: fn () => Http::response(['chat' => ['id' => 'grp444', 'type' => 'Group', 'title' => 'Failing Group']]),
            sendMessage: fn () => Http::response(['status' => 'error: forbidden'])
        );

        $update = [
            'type' => 'BotJoined',
            'chat_id' => 'grp444',
            'permissions' => ['SendMessages' => true],
        ];

        $this->postRubikaUpdate($update)->assertStatus(200);

        $this->assertCount(1, $this->sentTo((string) self::CHAT_ID));
        $text = (string) $this->sentTo((string) self::CHAT_ID)->first()->data()['text'];
        $this->assertStringContainsString('⚠️ BotJoined — ارسال خوش‌آمدگویی تأیید نشد', $text);
        $this->assertStringContainsString('Chat ID: grp444', $text);
        $this->assertStringContainsString('وضعیت API: ', $text);
        $this->assertStringContainsString('api_rejected', $text);
        $this->assertStringContainsString('کلید dedup پاک شد تا در رویداد بعدی دوباره امتحان شود.', $text);

        // Dedup key cleared → the NEXT BotJoined will retry (no stuck state).
        $this->assertFalse(Cache::has('rubika_diag:event:botjoined:grp444'));
    }

    public function test_bot_removed_clears_join_dedup_and_reports(): void
    {
        $this->makeBot();
        $this->fakeApis();

        // First: the bot joins (dedup key is set).
        $this->postRubikaUpdate([
            'type' => 'BotJoined',
            'chat_id' => 'grp555',
            'permissions' => ['SendMessages' => true],
        ])->assertStatus(200);
        $this->assertTrue(Cache::has('rubika_diag:event:botjoined:grp555'));

        // Then: the bot is removed.
        $this->postRubikaUpdate([
            'type' => 'BotRemoved',
            'chat_id' => 'grp555',
        ])->assertStatus(200);

        // Join-dedup cleared → a rejoin will welcome again.
        $this->assertFalse(Cache::has('rubika_diag:event:botjoined:grp555'));

        $owner = $this->ownerMessages();
        $removal = array_values(array_filter($owner, fn (string $t) => str_contains($t, '🚪 BotRemoved')));
        $this->assertCount(1, $removal);
        $this->assertStringContainsString('🚪 BotRemoved — ربات از چت حذف شد', $removal[0]);
        $this->assertStringContainsString('Chat ID: grp555', $removal[0]);
    }

    public function test_permissions_changed_reports_and_dedups_identical_changes(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $update = [
            'type' => 'BotPermissionsChanged',
            'chat_id' => 'grp666',
            'permissions' => ['SendMessages' => true],
        ];

        $this->postRubikaUpdate($update)->assertStatus(200);
        $this->assertCount(1, $this->sentTo((string) self::CHAT_ID));
        $first = (string) $this->sentTo((string) self::CHAT_ID)->first()->data()['text'];
        $this->assertStringContainsString('🔧 BotPermissionsChanged — تغییر صلاحیت ربات در چت', $first);
        $this->assertStringContainsString('Chat ID: grp666', $first);
        $this->assertStringContainsString('کلیدهای صلاحیت: sendmessages', $first);
        $this->assertStringContainsString('SendMessages: فعال', $first);
        $this->assertStringContainsString('Admin: خیر', $first);

        // The EXACT SAME change again → deduplicated, no second report.
        $this->postRubikaUpdate($update)->assertStatus(200);
        $this->assertCount(1, $this->sentTo((string) self::CHAT_ID));

        // A REAL change → new dedup suffix → reported again.
        $update2 = [
            'type' => 'BotPermissionsChanged',
            'chat_id' => 'grp666',
            'permissions' => ['SendMessages' => true, 'Admin' => true],
        ];
        $this->postRubikaUpdate($update2)->assertStatus(200);
        $this->assertCount(2, $this->sentTo((string) self::CHAT_ID));
        $second = (string) $this->sentTo((string) self::CHAT_ID)->last()->data()['text'];
        $this->assertStringContainsString('Admin: بله', $second);
    }

    public function test_unknown_event_reports_to_owner(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $this->postRubikaUpdate([
            'type' => 'SomeNewEvent',
            'chat_id' => 'grp777',
        ])->assertStatus(200);

        $this->assertCount(1, $this->sentTo((string) self::CHAT_ID));
        $text = (string) $this->sentTo((string) self::CHAT_ID)->first()->data()['text'];
        $this->assertStringContainsString('❓ رویداد ناشناخته از روبیکا', $text);
        $this->assertStringContainsString('نوع رویداد: SomeNewEvent', $text);
        $this->assertStringContainsString('Chat ID: grp777', $text);
        $this->assertStringContainsString('این رویداد در مستندات محلی تأیید نشده است (BLOCKED تا تست زنده).', $text);
    }

    // ------------------------------------------------------------------
    // duplicate update prevention
    // ------------------------------------------------------------------

    public function test_duplicate_update_is_processed_once(): void
    {
        $this->makeBot();
        $this->fakeApis();

        $update = $this->privateUpdate('dup');

        $this->postRubikaUpdate($update)->assertStatus(200);
        $this->postRubikaUpdate($update)->assertStatus(200);

        // Atomic Cache::add: second identical update is ignored.
        $this->assertCount(1, $this->sentTo('chatUser123'));
        $this->assertDatabaseCount('bot_logs', 1);
    }

    // ------------------------------------------------------------------
    // poll command (rubika:diagnostic-poll)
    // ------------------------------------------------------------------

    public function test_poll_command_fails_when_bot_missing(): void
    {
        $this->fakeApis();

        $this->artisan('rubika:diagnostic-poll', ['bot_id' => 999999])
            ->assertFailed()
            ->expectsOutputToContain('پیدا نشد');

        Http::assertNothingSent();
    }

    public function test_poll_command_fails_without_token(): void
    {
        $bot = $this->makeBot(['rubika_bot_token' => null]);
        $this->fakeApis();

        $this->artisan('rubika:diagnostic-poll', ['bot_id' => $bot->id])
            ->assertFailed()
            ->expectsOutputToContain('توکن روبیکا برای این ربات ثبت نشده است');

        Http::assertNothingSent();
    }

    public function test_poll_command_refuses_while_webhook_is_set(): void
    {
        $bot = $this->makeBot(['rubika_webhook_is_set' => true]);
        $this->fakeApis();

        // Polling and Webhook must never run simultaneously for one token
        // without an explicit check.
        $this->artisan('rubika:diagnostic-poll', ['bot_id' => $bot->id])
            ->assertFailed()
            ->expectsOutputToContain('Webhook برای این ربات فعال است')
            ->expectsOutputToContain('--force');

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/getUpdates'));
    }

    public function test_poll_command_lists_updates_and_stores_next_offset(): void
    {
        $bot = $this->makeBot();
        $this->fakeApis(getUpdates: fn () => Http::response([
            'updates' => [
                [
                    'type' => 'NewMessage',
                    'chat_id' => 'g1',
                    'new_message' => ['message_id' => '11', 'text' => 'first', 'sender_id' => 's1', 'sender_type' => 'User'],
                ],
                [
                    'type' => 'NewMessage',
                    'chat_id' => 'g2',
                    'new_message' => [
                        'message_id' => '22', 'text' => 'second', 'sender_id' => 's2', 'sender_type' => 'User',
                        'type_from' => 'User', 'from_chat_id' => 'orig1',
                    ],
                ],
            ],
            'next_offset_id' => 'offset_123',
        ]));

        $this->artisan('rubika:diagnostic-poll', ['bot_id' => $bot->id])
            ->assertSuccessful()
            ->expectsOutputToContain('آپدیت‌های دریافتی: 2')
            ->expectsOutputToContain('type=NewMessage chat_id=g1 message_id=11 sender=s1 text=first')
            ->expectsOutputToContain('فوروارد: orig1')
            ->expectsOutputToContain('next_offset_id ذخیره شد: offset_123')
            ->expectsOutputToContain('start_id در aux_data');

        // The documented next_offset_id contract (NOT start_id).
        $this->assertSame('offset_123', Cache::get('rubika_diag_poll_offset:' . $bot->id));

        // First call: no offset_id yet, default limit.
        $pair = Http::recorded()->first(fn ($p) => str_ends_with($p[0]->url(), '/getUpdates'));
        $this->assertNotNull($pair);
        $this->assertArrayNotHasKey('offset_id', $pair[0]->data());
        $this->assertSame(50, $pair[0]->data()['limit']);
    }

    public function test_poll_command_uses_cached_offset_on_next_run(): void
    {
        $bot = $this->makeBot();
        $captured = null;
        $this->fakeApis(getUpdates: function (Request $r) use (&$captured) {
            $captured = $r->data();
            return Http::response(['updates' => [], 'next_offset_id' => 'offset_456']);
        });
        Cache::put('rubika_diag_poll_offset:' . $bot->id, 'offset_123');

        $this->artisan('rubika:diagnostic-poll', ['bot_id' => $bot->id])
            ->assertSuccessful()
            ->expectsOutputToContain('آپدیت‌های دریافتی: 0');

        // Continues exactly where the previous run left off.
        $this->assertSame('offset_123', $captured['offset_id'] ?? null);
        // An empty result must NOT move the stored offset (no skip/loop).
        $this->assertSame('offset_123', Cache::get('rubika_diag_poll_offset:' . $bot->id));
    }

    public function test_poll_command_keeps_offset_when_next_offset_missing(): void
    {
        $bot = $this->makeBot();
        $this->fakeApis(getUpdates: fn () => Http::response([
            'updates' => [[
                'type' => 'NewMessage',
                'chat_id' => 'g9',
                'new_message' => ['message_id' => '9', 'text' => 'only', 'sender_id' => 's9', 'sender_type' => 'User'],
            ]],
        ]));

        $this->artisan('rubika:diagnostic-poll', ['bot_id' => $bot->id])
            ->assertSuccessful()
            ->expectsOutputToContain('آپدیت‌های دریافتی: 1')
            ->expectsOutputToContain('next_offset_id در پاسخ یافت نشد');

        $this->assertNull(Cache::get('rubika_diag_poll_offset:' . $bot->id));
    }

    public function test_poll_command_honors_explicit_offset_option(): void
    {
        $bot = $this->makeBot();
        $captured = null;
        $this->fakeApis(getUpdates: function (Request $r) use (&$captured) {
            $captured = $r->data();
            return Http::response(['updates' => [], 'next_offset_id' => 'x']);
        });

        $this->artisan('rubika:diagnostic-poll', [
            'bot_id' => $bot->id,
            '--offset' => 'manual_offset',
            '--limit' => 7,
        ])->assertSuccessful();

        $this->assertSame('manual_offset', $captured['offset_id'] ?? null);
        $this->assertSame(7, $captured['limit'] ?? null);
    }

    public function test_poll_command_force_overrides_webhook_conflict(): void
    {
        $bot = $this->makeBot(['rubika_webhook_is_set' => true]);
        $this->fakeApis(getUpdates: fn () => Http::response(['updates' => [], 'next_offset_id' => 'off9']));

        $this->artisan('rubika:diagnostic-poll', ['bot_id' => $bot->id, '--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('آپدیت‌های دریافتی: 0');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/getUpdates'));
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    /**
     * A NewMessage update for a private chat from the owner (admin chat).
     * Includes an inline chat object so getChat is not needed.
     */
    private function privateUpdate(string $text): array
    {
        return [
            'type' => 'NewMessage',
            'chat_id' => 'chatUser123',
            'chat' => ['id' => 'chatUser123', 'type' => 'User', 'username' => 'saber'],
            'new_message' => [
                'message_id' => '5001',
                'text' => $text,
                'time' => '1700000000',
                'sender_type' => 'User',
                'sender_id' => (string) self::CHAT_ID,
                'first_name' => 'Saber',
                'last_name' => 'Tabatabaei',
            ],
        ];
    }

    private function postRubikaUpdate(array $update): TestResponse
    {
        return $this->postJson(self::URL, ['update' => $update]);
    }

    /**
     * A rubika bot bound to the diagnostic endpoint.
     * rubika_bot_name is UNIQUE → uniqid suffix.
     */
    private function makeBot(array $overrides = []): Bot
    {
        return Bot::create(array_merge([
            'type' => 'rubika',
            'endpoint_id' => 'rubika-diagnostic',
            'bot_mother_id' => 1,
            'rubika_bot_token' => self::DIAG_TOKEN,
            'rubika_bot_name' => 'diagtestbot_' . uniqid(),
            'rubika_owner_chat_id' => self::CHAT_ID,
            'rubika_bot_status' => 'Active',
            'rubika_webhook_is_set' => false,
        ], $overrides));
    }

    /**
     * Mock the Rubika API (botapi.rubika.ir). Defaults: getChat → minimal
     * chat, sendMessage → success (message_id), getUpdates → empty.
     */
    private function fakeApis(?callable $getChat = null, ?callable $sendMessage = null, ?callable $getUpdates = null): void
    {
        Http::fake(function (Request $request) use ($getChat, $sendMessage, $getUpdates) {
            $url = $request->url();

            if (!str_contains($url, 'botapi.rubika.ir')) {
                return Http::response(['ok' => true]);
            }

            if (str_ends_with($url, '/getChat')) {
                return $getChat !== null
                    ? $getChat($request)
                    : Http::response(['chat' => ['id' => 'default', 'type' => 'User']]);
            }

            if (str_ends_with($url, '/sendMessage')) {
                return $sendMessage !== null
                    ? $sendMessage($request)
                    : Http::response(['message_id' => '999']);
            }

            if (str_ends_with($url, '/getUpdates')) {
                return $getUpdates !== null
                    ? $getUpdates($request)
                    : Http::response(['updates' => [], 'next_offset_id' => 'offset_default']);
            }

            return Http::response(['ok' => true]);
        });
    }

    /**
     * All recorded sendMessage API requests.
     *
     * @return Collection<int, Request>
     */
    private function sentMessages(): Collection
    {
        return Http::recorded()
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $r) => str_ends_with($r->url(), '/sendMessage'))
            ->values();
    }

    /**
     * Recorded sendMessage requests to a specific chat id.
     *
     * @return Collection<int, Request>
     */
    private function sentTo(string $chatId): Collection
    {
        return $this->sentMessages()->filter(
            fn (Request $r) => ($r->data()['chat_id'] ?? null) === $chatId
        )->values();
    }

    /**
     * Texts of all recorded owner notifications (single admin chat).
     *
     * @return string[]
     */
    private function ownerMessages(): array
    {
        return $this->sentTo((string) self::CHAT_ID)
            ->map(fn (Request $r) => (string) ($r->data()['text'] ?? ''))
            ->all();
    }

    /**
     * Set an env var via all three channels (putenv/$_ENV/$_SERVER),
     * remembering the previous value for tearDown.
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
