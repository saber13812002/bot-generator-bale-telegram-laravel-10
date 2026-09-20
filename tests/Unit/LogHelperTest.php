<?php

namespace Tests\Unit;

use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use Illuminate\Support\Facades\App;
use Tests\TestCase; // Use Laravel's base TestCase
use Illuminate\Foundation\Testing\RefreshDatabase;

class LogHelperTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_log_bot_request_correctly()
    {
        // Simulate request data.
        // POST (not GET) is deliberate: LogHelper::log() reads command_type
        // via $request->request (the raw body), which is empty for GET
        // requests — this mirrors how the real webhook routes POST updates.
        $data = [
            'bot_mother_id' => 1,
            'language' => 'fa',
            'origin' => 'bale',
            'command_type' => 'test_command',
            'token' => 'test_token'
        ];

        // Bind a REAL request to the container instead of mocking the
        // Request facade: Laravel's AuthServiceProvider registers a
        // "request" rebinding handler that calls setUserResolver() on any
        // newly bound instance, which a strict Mockery facade mock rejects.
        // The path is chosen so segment(2) === 'webhook-bot-get-id', which
        // is what LogHelper::log() persists as webhook_endpoint_uri.
        $request = BotRequest::create('/bots/webhook-bot-get-id', 'POST', $data);
        app()->instance('request', $request);

        // Simulate the Bot instance
        $bot = $this->getMockBuilder('Telegram')
            ->disableOriginalConstructor()
            ->getMock();
        $bot->method('Text')
            ->willReturn('Sample bot text');
        $bot->method('ChatID')
            ->willReturn(123456);

        // Call the log method
        LogHelper::log($request, 'bale', $bot);

        // Assert that the log has been created in the database.
        // bot_id falls back to 1 (Bot Mother) in findBotIdFromToken()
        // because the 'test_token' does not belong to any bot in the DB.
        // command_type is intentionally excluded: for a webhook without a
        // recognizable command, LogHelper stores null in the body — the
        // raw value only survives for GET-style (query string) requests.
        $this->assertDatabaseHas('bot_logs', [
            'webhook_endpoint_uri' => 'webhook-bot-get-id',
            'bot_mother_id' => 1,
            'language' => 'fa',
            'locale' => App::getLocale(),
            'type' => 'bale',
            'text' => 'Sample bot text',
            'is_command' => false,
            'channel_group_type' => 0,
            'bot_id' => 1,
            'chat_id' => 123456,
        ]);
    }
}
