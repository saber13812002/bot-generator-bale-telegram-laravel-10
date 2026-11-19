<?php

namespace Tests\Feature\Modules\AdminBots;

use App\Helpers\WebhookMockHelper;
use App\Modules\BotOwner\Models\BotOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBotsWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_command_returns_ok(): void
    {
        $owner = BotOwner::create([
            'phone' => '989123456789',
            'bale_chat_id' => '12345',
            'status' => 'active',
        ]);

        $update = WebhookMockHelper::mockBaleUpdate('/bots');
        $update['message']['chat']['id'] = 12345;

        $response = $this->postJson('/api/webhook-admin-bots?origin=bale&bot_mother_id=1&token=test', $update);

        $response->assertStatus(200);
        $response->assertSee('ok');
    }
}
