<?php

namespace Tests\Unit\Modules\BotOwner;

use App\Modules\BotOwner\Models\BotOwner;
use App\Modules\BotOwner\Models\BotOwnerProRequest;
use App\Modules\BotOwner\Services\BotOwnerProNotificationService;
use App\Modules\BotOwner\Services\BotOwnerProService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BotOwnerProServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(): BotOwnerProService
    {
        $notify = Mockery::mock(BotOwnerProNotificationService::class);
        $notify->shouldReceive('notifySuperAdmins')->byDefault();

        return new BotOwnerProService($notify);
    }

    public function test_request_and_confirm_pro_sets_expiry(): void
    {
        $notify = Mockery::mock(BotOwnerProNotificationService::class);
        $notify->shouldReceive('notifySuperAdmins')->once();

        $owner = BotOwner::create([
            'phone' => '989123456789',
            'is_pro' => false,
            'status' => 'active',
        ]);

        $service = new BotOwnerProService($notify);
        $request = $service->requestPro($owner->id);

        $this->assertTrue($request['success']);
        $this->assertDatabaseHas('bot_owner_pro_requests', [
            'bot_owner_id' => $owner->id,
            'status' => 'pending',
        ]);

        Carbon::setTestNow('2026-06-16 12:00:00');
        $confirmed = $service->confirmPro($request['request_id'], 1, 3);
        $this->assertTrue($confirmed);

        $owner->refresh();
        $this->assertTrue($owner->is_pro);
        $this->assertTrue($owner->hasActivePro());
        $this->assertEquals('2026-09-16 12:00:00', $owner->pro_expires_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_request_pro_rejected_when_already_active(): void
    {
        $service = $this->makeService();

        $owner = BotOwner::create([
            'phone' => '989123456788',
            'is_pro' => true,
            'pro_confirmed_at' => now(),
            'pro_expires_at' => now()->addMonths(3),
            'status' => 'active',
        ]);

        $result = $service->requestPro($owner->id);

        $this->assertFalse($result['success']);
    }

    public function test_request_pro_rejected_when_pending_exists(): void
    {
        $service = $this->makeService();

        $owner = BotOwner::create([
            'phone' => '989123456787',
            'is_pro' => false,
            'status' => 'active',
        ]);

        BotOwnerProRequest::create([
            'bot_owner_id' => $owner->id,
            'status' => 'pending',
        ]);

        $result = $service->requestPro($owner->id);

        $this->assertFalse($result['success']);
    }

    public function test_request_pro_allowed_after_expiry(): void
    {
        $notify = Mockery::mock(BotOwnerProNotificationService::class);
        $notify->shouldReceive('notifySuperAdmins')->once();

        $service = new BotOwnerProService($notify);

        $owner = BotOwner::create([
            'phone' => '989123456786',
            'is_pro' => true,
            'pro_confirmed_at' => now()->subMonths(4),
            'pro_expires_at' => now()->subMonth(),
            'status' => 'active',
        ]);

        $this->assertFalse($owner->hasActivePro());

        $result = $service->requestPro($owner->id);

        $this->assertTrue($result['success']);
    }

    public function test_confirm_pro_unlimited_sets_far_future_expiry(): void
    {
        $service = $this->makeService();

        $owner = BotOwner::create([
            'phone' => '989123456785',
            'is_pro' => false,
            'status' => 'active',
        ]);

        $request = BotOwnerProRequest::create([
            'bot_owner_id' => $owner->id,
            'status' => 'pending',
        ]);

        Carbon::setTestNow('2026-06-16 12:00:00');
        $this->assertTrue($service->confirmPro($request->id, 1, 0));

        $owner->refresh();
        $this->assertTrue($owner->isProUnlimited());
        $this->assertNull($owner->pro_expires_at);

        Carbon::setTestNow();
    }
}
