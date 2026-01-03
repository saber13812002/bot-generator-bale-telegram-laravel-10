<?php

namespace Tests\Unit;

use App\Helpers\LogHelper;
use App\Http\Requests\BotRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Tests\TestCase; // Use Laravel's base TestCase
use Illuminate\Foundation\Testing\RefreshDatabase;

class LogHelperTest extends TestCase
{
//    use RefreshDatabase; // Use RefreshDatabase trait

    /** @test */
    public function it_can_log_bot_request_correctly()
    {
        // Simulate request data
        $data = [
            'bot_mother_id' => 1,
            'language' => 'fa',
            'origin' => 'bale',
            'command_type' => 'test_command',
            'token' => 'test_token'
        ];
        $request = BotRequest::create('/endpoint', 'GET', $data);

        // Simulate the Bot instance
        $bot = $this->getMockBuilder('Telegram')
            ->disableOriginalConstructor()
            ->getMock();
        $bot->method('Text')
            ->willReturn('Sample bot text');
        $bot->method('ChatID')
            ->willReturn(123456);

        // Mock the request facade
        Request::shouldReceive('segment')
            ->with(2)
            ->andReturn('webhook-bot-get-id');

        // Call the log method
        LogHelper::log($request, 'bale', $bot);

        // Assert that the log has been created in the database
        $this->assertDatabaseHas('bot_logs', [
            'bot_mother_id' => 1,
            'language' => 'fa',
            'command_type' => 'test_command',
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
