<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\Bot;
use App\Services\Ai\AiFeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WS-E: دروازه‌ی مرکزی قابلیت‌های AI.
 *
 * پوشش acceptance scenarios:
 *  - 2 (bot independence): flag هر bot مستقل؛ capability independence
 *  - 5 (staged rollout): test group allowlist
 *  - 1 (time window): L4 → deferrable در پنجره‌ی 09–14 تهران؛ Pro 24h
 *  - 3 (AI outage): provider unhealthy → deny؛ بدون provider → safe-by-default
 */
class AiFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    private AiFeatureGate $gate;
    private Bot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gate = new AiFeatureGate;
        $this->bot  = Bot::create(['telegram_bot_name' => 'gate-test-bot']);

        // state تمیز cache/HTTP
        AiFeatureGate::clearHealthCache();
    }

    private function flags(array $on = []): void
    {
        Config::set('ai.flags', [
            'ai_global_enabled'           => true,
            'ai_llm_enabled'              => in_array('llm', $on, true),
            'ai_ocr_enabled'              => in_array('ocr', $on, true),
            'ai_asr_enabled'              => in_array('asr', $on, true),
            'ai_rag_enabled'              => in_array('rag', $on, true),
            'ai_tts_enabled'              => in_array('tts', $on, true),
            'ai_growth_companion_enabled' => in_array('growth_companion', $on, true),
        ]);
    }

    private function botWithSettings(array $aiSettings): Bot
    {
        return Bot::create([
            'telegram_bot_name' => 'gate-'.uniqid(),
            'ai_settings'       => $aiSettings,
        ]);
    }

    // ─────────────────────────── L0.5 + L1 ───────────────────────────

    public function test_master_switch_off_denies_everything(): void
    {
        $this->flags(['ocr', 'llm']);
        Config::set('ai.flags.ai_global_enabled', false);

        $r = $this->gate->check('ocr', $this->bot->id);

        $this->assertFalse($r['allowed']);
        $this->assertSame('global_off', $r['reason']);
    }

    public function test_capability_flag_off_denies_that_capability_only(): void
    {
        // ocr روشن، llm خاموش → capability independence (decision owner #3)
        $this->flags(['ocr']);

        $this->assertTrue($this->gate->check('ocr', $this->bot->id)['allowed']);

        $r = $this->gate->check('llm', $this->bot->id);
        $this->assertFalse($r['allowed']);
        $this->assertSame('global_off', $r['reason']);
    }

    public function test_unknown_feature_denied(): void
    {
        $this->flags(['ocr']);

        $r = $this->gate->check('does_not_exist', $this->bot->id);

        $this->assertFalse($r['allowed']);
        $this->assertSame('unknown_feature', $r['reason']);
    }

    // ─────────────────────────── L2: bot override ───────────────────────────

    public function test_bot_can_disable_globally_enabled_capability(): void
    {
        $this->flags(['ocr']);
        $botOff = $this->botWithSettings(['ai_feature_flags' => ['ocr' => false]]);

        $r = $this->gate->check('ocr', $botOff->id);

        $this->assertFalse($r['allowed']);
        $this->assertSame('bot_disabled', $r['reason']);

        // bot دیگر (بدون override) همان capability را دارد → bot independence
        $this->assertTrue($this->gate->check('ocr', $this->bot->id)['allowed']);
    }

    public function test_kill_switch_wins_over_bot_override(): void
    {
        $this->flags([]); // همه سراسری خاموش (master kill switch)
        $botOn = $this->botWithSettings(['ai_feature_flags' => ['asr' => true]]);

        // بدون override → deny
        $this->assertFalse($this->gate->check('asr', $this->bot->id)['allowed']);

        // حتی با override on در bot → باز هم deny؛ flag سراسری kill switch است (L1 قبل از L2)
        $r = $this->gate->check('asr', $botOn->id);
        $this->assertFalse($r['allowed']);
        $this->assertSame('global_off', $r['reason']);
    }

    // ─────────────────────────── L3: test group ───────────────────────────

    public function test_test_group_allowlist_gates_users(): void
    {
        $this->flags(['ocr']);
        $bot = $this->botWithSettings(['ai_test_group' => [101, 202]]);

        $in  = $this->gate->check('ocr', $bot->id, customerId: 101);
        $out = $this->gate->check('ocr', $bot->id, customerId: 999);
        $none = $this->gate->check('ocr', $bot->id);

        $this->assertTrue($in['allowed']);
        $this->assertFalse($out['allowed']);
        $this->assertSame('not_in_test_group', $out['reason']);
        $this->assertFalse($none['allowed'], 'بدون user/customer → در allowlist نیست');
    }

    public function test_no_test_group_means_everyone_allowed(): void
    {
        $this->flags(['ocr']);
        $bot = $this->botWithSettings([]);

        $this->assertTrue($this->gate->check('ocr', $bot->id, customerId: 7)['allowed']);
        $this->assertTrue($this->gate->check('ocr', $bot->id, userId: 8)['allowed']);
    }

    // ─────────────────────────── L4: time window ───────────────────────────

    public function test_regular_user_in_morning_window_is_deferrable(): void
    {
        // 2026-11-14 06:30 UTC = 10:00 Tehran (وسط پنجره‌ی deferral)
        Carbon::setTestNow(Carbon::parse('2026-11-14 06:30:00', 'UTC'));
        $this->flags(['ocr']);

        $r = $this->gate->check('ocr', $this->bot->id, customerId: 1);

        $this->assertTrue($r['allowed']);
        $this->assertTrue($r['deferrable']);
        $this->assertSame('deferred', $r['reason']);
    }

    public function test_pro_user_bypasses_deferral_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 06:30:00', 'UTC'));
        $this->flags(['ocr']);

        $r = $this->gate->check('ocr', $this->bot->id, customerId: 1, isPro: true);

        $this->assertTrue($r['allowed']);
        $this->assertFalse($r['deferrable']);
        $this->assertSame('ok', $r['reason']);
    }

    public function test_outside_window_regular_is_immediate(): void
    {
        // 2026-11-14 11:30 UTC = 15:00 Tehran (خارج از پنجره)
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));
        $this->flags(['ocr']);

        $r = $this->gate->check('ocr', $this->bot->id, customerId: 1);

        $this->assertTrue($r['allowed']);
        $this->assertFalse($r['deferrable']);
        $this->assertSame('ok', $r['reason']);
    }

    // ─────────────────────────── L5: provider health ───────────────────────────

    public function test_unhealthy_provider_denies(): void
    {
        $this->flags(['ocr']);
        Http::fake(['*' => Http::response('boom', 500)]);
        AiFeatureGate::clearHealthCache();

        // provider active در DB؟ خیر (RefreshDatabase خالی) → safe-by-default pass
        // برای deny واقعی provider row لازم است:
        \App\Models\AiProvider::create([
            'name'    => 'litellm',
            'is_active' => true,
            'base_url' => 'https://ai.example.test',
            'api_key' => 'sk-test',
        ]);

        $r = $this->gate->check('ocr', $this->bot->id);

        $this->assertFalse($r['allowed']);
        $this->assertSame('provider_unhealthy', $r['reason']);
    }

    public function test_no_provider_row_is_safe_by_default(): void
    {
        $this->flags(['ocr']);

        $r = $this->gate->check('ocr', $this->bot->id);

        $this->assertTrue($r['allowed'], 'بدون provider row → safe-by-default pass (WS-J سخت‌گیرتر می‌کند)');
    }

    // ─────────────────────────── L6: capacity ───────────────────────────

    public function test_capacity_exceeded_denies_new_jobs_for_busy_bot(): void
    {
        $this->flags(['ocr']);
        Config::set('ai.queue.max_processing_per_bot', 2);

        // ۲ job open برای این bot
        $payload = ['prompt' => 'x'];
        for ($i = 0; $i < 2; $i++) {
            AiJob::create([
                'job_id'          => (string) \Illuminate\Support\Str::uuid(),
                'idempotency_key' => 'cap-'.uniqid(),
                'bot_id'          => $this->bot->id,
                'feature'         => 'ocr',
                'state'           => AiJob::STATE_QUEUED,
                'payload'         => $payload,
            ]);
        }

        $r = $this->gate->check('ocr', $this->bot->id, customerId: 1);

        $this->assertFalse($r['allowed']);
        $this->assertSame('capacity_exceeded', $r['reason']);

        // bot دیگر ظرفیت دارد
        $other = $this->botWithSettings([]);
        $this->assertTrue($this->gate->check('ocr', $other->id, customerId: 1)['allowed']);
    }

    // ─────────────────────────── helpers / model ───────────────────────────

    public function test_bot_ai_flag_helpers_roundtrip(): void
    {
        $this->flags(['ocr']);

        // default: no override
        $this->assertNull($this->bot->fresh()->aiFlag('ocr'));

        // set on
        $this->bot->setAiFlag('ocr', true);
        $this->assertTrue($this->bot->fresh()->aiFlag('ocr'));

        // set off
        $this->bot->setAiFlag('ocr', false);
        $this->assertFalse($this->bot->fresh()->aiFlag('ocr'));

        // remove override
        $this->bot->setAiFlag('ocr', null);
        $this->assertNull($this->bot->fresh()->aiFlag('ocr'));
    }

    public function test_nova_style_setter_maps_to_ai_settings(): void
    {
        $bot = Bot::create(['telegram_bot_name' => 'nova-style']);

        $bot->ai_ocr = 'on';
        $bot->ai_test_group = '12, 34';
        $bot->save();

        $fresh = $bot->fresh();
        $this->assertTrue($fresh->aiFlag('ocr'));
        $this->assertSame([12, 34], $fresh->aiTestGroup());
        $this->assertSame('on', $fresh->ai_ocr);
        $this->assertSame('12, 34', $fresh->ai_test_group);
        $this->assertSame('follow', $fresh->ai_llm);
    }

    public function test_is_open_shortcut_respects_bot_override(): void
    {
        $this->flags(['ocr']);
        $botOff = $this->botWithSettings(['ai_feature_flags' => ['ocr' => false]]);

        $this->assertTrue($this->gate->isOpen('ocr', $this->bot->id));
        $this->assertFalse($this->gate->isOpen('ocr', $botOff->id));
    }

    public function test_gate_never_throws(): void
    {
        // botId غیرموجود + state عجیب → باید clean deny/ok برگردد، نه exception
        $this->flags(['ocr']);

        $r = $this->gate->check('ocr', 999999, customerId: 1);
        $this->assertIsBool($r['allowed']);
        $this->assertIsString($r['reason']);
    }

    public function test_feature_constants_cover_all_flags(): void
    {
        $this->assertSame(
            ['llm', 'ocr', 'asr', 'rag', 'tts', 'growth_companion'],
            AiFeatureGate::FEATURES
        );
    }
}
