<?php

namespace Tests\Unit\Services;

use App\Models\Bot;
use App\Models\BotAdminKieRequest;
use App\Models\LibraryBotConfig;
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

    public function test_confirm_resolves_main_bot_from_reader_bot_id(): void
    {
        $notify = Mockery::mock(BotAdminKieNotificationService::class);
        $service = new BotAdminKieService($notify);

        $mainBot = Bot::create([
            'endpoint_id' => 'book-library',
            'bale_bot_name' => 'main_library',
            'bale_bot_token' => '111:main',
            'bale_bot_status' => 'Active',
        ]);

        $readerBot = Bot::create([
            'endpoint_id' => 'book-library-reader',
            'bale_bot_name' => 'reader_library',
            'bale_bot_token' => '222:reader',
            'bale_bot_status' => 'Active',
        ]);

        LibraryBotConfig::create([
            'bot_id' => $mainBot->id,
            'reader_bot_id' => $readerBot->id,
        ]);

        $request = BotAdminKieRequest::create([
            'bot_id' => $readerBot->id,
            'chat_id' => 2060645916,
            'origin' => 'bale',
            'webhook_endpoint' => 'webhook-book-library-reader',
            'first_name' => 'Reza',
            'status' => 'pending',
        ]);

        $this->assertTrue($service->confirmRequest($request->id, 485750575));

        $mainBot->refresh();
        $this->assertEquals('2060645916', (string) $mainBot->bale_owner_chat_id);

        $request->refresh();
        $this->assertEquals('confirmed', $request->status);
    }
}
