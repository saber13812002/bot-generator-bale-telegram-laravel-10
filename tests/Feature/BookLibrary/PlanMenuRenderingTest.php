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
use Illuminate\Support\Facades\Schema;
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

    private function setUpTables(): void
    {
        Schema::dropIfExists('library_user_subscriptions');
        Schema::dropIfExists('bot_users');

        Schema::create('bot_users', function ($table) {
            $table->id();
            $table->bigInteger('chat_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('origin')->default('telegram');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('library_user_subscriptions', function ($table) {
            $table->id();
            $table->unsignedBigInteger('bot_user_id');
            $table->unsignedBigInteger('bot_id');
            $table->string('plan', 20)->default('free');
            $table->unsignedInteger('books_used')->default(0);
            $table->unsignedInteger('books_limit')->default(3);
            $table->unsignedInteger('reward_target')->nullable();
            $table->unsignedInteger('reward_bonus')->nullable();
            $table->timestamp('reward_granted_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['bot_user_id', 'bot_id']);
        });
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
        $messenger->shouldReceive('sendMessage')->once()->andReturnUsing(function (array $content) use (&$captured) {
            $captured[] = $content;
            return [];
        });

        $method = new \ReflectionMethod(BookLibraryController::class, 'showPlanMenu');
        $method->setAccessible(true);
        $method->invoke($controller, $messenger, $user, $botId);

        $this->assertCount(1, $captured);
        $captured[0]['message'] = $captured[0]['text'] ?? '';
        unset($captured[0]['text'], $captured[0]['reply_markup']);

        return ['message' => $captured[0]['message'], 'keyboard' => $captured[0]['reply_markup'] ?? []];
    }

    public function test_unlimited_plan_renders_strikethrough_offer_price(): void
    {
        $this->setUpTables();
        $user = BotUsers::create(['chat_id' => 7001, 'bot_id' => 7, 'origin' => 'telegram']);

        $rendered = $this->renderPlanMenu($user, 7);
        $message = $rendered['message'];

        // Struck-through list price + bold offer price (HTML parse mode)
                $this->assertStringContainsString('<s>20,000,000', $message);
                $this->assertStringContainsString('<b>10,000,000', $message);

        // No broken/untranslated key or "0 toman" for the unlimited plan
        $this->assertStringNotContainsString('book_library.plan.', $message);
        $this->assertStringNotContainsString('0 ' . trans('book_library.currency'), $message);

        // Reward teaser line
        $this->assertStringContainsString(trans('book_library.reward_menu_hint'), $message);
    }

    public function test_other_paid_plans_render_plain_price(): void
    {
        $this->setUpTables();
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
        $this->setUpTables();
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
