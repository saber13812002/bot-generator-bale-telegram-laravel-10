<?php

namespace Tests\Unit;

use App\Interfaces\Services\GrowthCompanionService;
use App\Models\BotUsers;
use App\Models\GrowthProfile;
use App\Services\NullGrowthLlmProvider;
use Carbon\Carbon;
use Tests\TestCase;
use Tests\UsesGrowthCompanionSqlite;

class GrowthCompanionPhase2UnitTest extends TestCase
{
    use UsesGrowthCompanionSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpGrowthCompanionTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_null_llm_returns_no_variants(): void
    {
        $provider = new NullGrowthLlmProvider();
        $this->assertSame([], $provider->generateVariants('intent', 'health', 1, 'fa'));
    }

    public function test_day_window_starts_at_reset_hour(): void
    {
        $user = BotUsers::create([
            'chat_id' => 1,
            'bot_id' => 1,
            'origin' => 'bale',
            'status' => 'active',
        ]);
        $profile = GrowthProfile::create([
            'bot_user_id' => $user->id,
            'bot_id' => 1,
            'timezone' => 'Asia/Tehran',
            'day_reset_hour' => 3,
        ]);
        $service = app(GrowthCompanionService::class);

        Carbon::setTestNow(Carbon::parse('2026-08-18 02:30:00', 'Asia/Tehran'));
        $start = $service->dayWindowStart($profile);
        $this->assertTrue($start->equalTo(
            Carbon::parse('2026-08-17 03:00:00', 'Asia/Tehran')->utc()
        ));

        Carbon::setTestNow(Carbon::parse('2026-08-18 03:30:00', 'Asia/Tehran'));
        $start = $service->dayWindowStart($profile);
        $this->assertTrue($start->equalTo(
            Carbon::parse('2026-08-18 03:00:00', 'Asia/Tehran')->utc()
        ));
    }

    public function test_budget_for_intensity(): void
    {
        $this->assertSame(1, \App\Services\GrowthCompanionServiceImpl::budgetForIntensity('minimal'));
        $this->assertSame(1, \App\Services\GrowthCompanionServiceImpl::budgetForIntensity('balanced'));
        $this->assertSame(2, \App\Services\GrowthCompanionServiceImpl::budgetForIntensity('active'));
    }

    public function test_progress_bar_and_bullet_summary(): void
    {
        $service = app(GrowthCompanionService::class);
        $this->assertSame('▰▰▰▱▱▱▱', $service->progressBar(3));
        $this->assertSame(
            ['First line', 'Second sentence.', 'Extra'],
            $service->bulletSummary("First line\n\nSecond sentence. Extra")
        );
    }

    public function test_pro_confirm_sets_monthly_expiry(): void
    {
        $bot = \App\Models\Bot::create([
            'endpoint_id' => \App\Http\Controllers\GrowthCompanionController::ENDPOINT_ID,
            'bale_bot_token' => 'tok',
        ]);
        $user = BotUsers::create([
            'chat_id' => 9,
            'bot_id' => $bot->id,
            'origin' => 'bale',
            'status' => 'active',
        ]);
        $request = \App\Models\ProPurchaseRequest::create([
            'bot_user_id' => $user->id,
            'bot_id' => $bot->id,
            'user_identifier' => '9',
            'status' => 'pending',
            'payment_info' => json_encode(['plan' => 'monthly', 'months' => 1, 'amount' => 49000]),
        ]);

        $ok = app(\App\Interfaces\Services\ProService::class)->confirmPurchase($request->id, 1);
        $this->assertTrue($ok);
        $pro = \App\Models\ProUser::where('bot_user_id', $user->id)->where('bot_id', $bot->id)->first();
        $this->assertNotNull($pro);
        $this->assertTrue($user->fresh()->isPro($bot->id));
        $this->assertNotNull($pro->expires_at);
        $this->assertTrue($pro->expires_at->greaterThan(now()->addDays(20)));
    }
}
