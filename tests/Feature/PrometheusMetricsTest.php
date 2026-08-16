<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotHealthEvent;
use App\Models\BotLog;
use App\Models\BotUsers;
use App\Models\WebhookEndpoint;
use Tests\TestCase;
use Tests\UsesObservabilitySqlite;

class PrometheusMetricsTest extends TestCase
{
    use UsesObservabilitySqlite;

    private string $secret = 'test-metrics-secret-token-at-least-32-chars';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpObservabilityTables();
    }

    public function test_metrics_without_secret_returns_404(): void
    {
        $this->get('/api/metrics')->assertNotFound();
        $this->get('/api/metrics?token=wrong-secret')->assertNotFound();
        $this->get('/api/metrics/wrong-secret')->assertNotFound();
    }

    public function test_metrics_with_secret_includes_bot_and_endpoint_labels(): void
    {
        WebhookEndpoint::create([
            'endpoint_id' => 'webhook-hadith',
            'name' => 'Hadith Bot',
            'route' => 'api/webhook-hadith',
        ]);

        $bot = Bot::create([
            'endpoint_id' => 'webhook-hadith',
            'bale_bot_name' => 'hadith_fa_bot',
            'bale_bot_token' => '123:hadith-token',
            'bale_bot_status' => 'Active',
            'bale_webhook_is_set' => true,
        ]);

        BotUsers::create([
            'chat_id' => 111,
            'bot_id' => $bot->id,
            'status' => 'active',
            'origin' => 'bale',
        ]);

        $response = $this->get('/api/metrics?token='.$this->secret);

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringContainsString('bot_id="'.$bot->id.'"', $body);
        $this->assertStringContainsString('endpoint_id="webhook-hadith"', $body);
        $this->assertStringContainsString('endpoint_name="Hadith Bot"', $body);
        $this->assertStringContainsString('bot_name="hadith_fa_bot"', $body);
        $this->assertStringContainsString('platform="bale"', $body);
        $this->assertStringContainsString('bot_users_total', $body);
        $this->assertMatchesRegularExpression('/bot_users_total\{[^}]*\} 1/', $body);
    }

    public function test_metrics_path_secret_and_zero_users_zero_outbound(): void
    {
        WebhookEndpoint::create([
            'endpoint_id' => 'webhook-nahj',
            'name' => 'Nahj Bot',
            'route' => 'api/webhook-nahj',
        ]);

        $bot = Bot::create([
            'endpoint_id' => 'webhook-nahj',
            'telegram_bot_name' => 'nahj_bot',
            'telegram_bot_token' => '456:nahj-token',
            'telegram_bot_status' => 'Active',
        ]);

        BotLog::create([
            'webhook_endpoint_uri' => 'webhook-nahj',
            'bot_id' => $bot->id,
            'type' => 'telegram',
            'text' => '/start',
            'chat_id' => 222,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $response = $this->get('/api/metrics/'.$this->secret);

        $response->assertOk();
        $body = $response->getContent();
        $this->assertMatchesRegularExpression('/bot_users_total\{[^}]*bot_id="'.$bot->id.'"[^}]*\} 0/', $body);
        $this->assertMatchesRegularExpression('/bot_last_outbound_ok_timestamp\{[^}]*bot_id="'.$bot->id.'"[^}]*\} 0/', $body);
        $this->assertMatchesRegularExpression(
            '/bot_last_inbound_timestamp\{[^}]*bot_id="'.$bot->id.'"[^}]*\} [1-9][0-9]*/',
            $body
        );
    }

    public function test_metrics_include_outbound_ok_timestamp(): void
    {
        $bot = Bot::create([
            'endpoint_id' => 'webhook-verse',
            'bale_bot_name' => 'verse_bot',
            'bale_bot_token' => '789:verse-token',
            'bale_bot_status' => 'Active',
        ]);

        $okAt = now()->subMinutes(10);
        BotHealthEvent::create([
            'bot_id' => $bot->id,
            'feature_key' => 'verse',
            'platform' => 'bale',
            'event_type' => 'channel_post',
            'status' => 'ok',
            'created_at' => $okAt,
        ]);

        $body = $this->get('/api/metrics?token='.$this->secret)->getContent();
        $this->assertStringContainsString(
            'bot_last_outbound_ok_timestamp{endpoint_id="webhook-verse",endpoint_name="webhook-verse",bot_id="'.$bot->id.'",bot_name="verse_bot",platform="bale"} '.$okAt->getTimestamp(),
            $body
        );
    }
}
