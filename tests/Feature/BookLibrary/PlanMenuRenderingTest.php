<?php

namespace Tests\Feature\BookLibrary;

use App\Http\Controllers\BookLibraryController;
use App\Interfaces\Services\BookLibraryPlanService;
use App\Interfaces\Services\BookLibraryService;
use App\Interfaces\Services\ContentDeliveryService;
use App\Interfaces\Services\ContentQueueService;
use App\Models\BotUsers;
use App\Models\LibraryUserSubscription;
use App\Services\ContentAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Telegram;

/**
 * Phase B: the plan menu must render the unlimited plan with a
 * struck-through list price (~~20,000,000~~ → 10,000,000) plus the
 * reward teaser — no broken keys or "0 toman" (demo pricing only).
 *
 * Inline button labels cannot carry HTML, so buttons keep the plain
 * offer price while the message body carries <s>/<b> markup.
 */
class PlanMenuRenderingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Runs against the fully-migrated schema (RefreshDatabase) and every
     * test is rolled back afterwards. It must NOT drop/recreate the shared
     * `bot_users` table: that DDL auto-commits and would corrupt the
     * schema for every later test in the same run.
     */

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('fa');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Build the real controller with mocked services and invoke the
     * private showPlanMenu(); capture everything sent via the messenger.
     *
     * @return array{message: string, keyboard: array}
     */
    private function renderPlanMenu(BotUsers $user, int $botId): array
    {
        $queueService = Mockery::mock(ContentQueueService::class);
        $deliveryService = Mockery::mock(ContentDeliveryService::class);
        $planService = Mockery::mock(BookLibraryPlanService::class);
        $adminService = Mockery::mock(ContentAdminService::class);

        $bookLibraryService = Mockery::mock(BookLibraryService::class);
        $subscription = LibraryUserSubscription::create([
            'bot_user_id' => $user->id,
            'bot_id' => $botId,
            'plan' => 'free',
            'books_used' => 1,
            'books_limit' => 3,
            'status' => 'active',
        ]);
        $bookLibraryService->shouldReceive('getOrCreateSubscription')
            ->once()
            ->with($user, $botId)
            ->andReturn($subscription);
        $bookLibraryService->shouldReceive('buildProgressBar')
            ->once()
            ->andReturn('progress-bar');

        $controller = new BookLibraryController(
            $queueService,
            $deliveryService,
            $bookLibraryService,
            $planService,
            $adminService
        );

        $captured = [];
        $messenger = Mockery::mock(Telegram::class);
        $messenger->shouldReceive('ChatID')->andReturn('chat-7');
        // Mirrors vendor Telegram::buildInlineKeyBoardButton(): first arg is the
        // label, callback_data arrives as the third positional (a named arg
        // fills skipped parameters with their defaults).
        $messenger->shouldReceive('buildInlineKeyBoardButton')->zeroOrMoreTimes()
            ->andReturnUsing(function (...$args) {
                $button = ['text' => $args[0] ?? ''];
                if (!empty($args[2])) {
                    $button['callback_data'] = $args[2];
                }
                return $button;
            });
        // Mirrors vendor Telegram::buildInlineKeyBoard(): returns a JSON string
        $messenger->shouldReceive('buildInlineKeyBoard')->zeroOrMoreTimes()
            ->andReturnUsing(fn (array $options) => json_encode(['inline_keyboard' => $options], JSON_UNESCAPED_UNICODE));
        $messenger->shouldReceive('sendMessage')->once()->andReturnUsing(function (array $content) use (&$captured) {
            $captured[] = $content;
            return [];
        });

        $method = new \ReflectionMethod(BookLibraryController::class, 'showPlanMenu');
        $method->setAccessible(true);
        $method->invoke($controller, $messenger, $user, $botId);

        $this->assertCount(1, $captured);
        $message = $captured[0]['text'] ?? '';
        $keyboard = $captured[0]['reply_markup'] ?? [];

        return ['message' => $message, 'keyboard' => $keyboard];
    }

    public function test_unlimited_plan_renders_strikethrough_offer_price(): void
    {
        $user = BotUsers::create(['chat_id' => 7001, 'bot_id' => 7, 'origin' => 'telegram']);

        $rendered = $this->renderPlanMenu($user, 7);
        $message = $rendered['message'];

        // Struck-through list price + bold offer price (HTML parse mode)
                $this->assertStringContainsString('<s>20,000,000', $message);
                $this->assertStringContainsString('<b>10,000,000', $message);

        // No broken/untranslated key, and no zero-price line ("— 0 تومان")
        $this->assertStringNotContainsString('book_library.plan.', $message);
        $this->assertStringNotContainsString('— 0 ' . trans('book_library.currency'), $message);

        // Reward teaser line
        $this->assertStringContainsString(trans('book_library.reward_menu_hint'), $message);
    }

    public function test_other_paid_plans_render_plain_price(): void
    {
        $user = BotUsers::create(['chat_id' => 7002, 'bot_id' => 7, 'origin' => 'telegram']);

        $message = $this->renderPlanMenu($user, 7)['message'];

        // plan_100 has no list_price → plain price line, no strikethrough
        $this->assertStringContainsString(trans('book_library.plan_price_line', [
            'price' => '990,000',
            'currency' => trans('book_library.currency'),
        ]), $message);

        $this->assertStringNotContainsString('<s>990,000', $message);
    }

    public function test_button_labels_use_plain_offer_price_without_html(): void
    {
        $user = BotUsers::create(['chat_id' => 7003, 'bot_id' => 7, 'origin' => 'telegram']);

        $keyboard = $this->renderPlanMenu($user, 7)['keyboard'];
                $this->assertNotEmpty($keyboard);
                $keyboardJson = json_encode($keyboard, JSON_UNESCAPED_UNICODE);

                // Unlimited button shows the offer price (10,000,000), never the list price
                $this->assertStringContainsString(
                    trans('book_library.plan.unlimited') . ' - 10,000,000 ' . trans('book_library.currency'),
                    $keyboardJson
                );
                $this->assertStringNotContainsString('20,000,000', $keyboardJson);

                // No HTML markup leaks into button labels
                $this->assertStringNotContainsString('<s>', $keyboardJson);
                $this->assertStringNotContainsString('<b>', $keyboardJson);
    }
}
