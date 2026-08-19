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
}
