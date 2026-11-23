<?php

namespace Tests\Feature\BookLibrary;

use App\Interfaces\Services\BookLibraryDeliveryService;
use App\Interfaces\Services\BookLibraryPlanService;
use App\Interfaces\Services\BookLibraryService;
use App\Interfaces\Services\ContentDeliveryService;
use App\Interfaces\Services\ContentQueueService;
use App\Jobs\NotifyNewCategoryJob;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentPendingUpload;
use App\Services\BotAdminKieService;
use App\Services\ContentAdminService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Telegram;

/**
 * AUDIOBOOK-BOT-ONBOARDING-UX-02 — TDD suite for the `add_category_broadcast`
 * state bug + 3-step onboarding (create first category, send first audio file).
 *
 * Root cause under test (discovery, mission 01):
 *  - After typing a category name, the wizard moves to the state
 *    `content_wizard = 'add_category_broadcast'` and waits for the
 *    `bl:catbc:yes|no` inline buttons.
 *  - `handleAdminWizardText()` had NO text case for that state, so any typed
 *    text fell through to the "unknown command" fallback while the wizard
 *    state stayed stuck. Re-running `/addcategory` restarted the same prompt:
 *    an infinite loop for new owners.
 *
 * This suite covers (Telegram AND Bale where applicable):
 *  - typed "بله"/"خیر" in the broadcast state must finalize the category
 *    (with / without the notify job);
 *  - unknown typed text must re-prompt with the buttons, not "unknown command";
 *  - `/cancel` must clear the category draft;
 *  - re-entering `/addcategory` must reset the wizard (no loop);
 *  - empty-library states (/start, /manage, upload) must guide the owner
 *    through the 3-step onboarding;
 *  - library ownership is per-bot (two bots in the same account stay isolated);
 *  - non-owners cannot manage; their uploads are forwarded to the owner.
 *
 * Harness notes: the test database is the migrated `pardisa2_bot_platform_test`
 * (same pattern as TopUsersSendMsgTest): real rows are created and cleaned up
 * in tearDown; content tables are created on demand if the DB predates the
 * content migration. The real ContentAdminService is used together with a
 * Mockery ContentQueueService that reads/writes the real DB, so createCategory /
 * storePendingUpload / notifyFileReceived behave exactly as in production.
 */
class AddCategoryOnboardingTest extends TestCase
{
    private const SUPER_ADMIN_CHAT = '900001';

    /** Onboarding guide markers shared by both controllers. */
    private const ONBOARDING_HEADER = '🚀 شروع سریع کتابخانه';
    private const ONBOARDING_STEP_1 = '۱️⃣ دسته بسازید';
    private const ONBOARDING_STEP_2 = '۲️⃣ فایل صوتی بفرستید';
    private const ONBOARDING_STEP_3 = '۳️⃣ انتساب';

    /** @var list<int> */
    private array $createdBotUserIds = [];

    /** @var list<int> */
    private array $createdBotIds = [];

    /** @var list<string> */
    private array $touchedChatIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // AdminHelper::isAdmin() reads env() directly.
        $adminEnv = self::SUPER_ADMIN_CHAT;
        putenv('CHAT_ID_ACCOUNT_1_SABER=' . $adminEnv);
        $_ENV['CHAT_ID_ACCOUNT_1_SABER'] = $adminEnv;
        $_SERVER['CHAT_ID_ACCOUNT_1_SABER'] = $adminEnv;

        // Keep the mother-admin notify path offline in tests.
        putenv('BOT_MOTHER_TOKEN_BALE');
        putenv('BOT_MOTHER_TOKEN_TELEGRAM');
        unset($_ENV['BOT_MOTHER_TOKEN_BALE'], $_SERVER['BOT_MOTHER_TOKEN_BALE']);
        unset($_ENV['BOT_MOTHER_TOKEN_TELEGRAM'], $_SERVER['BOT_MOTHER_TOKEN_TELEGRAM']);

        app()->setLocale('fa');
        Queue::fake();

        $this->ensureContentTables();

        // Self-heal: previous crashed runs can leave TestBot-* rows behind and
        // the bots table has UNIQUE telegram_bot_name/bale_bot_name columns.
        Bot::where('bale_bot_name', 'like', 'TestBot-%')
            ->orWhere('telegram_bot_name', 'like', 'TestBot-%')
            ->delete();
    }

    protected function tearDown(): void
    {
        ContentCategory::query()->delete();
        foreach (ContentItem::withTrashed()->pluck('id') as $itemId) {
            $item = ContentItem::withTrashed()->find($itemId);
            $item?->forceDelete();
        }
        ContentPendingUpload::query()->delete();

        foreach ($this->createdBotIds as $botId) {
            Bot::whereKey($botId)->delete();
        }
        foreach ($this->createdBotUserIds as $botUserId) {
            BotUsers::whereKey($botUserId)->delete();
        }
        if ($this->touchedChatIds !== []) {
            BotUsers::whereIn('chat_id', $this->touchedChatIds)->delete();
        }

        putenv('CHAT_ID_ACCOUNT_1_SABER');
        unset($_ENV['CHAT_ID_ACCOUNT_1_SABER'], $_SERVER['CHAT_ID_ACCOUNT_1_SABER']);

        Mockery::close();
        parent::tearDown();
    }

    /**
     * The test DB may predate the content tables migration; create them
     * on demand (no FKs involved) so the suite is independent from the
     * DB migration state.
     */
    private function ensureContentTables(): void
    {
        $needed = ['content_categories', 'content_items', 'content_pending_uploads', 'content_user_progress'];
        $missing = array_values(array_filter($needed, fn (string $t) => !Schema::hasTable($t)));
        if ($missing === []) {
            return;
        }

        if (!in_array('content_categories', $missing, true)) {
            Schema::create('content_categories', function ($table) {
                $table->id();
                $table->unsignedBigInteger('bot_id')->index();
                $table->string('title');
                $table->unsignedTinyInteger('page')->default(1);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (!in_array('content_items', $missing, true)) {
            Schema::create('content_items', function ($table) {
                $table->id();
                $table->unsignedBigInteger('bot_id')->index();
                $table->unsignedBigInteger('category_id')->index();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->unsignedInteger('queue_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!in_array('content_pending_uploads', $missing, true)) {
            Schema::create('content_pending_uploads', function ($table) {
                $table->id();
                $table->unsignedBigInteger('bot_id')->index();
                $table->text('file_id');
                $table->string('file_unique_id')->nullable();
                $table->string('origin', 20);
                $table->string('uploaded_by_chat_id');
                $table->text('title')->nullable();
                $table->string('mime_type')->nullable();
                $table->timestamps();
            });
        }
        if (!in_array('content_user_progress', $missing, true)) {
            Schema::create('content_user_progress', function ($table) {
                $table->id();
                $table->unsignedBigInteger('bot_user_id')->index();
                $table->unsignedBigInteger('category_id')->index();
                $table->unsignedBigInteger('bot_id')->index();
                $table->unsignedInteger('last_position')->default(0);
                $table->unsignedBigInteger('last_content_item_id')->nullable();
                $table->timestamps();
            });
        }
    }

    // ======================== FACTORIES ========================

    /**
     * Real bot row; the owner is identified per origin via the
     * *_owner_chat_id columns (ContentBotAdminHelper::isBotOwner).
     */
    private function makeBot(string $ownerChatId, string $origin): Bot
    {
        $bot = Bot::create([
            $origin === 'bale' ? 'bale_bot_token' : 'telegram_bot_token' => 'TEST-TOKEN-' . strtoupper($origin),
            // bots.bot_name columns are UNIQUE per origin; the run suffix keeps
            // re-runs collision-free even when a previous run crashed mid-test.
            $origin === 'bale' ? 'bale_bot_name' : 'telegram_bot_name' => 'TestBot-' . $ownerChatId . '-' . substr(uniqid(), -6),
            $origin === 'bale' ? 'bale_owner_chat_id' : 'telegram_owner_chat_id' => $ownerChatId,
        ]);
        $this->createdBotIds[] = $bot->id;

        return $bot;
    }

    private function makeBotUser(string $chatId, string $origin = 'bale', array $extra = []): BotUsers
    {
        $user = BotUsers::create(array_merge([
            'bot_id' => 1,
            'chat_id' => $chatId,
            'origin' => $origin,
            'status' => 'active',
        ], $extra));
        $this->createdBotUserIds[] = $user->id;
        $this->touchedChatIds[] = $chatId;

        return $user;
    }

    /**
     * Mockery ContentQueueService backed by the real DB (mirrors
     * ContentQueueServiceImpl behaviour for the methods under test).
     */
    private function makeQueueService(): ContentQueueService
    {
        $mock = Mockery::mock(ContentQueueService::class);
        $mock->shouldReceive('getCategoriesForPage')->andReturnUsing(function (int $botId, int $page) {
            $perPage = (int) config('content_bots.categories_per_page', 5);
            return ContentCategory::where('bot_id', $botId)
                ->where('page', $page)
                ->orderBy('sort_order')
                ->get();
        });
        $mock->shouldReceive('getMaxCategoryPage')->andReturnUsing(function (int $botId) {
            $max = (int) ContentCategory::where('bot_id', $botId)->max('page');

            return max(1, $max);
        });
        $mock->shouldReceive('findCategory')->andReturnUsing(function (int $categoryId, int $botId) {
            return ContentCategory::where('id', $categoryId)->where('bot_id', $botId)->first();
        });
        $mock->shouldReceive('appendPendingToCategory')->andReturnUsing(function (ContentPendingUpload $pending, int $categoryId, ?string $title = null) {
            $order = (int) ContentItem::where('category_id', $categoryId)->max('queue_order') + 1;
            $item = ContentItem::create([
                'bot_id' => $pending->bot_id,
                'category_id' => $categoryId,
                'title' => $title ?? $pending->title,
                'queue_order' => $order,
                'is_active' => true,
            ]);
            $pending->delete();

            return $item;
        });
        $mock->shouldReceive('getNextItemForUser')->andReturn(null);
        $mock->shouldReceive('advanceProgress')->andReturnUsing(fn (...$args) => null);
        $mock->shouldReceive('getProgress')->andReturn(0);
        $mock->shouldReceive('maxQueueOrder')->andReturn(0);
        $mock->shouldReceive('getUserProgressList')->andReturn(collect());
        $mock->shouldReceive('softDeleteItem')->andReturn(true);
        $mock->shouldReceive('updateItem')->andReturn(null);

        return $mock;
    }

    /**
     * Messenger mock recording every sendMessage() payload plus the
     * file-forwarding helpers used by the media path.
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
        $messenger->shouldReceive('buildKeyBoard')->andReturn('KEYBOARD');
        $messenger->shouldReceive('answerCallbackQuery')->andReturn(true);
        $messenger->shouldReceive('sendVoice')->andReturnUsing(function (array $content) use (&$sent) {
            $sent[] = $content + ['__method' => 'sendVoice'];

            return [];
        });
        $messenger->shouldReceive('sendAudio')->andReturnUsing(function (array $content) use (&$sent) {
            $sent[] = $content + ['__method' => 'sendAudio'];

            return [];
        });

        return $messenger;
    }

    private function makeReaderController(): \App\Http\Controllers\BookLibraryReaderController
    {
        $adminKie = Mockery::mock(BotAdminKieService::class);
        $adminKie->shouldReceive('isAdminkieCommand')->andReturn(false);

        return new \App\Http\Controllers\BookLibraryReaderController(
            Mockery::mock(BookLibraryService::class),
            Mockery::mock(BookLibraryDeliveryService::class),
            $adminKie,
            $this->makeQueueService(),
            Mockery::mock(ContentDeliveryService::class),
            Mockery::mock(BookLibraryPlanService::class),
            new ContentAdminService($this->makeQueueService()),
        );
    }

    private function makeBookLibraryController(): \App\Http\Controllers\BookLibraryController
    {
        return new \App\Http\Controllers\BookLibraryController(
            $this->makeQueueService(),
            Mockery::mock(ContentDeliveryService::class),
            Mockery::mock(BookLibraryService::class),
            Mockery::mock(BookLibraryPlanService::class),
            new ContentAdminService($this->makeQueueService()),
        );
    }

    private function invokeReaderText(\App\Http\Controllers\BookLibraryReaderController $c, Telegram $m, string $text, BotUsers $user, string $type = 'bale', int $instanceBotId = 0): void
    {
        $this->touchedChatIds[] = (string) $user->chat_id;
        $method = new \ReflectionMethod($c, 'handleTextMessage');
        $method->setAccessible(true);
        $method->invoke($c, $m, $text, $user, (int) $user->chat_id, $type, 1, $instanceBotId, [], new \Illuminate\Http\Request());
    }

    private function invokeBookLibraryText(\App\Http\Controllers\BookLibraryController $c, Telegram $m, string $text, BotUsers $user, string $type = 'bale', int $instanceBotId = 0): void
    {
        $this->touchedChatIds[] = (string) $user->chat_id;
        $method = new \ReflectionMethod($c, 'handleTextMessage');
        $method->setAccessible(true);
        $method->invoke($c, $m, $text, $user, (int) $user->chat_id, $type, 1, $instanceBotId, []);
    }

    private function invokeCallback(\App\Http\Controllers\Controller $c, Telegram $m, string $data, BotUsers $user, string $type = 'bale', int $instanceBotId = 0): void
    {
        $this->touchedChatIds[] = (string) $user->chat_id;
        $method = new \ReflectionMethod($c, 'handleCallbackQuery');
        $method->setAccessible(true);
        $method->invoke($c, $m, ['id' => 'cb1', 'data' => $data], (int) $user->chat_id, $type, 1, $instanceBotId);
    }

    private function invokeReaderMedia(\App\Http\Controllers\BookLibraryReaderController $c, Telegram $m, BotUsers $user, string $type, int $instanceBotId, string $fileId): void
    {
        $this->touchedChatIds[] = (string) $user->chat_id;
        $method = new \ReflectionMethod($c, 'handleMediaUpload');
        $method->setAccessible(true);
        $method->invoke($c, $m, ['message' => ['voice' => ['file_id' => $fileId, 'file_unique_id' => uniqid('f')]]], $user, $instanceBotId, $type, (int) $user->chat_id);
    }

    /** Convenience: enter the add_category wizard and type the name. */
    private function startCategoryWizard(\App\Http\Controllers\BookLibraryReaderController $c, Telegram $m, BotUsers $owner, int $botId): void
    {
        $this->invokeReaderText($c, $m, '/addcategory', $owner, 'bale', $botId);
        $this->invokeReaderText($c, $m, 'قربانی‌ها', $owner, 'bale', $botId);
    }

    // ======================== R1: WIZARD HAPPY PATH (regression) ========================

    public function test_reader_category_wizard_button_path_creates_category_and_dispatches_notify(): void
    {
        $owner = $this->makeBotUser('800001');
        $bot = $this->makeBot('800001', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800001', $sent);

        $this->invokeReaderText($c, $m, '/addcategory', $owner, 'bale', $bot->id);
        $this->assertSame('add_category_name', $owner->fresh()->setting('content_wizard'));
        $this->invokeReaderText($c, $m, 'قربانی‌ها', $owner, 'bale', $bot->id);
        $this->assertSame('add_category_broadcast', $owner->fresh()->setting('content_wizard'));
        $this->assertSame('قربانی‌ها', $owner->fresh()->setting('content_category_draft'));

        $this->invokeCallback($c, $m, 'bl:catbc:yes', $owner, 'bale', $bot->id);

        $fresh = $owner->fresh();
        $this->assertNull($fresh->setting('content_wizard'));
        $this->assertNull($fresh->setting('content_category_draft'));
        $category = ContentCategory::where('bot_id', $bot->id)->where('title', 'قربانی‌ها')->first();
        $this->assertNotNull($category);
        Queue::assertPushed(NotifyNewCategoryJob::class);
        $allText = implode("\n", array_map(fn ($s) => $s['text'] ?? '', $sent));
        $this->assertStringContainsString('قربانی‌ها', $allText);
        $this->assertStringContainsString('ساخته شد', $allText);
    }

    // ======================== R2: TYPED "بله" IN BROADCAST STATE (RED) ========================

    public function test_reader_typed_bale_in_broadcast_state_creates_category_with_notify(): void
    {
        $owner = $this->makeBotUser('800002');
        $bot = $this->makeBot('800002', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800002', $sent);
        $this->startCategoryWizard($c, $m, $owner, $bot->id);

        $this->invokeReaderText($c, $m, 'بله', $owner, 'bale', $bot->id);

        $fresh = $owner->fresh();
        $this->assertNull($fresh->setting('content_wizard'), 'typed بله must finalize the wizard');
        $this->assertNull($fresh->setting('content_category_draft'));
        $this->assertNotNull(ContentCategory::where('bot_id', $bot->id)->where('title', 'قربانی‌ها')->first());
        Queue::assertPushed(NotifyNewCategoryJob::class);
        $last = $sent[count($sent) - 1]['text'];
        $this->assertStringContainsString('قربانی‌ها', $last);
        $this->assertStringContainsString('اطلاع‌رسانی', $last);
        $this->assertStringContainsString('اولین فایل صوتی', $last);
    }

    public function test_reader_typed_kheyr_in_broadcast_state_creates_category_without_notify(): void
    {
        $owner = $this->makeBotUser('800003');
        $bot = $this->makeBot('800003', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800003', $sent);
        $this->startCategoryWizard($c, $m, $owner, $bot->id);

        $this->invokeReaderText($c, $m, 'خیر', $owner, 'bale', $bot->id);

        $fresh = $owner->fresh();
        $this->assertNull($fresh->setting('content_wizard'));
        $this->assertNotNull(ContentCategory::where('bot_id', $bot->id)->where('title', 'قربانی‌ها')->first());
        Queue::assertNotPushed(NotifyNewCategoryJob::class);
        $last = $sent[count($sent) - 1]['text'];
        $this->assertStringContainsString('قربانی‌ها', $last);
        $this->assertStringNotContainsString('اطلاع‌رسانی', $last);
    }

    // ======================== R3: UNKNOWN TEXT MUST RE-PROMPT (RED) ========================

    public function test_reader_unknown_text_in_broadcast_state_re_prompts_with_buttons_not_unknown_command(): void
    {
        $owner = $this->makeBotUser('800004');
        $bot = $this->makeBot('800004', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800004', $sent);
        $this->startCategoryWizard($c, $m, $owner, $bot->id);

        $this->invokeReaderText($c, $m, 'سلام', $owner, 'bale', $bot->id);

        $fresh = $owner->fresh();
        $this->assertSame('add_category_broadcast', $fresh->setting('content_wizard'), 'state must stay in broadcast step');
        $this->assertSame('قربانی‌ها', $fresh->setting('content_category_draft'));
        $this->assertCount(0, ContentCategory::where('bot_id', $bot->id)->get());
        $last = $sent[count($sent) - 1];
        $this->assertSame('KEYBOARD', $last['reply_markup'] ?? null, 're-prompt must include the yes/no buttons');
        $this->assertStringContainsString('بله', $last['text']);
        $this->assertStringContainsString('خیر', $last['text']);
        $this->assertStringContainsString('/cancel', $last['text']);
        $this->assertStringNotContainsString('دستور نامشخص', $last['text']);
    }

    // ======================== R4: /addcategory LOOP GUARD (RED) ========================

    public function test_reader_reentering_addcategory_in_broadcast_state_resets_wizard_without_creating(): void
    {
        $owner = $this->makeBotUser('800005');
        $bot = $this->makeBot('800005', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800005', $sent);
        $this->startCategoryWizard($c, $m, $owner, $bot->id);

        $this->invokeReaderText($c, $m, '/addcategory', $owner, 'bale', $bot->id);

        $fresh = $owner->fresh();
        $this->assertSame('add_category_name', $fresh->setting('content_wizard'));
        $this->assertNull($fresh->setting('content_category_draft'), 'old draft must be cleared on re-entry');
        $this->assertCount(0, ContentCategory::where('bot_id', $bot->id)->get());
        $last = $sent[count($sent) - 1]['text'];
        $this->assertStringContainsString('/cancel', $last);
        $this->assertStringNotContainsString('ساخته شود؟', $last);
    }

    // ======================== R5: /cancel CLEARS DRAFT (RED) ========================

    public function test_reader_cancel_clears_category_draft_and_wizard(): void
    {
        $owner = $this->makeBotUser('800006');
        $bot = $this->makeBot('800006', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800006', $sent);
        $this->startCategoryWizard($c, $m, $owner, $bot->id);

        $this->invokeReaderText($c, $m, '/cancel', $owner, 'bale', $bot->id);

        $fresh = $owner->fresh();
        $this->assertNull($fresh->setting('content_wizard'));
        $this->assertNull($fresh->setting('content_category_draft'));
        $this->assertCount(0, ContentCategory::where('bot_id', $bot->id)->get());
        $this->assertStringContainsString('لغو', $sent[count($sent) - 1]['text']);
    }

    // ======================== R6: NON-OWNER CANNOT MANAGE (regression) ========================

    public function test_reader_addcategory_denied_for_non_owner(): void
    {
        $owner = $this->makeBotUser('800007');
        $stranger = $this->makeBotUser('800008');
        $bot = $this->makeBot('800007', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800008', $sent);
        $this->invokeReaderText($c, $m, '/addcategory', $stranger, 'bale', $bot->id);

        $this->assertNull($stranger->fresh()->setting('content_wizard'));
        $this->assertStringContainsString('دستور نامشخص', $sent[0]['text']);
    }

    // ======================== R7: ONBOARDING — /start EMPTY LIBRARY (RED) ========================

    public function test_reader_start_empty_library_shows_three_step_onboarding_to_owner(): void
    {
        $owner = $this->makeBotUser('800009');
        $bot = $this->makeBot('800009', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800009', $sent);
        $this->invokeReaderText($c, $m, '/start', $owner, 'bale', $bot->id);

        $last = $sent[count($sent) - 1]['text'];
        $this->assertStringContainsString(self::ONBOARDING_HEADER, $last);
        $this->assertStringContainsString(self::ONBOARDING_STEP_1, $last);
        $this->assertStringContainsString(self::ONBOARDING_STEP_2, $last);
        $this->assertStringContainsString(self::ONBOARDING_STEP_3, $last);
        $this->assertStringContainsString('/addcategory', $last);
    }

    public function test_reader_start_empty_library_shows_friendly_message_to_non_owner(): void
    {
        $owner = $this->makeBotUser('800010');
        $reader = $this->makeBotUser('800011');
        $bot = $this->makeBot('800010', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800011', $sent);
        $this->invokeReaderText($c, $m, '/start', $reader, 'bale', $bot->id);

        $last = $sent[count($sent) - 1]['text'];
        $this->assertStringContainsString('هنوز محتوایی موجود نیست', $last);
        $this->assertStringNotContainsString('/addcategory', $last);
        $this->assertStringNotContainsString(self::ONBOARDING_HEADER, $last);
    }

    // ======================== R8: ONBOARDING — /manage EMPTY LIBRARY (RED) ========================

    public function test_reader_manage_empty_library_includes_quick_start_steps(): void
    {
        $owner = $this->makeBotUser('800012');
        $bot = $this->makeBot('800012', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800012', $sent);
        $this->invokeReaderText($c, $m, '/manage', $owner, 'bale', $bot->id);

        $last = $sent[count($sent) - 1];
        $this->assertStringContainsString('🛠 پنل مدیریت', $last['text']);
        $this->assertStringContainsString(self::ONBOARDING_HEADER, $last['text']);
        $this->assertStringContainsString('/addcategory', $last['text']);
        $this->assertSame('KEYBOARD', $last['reply_markup'] ?? null);
    }

    // ======================== R9: UPLOAD ONBOARDING (RED) ========================

    public function test_reader_owner_upload_empty_library_guides_owner_through_steps(): void
    {
        $owner = $this->makeBotUser('800013');
        $bot = $this->makeBot('800013', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800013', $sent);
        $this->invokeReaderMedia($c, $m, $owner, 'bale', $bot->id, 'voice-file-1');

        $pending = ContentPendingUpload::where('bot_id', $bot->id)->first();
        $this->assertNotNull($pending, 'file must be stored in the pending queue');
        $last = $sent[count($sent) - 1]['text'];
        $this->assertStringContainsString((string) $pending->id, $last);
        $this->assertStringContainsString('/addcategory', $last);
        $this->assertStringContainsString('/addFileToCategory_' . $pending->id, $last);
    }

    public function test_reader_owner_upload_with_categories_shows_picker(): void
    {
        $owner = $this->makeBotUser('800014');
        $bot = $this->makeBot('800014', 'bale');
        $cat = ContentCategory::create(['bot_id' => $bot->id, 'title' => 'قرآن', 'page' => 1, 'sort_order' => 1, 'is_active' => true]);
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800014', $sent);
        $this->invokeReaderMedia($c, $m, $owner, 'bale', $bot->id, 'voice-file-2');

        $pending = ContentPendingUpload::where('bot_id', $bot->id)->first();
        $this->assertNotNull($pending);
        $last = $sent[count($sent) - 1];
        $this->assertSame('KEYBOARD', $last['reply_markup'] ?? null);
        $this->assertStringContainsString('قرآن', $last['text']);
    }

    public function test_reader_non_owner_upload_forwards_to_owner_with_receipt(): void
    {
        $owner = $this->makeBotUser('800015');
        $user = $this->makeBotUser('800016');
        $bot = $this->makeBot('800015', 'bale');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800016', $sent);
        $this->invokeReaderMedia($c, $m, $user, 'bale', $bot->id, 'voice-file-3');

        $pending = ContentPendingUpload::where('bot_id', $bot->id)->first();
        $this->assertNotNull($pending);
        $this->assertSame('800016', (string) $pending->uploaded_by_chat_id);
        $receipt = $sent[0]['text'] ?? '';
        $this->assertStringContainsString((string) $pending->id, $receipt);
        $forwarded = array_values(array_filter($sent, fn ($s) => ($s['__method'] ?? null) === 'sendVoice'));
        $this->assertCount(1, $forwarded, 'file must be forwarded to the owner chat');
        $this->assertSame('800015', (string) $forwarded[0]['chat_id']);
    }

    // ======================== R10: TELEGRAM PARITY (RED) ========================

    public function test_telegram_typed_bale_in_broadcast_state_creates_category_with_notify(): void
    {
        $owner = $this->makeBotUser('800017', 'telegram');
        $bot = $this->makeBot('800017', 'telegram');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800017', $sent);
        $this->invokeReaderText($c, $m, '/addcategory', $owner, 'telegram', $bot->id);
        $this->invokeReaderText($c, $m, 'قربانی‌ها', $owner, 'telegram', $bot->id);
        $this->invokeReaderText($c, $m, 'بله', $owner, 'telegram', $bot->id);

        $fresh = $owner->fresh();
        $this->assertNull($fresh->setting('content_wizard'));
        $category = ContentCategory::where('bot_id', $bot->id)->where('title', 'قربانی‌ها')->first();
        $this->assertNotNull($category);
        Queue::assertPushed(NotifyNewCategoryJob::class);
        $last = $sent[count($sent) - 1]['text'];
        $this->assertStringContainsString('قربانی‌ها', $last);
        $this->assertStringContainsString('اطلاع‌رسانی', $last);
        $this->assertStringContainsString('اولین فایل صوتی', $last);
    }

    public function test_telegram_button_path_creates_category(): void
    {
        $owner = $this->makeBotUser('800018', 'telegram');
        $bot = $this->makeBot('800018', 'telegram');
        $c = $this->makeReaderController();

        $sent = [];
        $m = $this->makeMessenger('800018', $sent);
        $this->invokeReaderText($c, $m, '/addcategory', $owner, 'telegram', $bot->id);
        $this->invokeReaderText($c, $m, 'قربانی‌ها', $owner, 'telegram', $bot->id);
        $this->invokeCallback($c, $m, 'bl:catbc:no', $owner, 'telegram', $bot->id);

        $this->assertNull($owner->fresh()->setting('content_wizard'));
        $this->assertNotNull(ContentCategory::where('bot_id', $bot->id)->where('title', 'قربانی‌ها')->first());
        Queue::assertNotPushed(NotifyNewCategoryJob::class);
    }

    // ======================== R11: CROSS-BOT ISOLATION ========================

    public function test_category_creation_is_isolated_between_bots_of_same_account(): void
    {
        $ownerA = $this->makeBotUser('800019');
        $ownerB = $this->makeBotUser('800020');
        $botA = $this->makeBot('800019', 'bale');
        $botB = $this->makeBot('800020', 'bale');
        $c = $this->makeReaderController();

        $sentA = [];
        $mA = $this->makeMessenger('800019', $sentA);
        $this->invokeReaderText($c, $mA, '/addcategory', $ownerA, 'bale', $botA->id);
        $this->invokeReaderText($c, $mA, 'دسته الف', $ownerA, 'bale', $botA->id);
        $this->invokeCallback($c, $mA, 'bl:catbc:no', $ownerA, 'bale', $botA->id);

        $sentB = [];
        $mB = $this->makeMessenger('800020', $sentB);
        $this->invokeReaderText($c, $mB, '/addcategory', $ownerB, 'bale', $botB->id);
        $this->invokeReaderText($c, $mB, 'دسته ب', $ownerB, 'bale', $botB->id);
        $this->invokeCallback($c, $mB, 'bl:catbc:no', $ownerB, 'bale', $botB->id);

        $this->assertSame(1, ContentCategory::where('bot_id', $botA->id)->count());
        $this->assertSame(1, ContentCategory::where('bot_id', $botB->id)->count());
        $this->assertSame('دسته الف', ContentCategory::where('bot_id', $botA->id)->value('title'));
        $this->assertSame('دسته ب', ContentCategory::where('bot_id', $botB->id)->value('title'));
        // Bot A's wizard state must be untouched by Bot B's flow.
        $this->assertNull($ownerA->fresh()->setting('content_wizard'));
        $this->assertNull($ownerB->fresh()->setting('content_wizard'));
    }

    public function test_second_category_lands_on_next_page(): void
    {
        $owner = $this->makeBotUser('800021');
        $bot = $this->makeBot('800021', 'bale');
        $c = $this->makeReaderController();

        // 4 فیلر در صفحه ۱ تا دسته‌ی ۵ و ۶ (خروجی ویزارد) مرز صفحه را باز کنند (categories_per_page = 5)
        foreach ([1, 2, 3, 4] as $i) {
            ContentCategory::create(['bot_id' => $bot->id, 'title' => 'پرکننده ' . $i, 'page' => 1, 'sort_order' => $i, 'is_active' => true]);
        }

        $sent = [];
        $m = $this->makeMessenger('800021', $sent);
        $this->invokeReaderText($c, $m, '/addcategory', $owner, 'bale', $bot->id);
        $this->invokeReaderText($c, $m, 'اولین', $owner, 'bale', $bot->id);
        $this->invokeCallback($c, $m, 'bl:catbc:no', $owner, 'bale', $bot->id);

        $this->invokeReaderText($c, $m, '/addcategory', $owner, 'bale', $bot->id);
        $this->invokeReaderText($c, $m, 'دومین', $owner, 'bale', $bot->id);
        $this->invokeCallback($c, $m, 'bl:catbc:no', $owner, 'bale', $bot->id);

        $first = ContentCategory::where('bot_id', $bot->id)->where('title', 'اولین')->first();
        $second = ContentCategory::where('bot_id', $bot->id)->where('title', 'دومین')->first();
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame(1, $first->page);
        $this->assertSame(2, $second->page, 'the 6th category must open a new page');
    }

    // ======================== R12: BOOKLIBRARY CONTROLLER MIRRORS ========================

    public function test_booklibrary_typed_bale_in_broadcast_state_creates_category_with_notify(): void
    {
        $owner = $this->makeBotUser('800022');
        $bot = $this->makeBot('800022', 'bale');
        $c = $this->makeBookLibraryController();

        $sent = [];
        $m = $this->makeMessenger('800022', $sent);
        $this->invokeBookLibraryText($c, $m, '/addcategory', $owner, 'bale', $bot->id);
        $this->invokeBookLibraryText($c, $m, 'قربانی‌ها', $owner, 'bale', $bot->id);
        $this->invokeBookLibraryText($c, $m, 'بله', $owner, 'bale', $bot->id);

        $fresh = $owner->fresh();
        $this->assertNull($fresh->setting('content_wizard'), 'typed بله must finalize the wizard');
        $this->assertNull($fresh->setting('content_category_draft'));
        $this->assertNotNull(ContentCategory::where('bot_id', $bot->id)->where('title', 'قربانی‌ها')->first());
        Queue::assertPushed(NotifyNewCategoryJob::class);
        $last = $sent[count($sent) - 1]['text'];
        $this->assertStringContainsString('قربانی‌ها', $last);
        $this->assertStringContainsString('اطلاع‌رسانی', $last);
        $this->assertStringContainsString('اولین فایل صوتی', $last);
    }

    public function test_booklibrary_unknown_text_in_broadcast_state_re_prompts_not_use_menu(): void
    {
        $owner = $this->makeBotUser('800023');
        $bot = $this->makeBot('800023', 'bale');
        $c = $this->makeBookLibraryController();

        $sent = [];
        $m = $this->makeMessenger('800023', $sent);
        $this->invokeBookLibraryText($c, $m, '/addcategory', $owner, 'bale', $bot->id);
        $this->invokeBookLibraryText($c, $m, 'قربانی‌ها', $owner, 'bale', $bot->id);

        $this->invokeBookLibraryText($c, $m, 'سلام', $owner, 'bale', $bot->id);

        $fresh = $owner->fresh();
        $this->assertSame('add_category_broadcast', $fresh->setting('content_wizard'));
        $this->assertSame('قربانی‌ها', $fresh->setting('content_category_draft'));
        $this->assertCount(0, ContentCategory::where('bot_id', $bot->id)->get());
        $last = $sent[count($sent) - 1];
        $this->assertSame('KEYBOARD', $last['reply_markup'] ?? null);
        $this->assertStringContainsString('بله', $last['text']);
        $this->assertStringContainsString('خیر', $last['text']);
        $this->assertStringContainsString('/cancel', $last['text']);
        $this->assertStringNotContainsString(trans('book_library.use_menu'), $last['text']);
    }

    public function test_booklibrary_button_path_creates_category_without_notify(): void
    {
        $owner = $this->makeBotUser('800024');
        $bot = $this->makeBot('800024', 'bale');
        $c = $this->makeBookLibraryController();

        $sent = [];
        $m = $this->makeMessenger('800024', $sent);
        $this->invokeBookLibraryText($c, $m, '/addcategory', $owner, 'bale', $bot->id);
        $this->invokeBookLibraryText($c, $m, 'قربانی‌ها', $owner, 'bale', $bot->id);
        $this->invokeCallback($c, $m, 'bl:catbc:no', $owner, 'bale', $bot->id);

        $fresh = $owner->fresh();
        $this->assertNull($fresh->setting('content_wizard'));
        $this->assertNull($fresh->setting('content_category_draft'));
        $this->assertNotNull(ContentCategory::where('bot_id', $bot->id)->where('title', 'قربانی‌ها')->first());
        Queue::assertNotPushed(NotifyNewCategoryJob::class);
        $last = $sent[count($sent) - 1]['text'];
        $this->assertStringContainsString('قربانی‌ها', $last);
        $this->assertStringNotContainsString('اطلاع‌رسانی', $last);
        $this->assertStringContainsString('اولین فایل صوتی', $last);
    }

    public function test_booklibrary_cancel_clears_category_draft_and_wizard(): void
    {
        $owner = $this->makeBotUser('800025');
        $bot = $this->makeBot('800025', 'bale');
        $c = $this->makeBookLibraryController();

        $sent = [];
        $m = $this->makeMessenger('800025', $sent);
        $this->invokeBookLibraryText($c, $m, '/addcategory', $owner, 'bale', $bot->id);
        $this->invokeBookLibraryText($c, $m, 'قربانی‌ها', $owner, 'bale', $bot->id);

        $this->invokeBookLibraryText($c, $m, '/cancel', $owner, 'bale', $bot->id);

        $fresh = $owner->fresh();
        $this->assertNull($fresh->setting('content_wizard'));
        $this->assertNull($fresh->setting('content_category_draft'));
        $this->assertCount(0, ContentCategory::where('bot_id', $bot->id)->get());
        $this->assertStringContainsString('لغو', $sent[count($sent) - 1]['text']);
    }

    public function test_booklibrary_reentering_addcategory_in_broadcast_state_resets_wizard(): void
    {
        $owner = $this->makeBotUser('800026');
        $bot = $this->makeBot('800026', 'bale');
        $c = $this->makeBookLibraryController();

        $sent = [];
        $m = $this->makeMessenger('800026', $sent);
        $this->invokeBookLibraryText($c, $m, '/addcategory', $owner, 'bale', $bot->id);
        $this->invokeBookLibraryText($c, $m, 'قربانی‌ها', $owner, 'bale', $bot->id);

        $this->invokeBookLibraryText($c, $m, '/addcategory', $owner, 'bale', $bot->id);

        $fresh = $owner->fresh();
        $this->assertSame('add_category_name', $fresh->setting('content_wizard'));
        $this->assertNull($fresh->setting('content_category_draft'));
        $this->assertCount(0, ContentCategory::where('bot_id', $bot->id)->get());
        $this->assertStringContainsString('/cancel', $sent[count($sent) - 1]['text']);
    }

    public function test_booklibrary_start_empty_library_shows_onboarding_to_owner(): void
    {
        $owner = $this->makeBotUser('800027');
        $bot = $this->makeBot('800027', 'bale');
        $c = $this->makeBookLibraryController();

        $sent = [];
        $m = $this->makeMessenger('800027', $sent);
        $this->invokeBookLibraryText($c, $m, '/start', $owner, 'bale', $bot->id);

        $last = $sent[count($sent) - 1]['text'];
        $this->assertStringContainsString(self::ONBOARDING_HEADER, $last);
        $this->assertStringContainsString(self::ONBOARDING_STEP_1, $last);
        $this->assertStringContainsString(self::ONBOARDING_STEP_2, $last);
        $this->assertStringContainsString('/addcategory', $last);
    }

    public function test_booklibrary_start_empty_library_friendly_to_non_owner(): void
    {
        $owner = $this->makeBotUser('800028');
        $reader = $this->makeBotUser('800029');
        $bot = $this->makeBot('800028', 'bale');
        $c = $this->makeBookLibraryController();

        $sent = [];
        $m = $this->makeMessenger('800029', $sent);
        $this->invokeBookLibraryText($c, $m, '/start', $reader, 'bale', $bot->id);

        $last = $sent[count($sent) - 1]['text'];
        $this->assertStringContainsString('هنوز محتوایی موجود نیست', $last);
        $this->assertStringNotContainsString('/addcategory', $last);
        $this->assertStringNotContainsString(self::ONBOARDING_HEADER, $last);
    }

    public function test_booklibrary_addcategory_denied_for_non_owner(): void
    {
        $owner = $this->makeBotUser('800030');
        $stranger = $this->makeBotUser('800031');
        $bot = $this->makeBot('800030', 'bale');
        $c = $this->makeBookLibraryController();

        $sent = [];
        $m = $this->makeMessenger('800031', $sent);
        $this->invokeBookLibraryText($c, $m, '/addcategory', $stranger, 'bale', $bot->id);

        $this->assertNull($stranger->fresh()->setting('content_wizard'));
        $this->assertSame(trans('book_library.use_menu'), $sent[0]['text']);
    }
}