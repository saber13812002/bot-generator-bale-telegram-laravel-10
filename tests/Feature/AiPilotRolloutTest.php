<?php

namespace Tests\Feature;

use App\Models\AiSettingsAuditLog;
use App\Models\Bot;
use App\Services\Ai\AiFeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * AI-PILOT-ROLLOUT-05 / R1 — دروازه‌ی rollout تدریجی (fail-closed pilot).
 *
 * پوشش brief §2:
 *  - Stage 0 (Internal) / Stage 1 (Selected Pilot): allowlist حتماً باید
 *    Fail-Closed باشد — allowlist خالی/غیرموجود نباید دسترسی به همه بدهد.
 *  - Stage 1: allowlist فقط مجوز ورود است؛ محدودیت ساعات AI (09–14 تهران)
 *    برای pilot customerها همچنان برقرار است (deferrable).
 *  - Stage 2 (All Pro): فقط Pro (یا pro-override ثبت‌شده) — بدون allowlist.
 *  - Stage 3 (Public): باز برای همه (رفتار موجود حفظ می‌شود).
 *  - هر تغییر setting مدیریت‌شده (stage/allowlist/pro-override/flags)
 *    در ai_settings_audit_logs ثبت می‌شود (brief §4).
 *
 * زمان‌های تست:
 *  - '2026-11-14 11:30:00' UTC = 15:00 تهران  (بیرون پنجره‌ی deferral)
 *  - '2026-11-14 06:30:00' UTC = 10:00 تهران  (در پنجره‌ی deferral)
 */
class AiPilotRolloutTest extends TestCase
{
    use RefreshDatabase;

    private AiFeatureGate $gate;
    private Bot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gate = new AiFeatureGate;
        $this->bot  = Bot::create(['telegram_bot_name' => 'rollout-bot']);

        AiFeatureGate::clearHealthCache();

        // baseline: stage 3 (public) — هر تست stage خودش را set می‌کند.
        // (phpunit.xml هم AI_ROLLOUT_STAGE=3 می‌گذارد تا تست‌های WS-E دست‌نخورده بمانند)
        Config::set('ai.rollout.stage', 3);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function flags(array $on = ['ocr']): void
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

    // ─────────────────────── Stage 0 / 1: fail-closed ───────────────────────

    public function test_stage_0_without_allowlist_denies_everyone(): void
    {
        $this->flags();
        Config::set('ai.rollout.stage', 0);
        Carbon::setTestNow('2026-11-14 11:30:00'); // 15:00 تهران

        // allowlist ندارد → هر user، حتی با id، رد می‌شود (fail-closed)
        $r = $this->gate->check('ocr', $this->bot->id, 5, 5);
        $this->assertFalse($r['allowed']);
        $this->assertSame('not_in_allowlist', $r['reason']);
    }

    public function test_stage_1_without_allowlist_denies_even_with_user_id(): void
    {
        $this->flags();
        Config::set('ai.rollout.stage', 1);
        Carbon::setTestNow('2026-11-14 11:30:00');

        $r = $this->gate->check('ocr', $this->bot->id, null, 99);
        $this->assertFalse($r['allowed']);
        $this->assertSame('not_in_allowlist', $r['reason']);
    }

    public function test_stage_1_with_empty_allowlist_denies(): void
    {
        $this->flags();
        Config::set('ai.rollout.stage', 1);
        Carbon::setTestNow('2026-11-14 11:30:00');
        $this->bot->setAiTestGroup([]); // allowlist «set ولی خالی»

        $r = $this->gate->check('ocr', $this->bot->id, null, 99);
        $this->assertFalse($r['allowed']);
        $this->assertSame('not_in_allowlist', $r['reason']);
    }

    public function test_stage_1_allowlisted_user_is_allowed_outside_window(): void
    {
        $this->flags();
        Config::set('ai.rollout.stage', 1);
        Carbon::setTestNow('2026-11-14 11:30:00'); // 15:00 تهران
        $this->bot->setAiTestGroup([77]);

        $r = $this->gate->check('ocr', $this->bot->id, null, 77);
        $this->assertTrue($r['allowed']);
        $this->assertSame('ok', $r['reason']);
    }

    public function test_stage_1_allowlisted_user_in_deferral_window_is_deferrable(): void
    {
        // brief §2: «allowlist فقط مجوز ورود به برنامه آزمایشی را می‌دهد؛
        // محدودیت ساعات AI همچنان برقرار است»
        $this->flags();
        Config::set('ai.rollout.stage', 1);
        Carbon::setTestNow('2026-11-14 06:30:00'); // 10:00 تهران
        $this->bot->setAiTestGroup([77]);

        $r = $this->gate->check('ocr', $this->bot->id, null, 77);
        $this->assertTrue($r['allowed']);
        $this->assertSame('deferred', $r['reason']);
        $this->assertTrue($r['deferrable']);
    }

    public function test_stage_1_non_listed_user_denied(): void
    {
        $this->flags();
        Config::set('ai.rollout.stage', 1);
        Carbon::setTestNow('2026-11-14 11:30:00');
        $this->bot->setAiTestGroup([77]);

        $r = $this->gate->check('ocr', $this->bot->id, null, 88);
        $this->assertFalse($r['allowed']);
        $this->assertSame('not_in_allowlist', $r['reason']);
    }

    public function test_stage_1_null_subject_denied(): void
    {
        $this->flags();
        Config::set('ai.rollout.stage', 1);
        Carbon::setTestNow('2026-11-14 11:30:00');
        $this->bot->setAiTestGroup([77]);

        // بدون customer/user id (مثل RAG که فقط botId دارد)
        $r = $this->gate->check('ocr', $this->bot->id);
        $this->assertFalse($r['allowed']);
        $this->assertSame('not_in_allowlist', $r['reason']);
    }

    // ─────────────────────── Stage 2: Pro-only ───────────────────────

    public function test_stage_2_regular_user_denied_pro_allowed_without_allowlist(): void
    {
        $this->flags();
        Config::set('ai.rollout.stage', 2);
        Carbon::setTestNow('2026-11-14 11:30:00');

        $regular = $this->gate->check('ocr', $this->bot->id, null, 12);
        $this->assertFalse($regular['allowed']);
        $this->assertSame('pro_only_stage', $regular['reason']);

        $pro = $this->gate->check('ocr', $this->bot->id, null, 12, true);
        $this->assertTrue($pro['allowed']);
        $this->assertSame('ok', $pro['reason']);
    }

    public function test_stage_2_logged_pro_override_treated_as_pro(): void
    {
        $this->flags();
        Config::set('ai.rollout.stage', 2);
        Carbon::setTestNow('2026-11-14 11:30:00');
        $this->bot->setAiProOverride([42]);

        $r = $this->gate->check('ocr', $this->bot->id, null, 42);
        $this->assertTrue($r['allowed']);
        $this->assertSame('ok', $r['reason']);

        $other = $this->gate->check('ocr', $this->bot->id, null, 43);
        $this->assertFalse($other['allowed']);
        $this->assertSame('pro_only_stage', $other['reason']);
    }

    // ─────────────────────── Stage 3: public ───────────────────────

    public function test_stage_3_open_to_all_without_allowlist(): void
    {
        $this->flags();
        Config::set('ai.rollout.stage', 3);
        Carbon::setTestNow('2026-11-14 11:30:00');

        $r = $this->gate->check('ocr', $this->bot->id, null, 123);
        $this->assertTrue($r['allowed']);
        $this->assertSame('ok', $r['reason']);
    }

    public function test_stage_3_existing_test_group_restriction_still_applies(): void
    {
        $this->flags();
        Config::set('ai.rollout.stage', 3);
        Carbon::setTestNow('2026-11-14 11:30:00');
        $this->bot->setAiTestGroup([77]);

        $in  = $this->gate->check('ocr', $this->bot->id, null, 77);
        $out = $this->gate->check('ocr', $this->bot->id, null, 88);
        $this->assertSame('ok', $in['reason']);
        $this->assertSame('not_in_test_group', $out['reason']);
    }

    // ─────────────────────── Bot-level stage override ───────────────────────

    public function test_bot_stage_overrides_global_stage(): void
    {
        $this->flags();
        Carbon::setTestNow('2026-11-14 11:30:00');

        // global 3، bot 0 → fail-closed برای آن bot
        Config::set('ai.rollout.stage', 3);
        $this->bot->setAiRolloutStage(0);
        $r = $this->gate->check('ocr', $this->bot->id, null, 5);
        $this->assertFalse($r['allowed']);
        $this->assertSame('not_in_allowlist', $r['reason']);

        // global 0، bot 3 → public برای آن bot
        Config::set('ai.rollout.stage', 0);
        $this->bot->setAiRolloutStage(3);
        $r2 = $this->gate->check('ocr', $this->bot->id, null, 5);
        $this->assertTrue($r2['allowed']);
        $this->assertSame('ok', $r2['reason']);
    }

    public function test_config_file_defaults_stage_to_zero_when_env_absent(): void
    {
        // phpunit.xml AI_ROLLOUT_STAGE=3 می‌گذارد (PHPUnit هم $_ENV/$_SERVER
        // و هم putenv را پر می‌کند)؛ برای تست «پیش‌فرض config» هر سه را
        // موقتاً پاک می‌کنیم تا env('AI_ROLLOUT_STAGE', 0) به 0 برسد.
        $envConst = $_ENV['AI_ROLLOUT_STAGE'] ?? null;
        $srvConst = $_SERVER['AI_ROLLOUT_STAGE'] ?? null;
        $getenv   = getenv('AI_ROLLOUT_STAGE');
        $getenvWasSet = $getenv !== false;

        unset($_ENV['AI_ROLLOUT_STAGE'], $_SERVER['AI_ROLLOUT_STAGE']);
        putenv('AI_ROLLOUT_STAGE');

        $config = require base_path('config/ai.php');

        if ($envConst !== null) {
            $_ENV['AI_ROLLOUT_STAGE'] = $envConst;
        }
        if ($srvConst !== null) {
            $_SERVER['AI_ROLLOUT_STAGE'] = $srvConst;
        }
        if ($getenvWasSet) {
            putenv("AI_ROLLOUT_STAGE={$getenv}");
        }

        $this->assertSame(0, $config['rollout']['stage']);
    }

    // ─────────────────────── Bot helpers + Nova mapping ───────────────────────

    public function test_bot_ai_rollout_stage_helpers_roundtrip(): void
    {
        $this->assertNull($this->bot->fresh()->aiRolloutStage());

        $this->bot->setAiRolloutStage(1);
        $fresh = $this->bot->fresh();
        $this->assertSame(1, $fresh->aiRolloutStage());
        $this->assertSame(1, $fresh->ai_settings['ai_rollout_stage']);

        $this->bot->setAiRolloutStage(null);
        $this->assertNull($this->bot->fresh()->aiRolloutStage());
        $this->assertArrayNotHasKey('ai_rollout_stage', $this->bot->fresh()->ai_settings);
    }

    public function test_bot_ai_rollout_stage_invalid_value_is_null(): void
    {
        $b = Bot::create([
            'telegram_bot_name' => 'rollout-invalid',
            'ai_settings'       => ['ai_rollout_stage' => 9],
        ]);
        $this->assertNull($b->aiRolloutStage());
    }

    public function test_nova_style_setter_maps_ai_rollout_stage(): void
    {
        $this->bot->ai_rollout_stage = '2';
        $this->assertSame(2, $this->bot->ai_settings['ai_rollout_stage']);
        $this->assertSame('2', $this->bot->ai_rollout_stage);

        $this->bot->ai_rollout_stage = 'follow';
        $this->assertArrayNotHasKey('ai_rollout_stage', $this->bot->ai_settings);
        $this->assertSame('follow', $this->bot->ai_rollout_stage);
    }

    public function test_bot_ai_pro_override_helpers_roundtrip(): void
    {
        $this->assertNull($this->bot->fresh()->aiProOverride());

        $this->bot->setAiProOverride([42, 43]);
        $fresh = $this->bot->fresh();
        $this->assertSame([42, 43], $fresh->aiProOverride());

        $this->bot->setAiProOverride(null);
        $this->assertNull($this->bot->fresh()->aiProOverride());
    }

    // ─────────────────────── Audit log (brief §4) ───────────────────────

    public function test_stage_change_writes_audit_log(): void
    {
        $this->bot->setAiRolloutStage(1, 'owner', 'pilot start');

        $row = AiSettingsAuditLog::where('bot_id', $this->bot->id)
            ->where('key', 'ai_rollout_stage')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('1', (string) $row->new_value);
        $this->assertNull($row->old_value);
        $this->assertSame('owner', $row->actor);
        $this->assertSame('pilot start', $row->reason);

        $this->bot->setAiRolloutStage(2, 'owner', 'advance to pro');
        $row2 = AiSettingsAuditLog::where('bot_id', $this->bot->id)
            ->where('key', 'ai_rollout_stage')
            ->latest('id')
            ->first();
        $this->assertSame('1', (string) $row2->old_value);
        $this->assertSame('2', (string) $row2->new_value);
    }

    public function test_allowlist_change_writes_audit_log(): void
    {
        $this->bot->setAiTestGroup([77]);

        $row = AiSettingsAuditLog::where('bot_id', $this->bot->id)
            ->where('key', 'ai_test_group')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('[77]', (string) $row->new_value);
        $this->assertNull($row->old_value);
    }

    public function test_capability_flag_change_writes_audit_log(): void
    {
        $this->bot->setAiFlag('ocr', true);

        $row = AiSettingsAuditLog::where('bot_id', $this->bot->id)
            ->where('key', 'ai_feature_flags.ocr')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('true', (string) $row->new_value);
    }

    public function test_unchanged_ai_settings_write_no_audit_row(): void
    {
        $this->bot->forceFill(['telegram_bot_name' => 'renamed'])->save();

        $this->assertSame(
            0,
            AiSettingsAuditLog::where('bot_id', $this->bot->id)->count()
        );
    }
}
