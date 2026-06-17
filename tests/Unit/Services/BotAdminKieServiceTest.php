<?php

namespace Tests\Unit\Services;

use App\Models\Bot;
use App\Models\BotAdminKieRequest;
use App\Services\BotAdminKieNotificationService;
use App\Services\BotAdminKieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BotAdminKieServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(): BotAdminKieService
    {
        $notify = Mockery::mock(BotAdminKieNotificationService::class);
        $notify->shouldReceive('notifySuperAdmins')->byDefault();

        return new BotAdminKieService($notify);
    }

    public function test_is_adminkie_command(): void
    {
        $service = $this->makeService();

        $this->assertTrue($service->isAdminkieCommand('/adminkie'));
        $this->assertTrue($service->isAdminkieCommand('/ADMINKIE'));
        $this->assertFalse($service->isAdminkieCommand('/start'));
        $this->assertFalse($service->isAdminkieCommand(null));
    }

    public function test_confirm_request_sets_bot_owner(): void
    {
        $notify = Mockery::mock(BotAdminKieNotificationService::class);
        $service = new BotAdminKieService($notify);

        $bot = Bot::create([
            'bale_bot_name' => 'test_bot',
            'bale_bot_token' => '123:abc',
            'bale_bot_status' => 'Active',
        ]);

        $request = BotAdminKieRequest::create([
            'bot_id' => $bot->id,
            'chat_id' => 999888777,
            'origin' => 'bale',
            'first_name' => 'Ali',
            'status' => 'pending',
        ]);

        $result = $service->confirmRequest($request->id, 111222333);
        $this->assertTrue($result);

        $bot->refresh();
        $this->assertEquals('999888777', (string) $bot->bale_owner_chat_id);

        $request->refresh();
        $this->assertEquals('confirmed', $request->status);
        $this->assertEquals(111222333, $request->approved_by);
    }

    public function test_confirm_returns_false_for_missing_request(): void
    {
        $service = $this->makeService();
        $this->assertFalse($service->confirmRequest(99999, 1));
    }
}
