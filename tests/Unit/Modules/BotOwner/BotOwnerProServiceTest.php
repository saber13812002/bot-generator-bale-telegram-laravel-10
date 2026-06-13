<?php

namespace Tests\Unit\Modules\BotOwner;

use App\Modules\BotOwner\Models\BotOwner;
use App\Modules\BotOwner\Models\BotOwnerProRequest;
use App\Modules\BotOwner\Services\BotOwnerProNotificationService;
use App\Modules\BotOwner\Services\BotOwnerProService;
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

    public function test_request_and_confirm_pro(): void
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

        $confirmed = $service->confirmPro($request['request_id'], 1);
        $this->assertTrue($confirmed);

        $owner->refresh();
        $this->assertTrue($owner->is_pro);
    }
}
