<?php

namespace Tests\Feature;

use App\Models\AppLogEntry;
use App\Models\BotHealthEvent;
use Tests\TestCase;
use Tests\UsesObservabilitySqlite;

class LogViewerTest extends TestCase
{
    use UsesObservabilitySqlite;

    private string $secret = 'test-log-viewer-secret-token-at-least-32-chars';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpObservabilityTables();
    }

    public function test_unknown_path_returns_404(): void
    {
        $this->get('/api/this-is-not-the-secret-path-xxxxxxxx')->assertNotFound();
    }

    public function test_viewer_shows_latest_logs(): void
    {
        AppLogEntry::create([
            'level' => 'info',
            'message' => 'visible log line for viewer',
            'bot_id' => 53,
            'created_at' => now(),
        ]);

        $this->get('/api/'.$this->secret)
            ->assertOk()
            ->assertSee('visible log line for viewer')
            ->assertSee('53');
    }

    public function test_viewer_filters_by_bot_id(): void
    {
        AppLogEntry::create([
            'level' => 'info',
            'message' => 'only bot fifty three',
            'bot_id' => 53,
            'created_at' => now(),
        ]);
        AppLogEntry::create([
            'level' => 'error',
            'message' => 'only bot ninety nine',
            'bot_id' => 99,
            'created_at' => now(),
        ]);

        $this->get('/api/'.$this->secret.'?bot_id=53')
            ->assertOk()
            ->assertSee('only bot fifty three')
            ->assertDontSee('only bot ninety nine');
    }

    public function test_format_text_returns_plain_text(): void
    {
        AppLogEntry::create([
            'level' => 'info',
            'message' => 'copy this log for cursor',
            'bot_id' => 53,
            'feature_key' => 'sharabe_beheshti',
            'created_at' => now(),
        ]);

        $response = $this->get('/api/'.$this->secret.'?bot_id=53&limit=100&format=text');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $response->assertSee('copy this log for cursor');
        $response->assertSee('bot_id=53');
    }

    public function test_health_cards_appear_on_viewer(): void
    {
        BotHealthEvent::create([
            'feature_key' => 'sharabe_beheshti',
            'platform' => 'bale',
            'event_type' => 'channel_post',
            'status' => 'ok',
            'created_at' => now(),
        ]);

        $this->get('/api/'.$this->secret)
            ->assertOk()
            ->assertSee('sharabe_beheshti')
            ->assertSee('bale');
    }
}
