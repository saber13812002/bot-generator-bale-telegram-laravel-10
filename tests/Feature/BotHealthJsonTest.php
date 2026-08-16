<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotHealthEvent;
use App\Models\BotLog;
use App\Models\BotUsers;
use App\Models\WebhookEndpoint;
use Tests\TestCase;
use Tests\UsesObservabilitySqlite;

class BotHealthJsonTest extends TestCase
{
    use UsesObservabilitySqlite;

    private string $secret = 'test-health-secret-token-at-least-32-chars';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpObservabilityTables();
    }

    public function test_health_without_secret_returns_404(): void
    {
        $this->get('/api/health')->assertNotFound();
        $this->get('/api/health?token=wrong-secret')->assertNotFound();
    }

    public function test_health_no_users_symptom(): void
    {
        WebhookEndpoint::create([
            'endpoint_id' => 'weather-bot',
            'name' => 'Weather',
            'route' => 'api/webhook-weather',
        ]);

        $bot = Bot::create([
            'endpoint_id' => 'weather-bot',
            'bale_bot_name' => 'weather_bot',
            'bale_bot_token' => '1:weather',
            'bale_bot_status' => 'Active',
            'bale_webhook_is_set' => true,
        ]);

        $response = $this->get('/api/health?token='.$this->secret);

        $response->assertOk()->assertJsonPath('bots.0.bot_id', $bot->id);
        $response->assertJsonPath('bots.0.users', 0);
        $this->assertContains('no_users', $response->json('bots.0.symptoms'));
        $this->assertContains('no_inbound', $response->json('bots.0.symptoms'));
        $this->assertNotContains('inbound_without_outbound', $response->json('bots.0.symptoms'));
    }

    public function test_health_inbound_without_outbound_symptom(): void
    {
        $bot = Bot::create([
            'endpoint_id' => 'webhook-nahj',
            'telegram_bot_name' => 'nahj_bot',
            'telegram_bot_token' => '2:nahj',
            'telegram_bot_status' => 'Active',
            'telegram_webhook_is_set' => true,
        ]);

        BotUsers::create([
            'chat_id' => 333,
            'bot_id' => $bot->id,
            'status' => 'active',
            'origin' => 'telegram',
        ]);

        BotLog::create([
            'webhook_endpoint_uri' => 'webhook-nahj',
            'bot_id' => $bot->id,
            'type' => 'telegram',
            'text' => 'سلام',
            'chat_id' => 333,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $response = $this->getJson('/api/health/'.$this->secret);

        $response->assertOk();
        $this->assertContains('inbound_without_outbound', $response->json('bots.0.symptoms'));
        $this->assertNotContains('no_users', $response->json('bots.0.symptoms'));
        $this->assertNotContains('no_inbound', $response->json('bots.0.symptoms'));
    }

    public function test_artisan_health_report_prints_json(): void
    {
        Bot::create([
            'endpoint_id' => 'webhook-hadith',
            'bale_bot_name' => 'hadith_bot',
            'bale_bot_token' => '3:hadith',
            'bale_bot_status' => 'DeActive',
        ]);

        $this->artisan('observability:health-report')->assertSuccessful();

        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('"symptoms"', $output);
        $this->assertStringContainsString('deactive', $output);
        $this->assertStringContainsString('no_users', $output);
    }

    public function test_health_does_not_flag_inbound_without_outbound_when_ok_is_newer(): void
    {
        $bot = Bot::create([
            'endpoint_id' => 'webhook-verse',
            'bale_bot_name' => 'verse_bot',
            'bale_bot_token' => '4:verse',
            'bale_bot_status' => 'Active',
            'bale_webhook_is_set' => true,
        ]);

        BotLog::create([
            'webhook_endpoint_uri' => 'webhook-verse',
            'bot_id' => $bot->id,
            'type' => 'bale',
            'text' => '/start',
            'chat_id' => 1,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        BotHealthEvent::create([
            'bot_id' => $bot->id,
            'feature_key' => 'verse',
            'platform' => 'bale',
            'status' => 'ok',
            'created_at' => now()->subHour(),
        ]);

        $symptoms = $this->get('/api/health?token='.$this->secret)->json('bots.0.symptoms');
        $this->assertNotContains('inbound_without_outbound', $symptoms);
    }
}
