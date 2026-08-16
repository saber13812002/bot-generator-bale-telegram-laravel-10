<?php

namespace Tests\Unit;

use App\Models\AppLogEntry;
use App\Models\Bot;
use App\Models\BotHealthEvent;
use App\Services\BotHealthRecorder;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\UsesObservabilitySqlite;

class BotHealthRecorderTest extends TestCase
{
    use UsesObservabilitySqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpObservabilityTables();
    }

    public function test_is_messenger_ok_reads_array_and_json(): void
    {
        $this->assertTrue(BotHealthRecorder::isMessengerOk(['ok' => true]));
        $this->assertFalse(BotHealthRecorder::isMessengerOk(['ok' => false]));
        $this->assertTrue(BotHealthRecorder::isMessengerOk('{"ok":true}'));
        $this->assertFalse(BotHealthRecorder::isMessengerOk('{"ok":false}'));
        $this->assertFalse(BotHealthRecorder::isMessengerOk(null));
        $this->assertFalse(BotHealthRecorder::isMessengerOk(''));
    }

    public function test_record_creates_health_event_and_updates_last_activity(): void
    {
        $bot = Bot::create([
            'bale_bot_name' => 'health_test_bot',
            'bale_bot_token' => '123:health-token',
            'bale_bot_status' => 'Active',
        ]);

        BotHealthRecorder::record([
            'bot_id' => $bot->id,
            'feature_key' => 'sharabe_beheshti',
            'platform' => 'bale',
            'event_type' => 'channel_post',
            'status' => 'ok',
            'meta' => ['config_id' => 1],
        ]);

        $this->assertDatabaseHas('bot_health_events', [
            'bot_id' => $bot->id,
            'feature_key' => 'sharabe_beheshti',
            'platform' => 'bale',
            'status' => 'ok',
        ]);

        $bot->refresh();
        $this->assertNotNull($bot->last_activity_at);
    }

    public function test_fail_status_does_not_update_last_activity(): void
    {
        $bot = Bot::create([
            'bale_bot_name' => 'health_fail_bot',
            'bale_bot_token' => '123:health-fail',
            'bale_bot_status' => 'Active',
        ]);

        BotHealthRecorder::record([
            'bot_id' => $bot->id,
            'feature_key' => 'sharabe_beheshti',
            'platform' => 'bale',
            'status' => 'fail',
            'message' => 'send failed',
        ]);

        $bot->refresh();
        $this->assertNull($bot->last_activity_at);
        $this->assertSame(1, BotHealthEvent::count());
    }

    public function test_find_bot_id_by_token(): void
    {
        $bot = Bot::create([
            'bale_bot_name' => 'token_bot',
            'bale_bot_token' => 'token-abc',
            'bale_bot_status' => 'Active',
        ]);

        $this->assertSame($bot->id, BotHealthRecorder::findBotIdByToken('token-abc'));
        $this->assertNull(BotHealthRecorder::findBotIdByToken('missing'));
        $this->assertNull(BotHealthRecorder::findBotIdByToken(null));
    }

    public function test_database_log_channel_stores_bot_id(): void
    {
        Log::channel('database')->info('database handler test line', [
            'bot_id' => 53,
            'feature_key' => 'sharabe_beheshti',
        ]);

        $this->assertDatabaseHas('app_log_entries', [
            'message' => 'database handler test line',
            'bot_id' => 53,
            'feature_key' => 'sharabe_beheshti',
        ]);
    }

    public function test_prune_keeps_only_max_log_rows(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            AppLogEntry::create([
                'level' => 'info',
                'message' => 'row-'.$i,
                'created_at' => now(),
            ]);
        }

        $this->artisan('observability:prune', ['--log-max' => 2])->assertSuccessful();
        $this->assertSame(2, AppLogEntry::count());
        $this->assertDatabaseHas('app_log_entries', ['message' => 'row-5']);
        $this->assertDatabaseMissing('app_log_entries', ['message' => 'row-1']);
    }
}
