<?php

namespace Tests\Feature\BookLibrary;

use App\Interfaces\Services\BookLibraryDeliveryService;
use App\Interfaces\Services\BookLibraryPlanService;
use App\Interfaces\Services\BookLibraryService;
use App\Interfaces\Services\ContentDeliveryService;
use App\Interfaces\Services\ContentQueueService;
use App\Models\BotUsers;
use App\Models\ContentUserProgress;
use App\Services\BotAdminKieService;
use App\Services\ContentAdminService;
use Mockery;
use Tests\TestCase;
use Telegram;

/**
 * /sendmsg (ارسال پیام به کاربر خاص) و /topusers (گزارش کاربران برتر با
 * دکمه‌ی ارسال پیام) در ربات کتابخانه.
 *
 * Coverage:
 *  - /sendmsg alone (button from /help) must NOT say "command not found";
 *    admins get a format guide, non-admins get an access-denied message.
 *  - /sendmsg ID + message sends immediately to the target user.
 *  - /sendmsg ID (no message) starts a two-step wizard; the next text is
 *    delivered to the target and the wizard state is cleared.
 *  - /topusers renders user details (chat id, email, origin) plus a
 *    "send message" inline button per user (bl:admin:sendmsg:<id>).
 *  - Clicking the topusers button starts the same wizard; non-admins
 *    cannot use it.
 *
 * NOTE: the test database is the migrated `pardisa2_bot_platform_test`,
 * so instead of recreating the tables the tests use the real
 * `bot_users` / `content_user_progress` tables and only clean up the
 * rows they create (bot_users has FK children, drop+recreate fails).
 */
class TopUsersSendMsgTest extends TestCase
{
    private const ADMIN_CHAT = '900001';

    /** @var list<int> */
    private array $createdBotUserIds = [];

    /** @var list<int> */
    private array $createdProgressIds = [];

    /**
     * Chat ids touched by this test; the sweep also covers rows that
     * handleCallbackQuery auto-creates via BotUsers::firstOrNew().
     *
     * @var list<string>
     */
    private array $touchedChatIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        // AdminHelper::isAdmin() reads env() directly.
        $adminEnv = (string) self::ADMIN_CHAT;
        putenv('CHAT_ID_ACCOUNT_1_SABER=' . $adminEnv);
        $_ENV['CHAT_ID_ACCOUNT_1_SABER'] = $adminEnv;
        $_SERVER['CHAT_ID_ACCOUNT_1_SABER'] = $adminEnv;

        app()->setLocale('fa');

        $this->ensureContentUserProgressTable();
    }

    /**
     * The test database may predate the content tables migration
     * (2026_06_16_100000_create_content_tables.php). The table has no
     * foreign keys, so creating it on demand is safe and keeps the
     * test independent from the DB migration state.
     */
    private function ensureContentUserProgressTable(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('content_user_progress')) {
            return;
        }

        \Illuminate\Support\Facades\Schema::create('content_user_progress', function ($table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id')->index();
            $table->unsignedBigInteger('category_id')->index();
            $table->unsignedBigInteger('bot_id')->index();
            $table->unsignedInteger('last_position')->default(0);
            $table->unsignedBigInteger('last_content_item_id')->nullable();
            $table->timestamps();
            $table->unique(['bot_user_id', 'category_id']);
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->createdProgressIds as $progressId) {
            ContentUserProgress::whereKey($progressId)->delete();
        }
        foreach ($this->createdBotUserIds as $botUserId) {
            BotUsers::whereKey($botUserId)->delete();
        }
        if ($this->touchedChatIds !== []) {
            BotUsers::whereIn('chat_id', $this->touchedChatIds)->delete();
        }
        $this->createdBotUserIds = [];
        $this->createdProgressIds = [];
        $this->touchedChatIds = [];

        putenv('CHAT_ID_ACCOUNT_1_SABER');
        unset($_ENV['CHAT_ID_ACCOUNT_1_SABER'], $_SERVER['CHAT_ID_ACCOUNT_1_SABER']);

        Mockery::close();
        parent::tearDown();
    }

    private function makeBotUser(array $attrs): BotUsers
    {
        $user = BotUsers::create(array_merge([
            'bot_id' => 1,
            'origin' => 'bale',
            'status' => 'active',
        ], $attrs));
        $this->createdBotUserIds[] = $user->id;
        $this->touchedChatIds[] = (string) $user->chat_id;

        return $user;
    }

    private function makeProgress(int $botUserId, int $lastPosition, int $categoryId = 1, int $botId = 5): ContentUserProgress
    {
        $row = ContentUserProgress::create([
            'bot_user_id' => $botUserId,
            'category_id' => $categoryId,
            'bot_id' => $botId,
            'last_position' => $lastPosition,
        ]);
        $this->createdProgressIds[] = $row->id;

        return $row;
    }

    private function makeController(): \App\Http\Controllers\BookLibraryReaderController
    {
        $adminKie = Mockery::mock(BotAdminKieService::class);
        $adminKie->shouldReceive('isAdminkieCommand')->andReturn(false);

        return new \App\Http\Controllers\BookLibraryReaderController(
            Mockery::mock(BookLibraryService::class),
            Mockery::mock(BookLibraryDeliveryService::class),
            $adminKie,
            Mockery::mock(ContentQueueService::class),
            Mockery::mock(ContentDeliveryService::class),
            Mockery::mock(BookLibraryPlanService::class),
            Mockery::mock(ContentAdminService::class),
        );
    }

    /**
     * Messenger mock that records every sendMessage() payload.
     */
    private function makeMessenger(string $chatId, array &$sent): Telegram
    {
        $messenger = Mockery::mock(Telegram::class);
        $messenger->shouldReceive('ChatID')->andReturn($chatId);
        $messenger->shouldReceive('sendMessage')->andReturnUsing(function (array $content) use (&$sent) {
            $sent[] = $content;
            return [];
        });
        $messenger->shouldReceive('buildInlineKeyBoardButton')->andReturnUsing(function ($text, $url = '', $callback = '') {
            return ['text' => $text, 'callback_data' => $callback];
        });
        $messenger->shouldReceive('buildInlineKeyBoard')->andReturn('KEYBOARD');
        $messenger->shouldReceive('answerCallbackQuery')->andReturn(true);

        return $messenger;
    }

    private function invokeText(\App\Http\Controllers\BookLibraryReaderController $controller, Telegram $messenger, string $text, BotUsers $user, string $type = 'bale', int $instanceBotId = 5): void
    {
        $this->touchedChatIds[] = (string) $user->chat_id;
        $method = new \ReflectionMethod($controller, 'handleTextMessage');
        $method->setAccessible(true);
        $method->invoke($controller, $messenger, $text, $user, (int) $user->chat_id, $type, 1, $instanceBotId, [], new \Illuminate\Http\Request());
    }

    private function invokeCallback(\App\Http\Controllers\BookLibraryReaderController $controller, Telegram $messenger, string $callbackData, BotUsers $user, string $type = 'bale'): void
    {
        // handleCallbackQuery() may auto-create a bot user via firstOrNew,
        // so track the chat id for cleanup.
        $this->touchedChatIds[] = (string) $user->chat_id;
        $method = new \ReflectionMethod($controller, 'handleCallbackQuery');
        $method->setAccessible(true);
        $method->invoke($controller, $messenger, ['id' => 'cb1', 'data' => $callbackData], (int) $user->chat_id, $type, 1, 5);
    }

    public function test_sendmsg_without_args_shows_format_guide_to_admin_not_command_not_found(): void
    {
        $admin = $this->makeBotUser(['chat_id' => self::ADMIN_CHAT]);

        $sent = [];
        $messenger = $this->makeMessenger(self::ADMIN_CHAT, $sent);
        $this->invokeText($this->makeController(), $messenger, '/sendmsg', $admin);

        $this->assertCount(1, $sent);
        $this->assertStringContainsString('فرمت ارسال پیام به کاربر خاص', $sent[0]['text']);
        $this->assertStringContainsString('/sendmsg USER_ID متن پیام', $sent[0]['text']);
        $this->assertStringNotContainsString('دستور نامشخص', $sent[0]['text']);
    }

    public function test_sendmsg_denied_for_non_admin(): void
    {
        $nonAdmin = $this->makeBotUser(['chat_id' => '111111']);

        $sent = [];
        $messenger = $this->makeMessenger('111111', $sent);
        $this->invokeText($this->makeController(), $messenger, '/sendmsg 2 پیام', $nonAdmin);

        $this->assertCount(1, $sent);
        $this->assertStringContainsString('مجوز استفاده از این دستور را ندارید', $sent[0]['text']);
    }

    public function test_sendmsg_with_id_and_message_sends_to_target_user(): void
    {
        $admin = $this->makeBotUser(['chat_id' => self::ADMIN_CHAT]);
        $target = $this->makeBotUser(['chat_id' => '222222', 'alias_name' => 'کاربر هدف', 'email' => 'target@example.com']);

        $sent = [];
        $messenger = $this->makeMessenger(self::ADMIN_CHAT, $sent);
        $this->invokeText($this->makeController(), $messenger, "/sendmsg {$target->id} سلام، فایل جدید اضافه شد", $admin);

        // 1) the message to the target user, 2) the confirmation to the admin
        $this->assertCount(2, $sent);
        $this->assertSame('222222', (string) $sent[0]['chat_id']);
        $this->assertStringContainsString('پیام ادمین:', $sent[0]['text']);
        $this->assertStringContainsString('سلام، فایل جدید اضافه شد', $sent[0]['text']);
        $this->assertSame(self::ADMIN_CHAT, (string) $sent[1]['chat_id']);
        $this->assertStringContainsString('✅ پیام با موفقیت به کاربر', $sent[1]['text']);
    }

    public function test_sendmsg_with_unknown_user_id_reports_error(): void
    {
        $admin = $this->makeBotUser(['chat_id' => self::ADMIN_CHAT]);
        $unknownId = (int) BotUsers::max('id') + 1;

        $sent = [];
        $messenger = $this->makeMessenger(self::ADMIN_CHAT, $sent);
        $this->invokeText($this->makeController(), $messenger, "/sendmsg {$unknownId} پیام تست", $admin);

        $this->assertCount(1, $sent);
        $this->assertStringContainsString('یافت نشد', $sent[0]['text']);
    }

    public function test_sendmsg_with_id_only_starts_wizard_then_sends_next_text(): void
    {
        $admin = $this->makeBotUser(['chat_id' => self::ADMIN_CHAT]);
        $target = $this->makeBotUser(['chat_id' => '333333', 'alias_name' => 'علی']);

        $controller = $this->makeController();
        $sent = [];
        $messenger = $this->makeMessenger(self::ADMIN_CHAT, $sent);

        // Step 1: start the wizard
        $this->invokeText($controller, $messenger, "/sendmsg {$target->id}", $admin);
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('پیام بنویسید', $sent[0]['text']);
        $this->assertSame('sendmsg_user', $admin->fresh()->setting('content_wizard'));
        $this->assertSame($target->id, $admin->fresh()->setting('sendmsg_target_id'));

        // Step 2: the next text is delivered to the target
        $this->invokeText($controller, $messenger, 'متن پیام ادمین', $admin);
        $this->assertCount(3, $sent);
        $this->assertSame('333333', (string) $sent[1]['chat_id']);
        $this->assertStringContainsString('پیام ادمین:', $sent[1]['text']);
        $this->assertStringContainsString('متن پیام ادمین', $sent[1]['text']);
        $this->assertStringContainsString('✅ پیام با موفقیت به کاربر', $sent[2]['text']);

        // wizard state cleared
        $this->assertNull($admin->fresh()->setting('content_wizard'));
        $this->assertNull($admin->fresh()->setting('sendmsg_target_id'));
    }

    public function test_topusers_report_shows_user_details_and_send_message_button(): void
    {
        $admin = $this->makeBotUser(['chat_id' => self::ADMIN_CHAT]);
        $u1 = $this->makeBotUser(['chat_id' => '444441', 'alias_name' => 'مریم', 'email' => 'm@example.com']);
        $u2 = $this->makeBotUser(['chat_id' => '444442', 'origin' => 'telegram']);

        $this->makeProgress($u1->id, 7, 1);
        $this->makeProgress($u2->id, 3, 2);

        $sent = [];
        $messenger = $this->makeMessenger(self::ADMIN_CHAT, $sent);
        $this->invokeText($this->makeController(), $messenger, '/topusers', $admin);

        $this->assertCount(1, $sent);
        $report = $sent[0]['text'];
        $this->assertStringContainsString('لیست ۱۰ کاربر برتر', $report);
        $this->assertStringContainsString('مریم', $report);
        $this->assertStringContainsString('Chat ID: 444441', $report);
        $this->assertStringContainsString('m@example.com', $report);
        $this->assertStringContainsString('Chat ID: 444442', $report);

        // inline keyboard with a send-message button per user
        $this->assertSame('KEYBOARD', $sent[0]['reply_markup']);
    }

    public function test_topusers_denied_for_non_admin(): void
    {
        $nonAdmin = $this->makeBotUser(['chat_id' => '555555']);

        $sent = [];
        $messenger = $this->makeMessenger('555555', $sent);
        $this->invokeText($this->makeController(), $messenger, '/topusers', $nonAdmin);

        $this->assertCount(1, $sent);
        $this->assertStringContainsString('مجوز استفاده از این دستور را ندارید', $sent[0]['text']);
    }

    public function test_topusers_button_starts_sendmsg_wizard_for_admin(): void
    {
        $admin = $this->makeBotUser(['chat_id' => self::ADMIN_CHAT]);
        $target = $this->makeBotUser(['chat_id' => '666666', 'alias_name' => 'سارا', 'email' => 's@example.com']);

        $sent = [];
        $messenger = $this->makeMessenger(self::ADMIN_CHAT, $sent);
        $this->invokeCallback($this->makeController(), $messenger, "bl:admin:sendmsg:{$target->id}", $admin);

        $this->assertCount(1, $sent);
        $this->assertStringContainsString('سارا', $sent[0]['text']);
        $this->assertStringContainsString('Chat ID: 666666', $sent[0]['text']);
        $this->assertStringContainsString('s@example.com', $sent[0]['text']);
        $this->assertStringContainsString('حالا متن پیام خود را بنویسید', $sent[0]['text']);
        $this->assertSame('sendmsg_user', $admin->fresh()->setting('content_wizard'));
    }

    public function test_topusers_button_denied_for_non_admin(): void
    {
        $nonAdmin = $this->makeBotUser(['chat_id' => '777777']);
        $target = $this->makeBotUser(['chat_id' => '666667']);

        $sent = [];
        $messenger = $this->makeMessenger('777777', $sent);
        $this->invokeCallback($this->makeController(), $messenger, "bl:admin:sendmsg:{$target->id}", $nonAdmin);

        $this->assertCount(1, $sent);
        $this->assertStringContainsString('مجوز استفاده از این قابلیت را ندارید', $sent[0]['text']);
        $this->assertNull($nonAdmin->fresh()->setting('content_wizard'));
    }

    public function test_cancel_clears_sendmsg_wizard_state(): void
    {
        $admin = $this->makeBotUser(['chat_id' => self::ADMIN_CHAT]);
        $target = $this->makeBotUser(['chat_id' => '888888']);

        $sent = [];
        $messenger = $this->makeMessenger(self::ADMIN_CHAT, $sent);
        $controller = $this->makeController();

        $this->invokeText($controller, $messenger, "/sendmsg {$target->id}", $admin);
        $this->invokeText($controller, $messenger, '/cancel', $admin);

        $last = end($sent);
        $this->assertStringContainsString('لغو شد', $last['text']);
        $fresh = $admin->fresh();
        $this->assertNull($fresh->setting('content_wizard'));
        $this->assertNull($fresh->setting('sendmsg_target_id'));
    }
}
