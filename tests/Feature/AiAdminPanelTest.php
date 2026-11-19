<?php

namespace Tests\Feature;

use App\Models\AiFlag;
use App\Models\AiJob;
use App\Models\AiSettingsAuditLog;
use App\Models\Bot;
use App\Services\Ai\AiFeatureGate;
use App\Services\Ai\AiFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * AI-PILOT-ROLLOUT-05 (R2): پنل مدیریت AI — flagها بدون re-deploy + Nova.
 *
 * brief §4:
 *  - AI Global Kill Switch + per-capability on/off — مستقل، بدون re-deploy
 *  - انتخاب bot/customer، نمایش Pro، نمایش jobs/errors، provider usage/cost
 *  - مدیریت allowlist + وضعیت rollout
 *  - هر تغییر ادمین → Audit Log
 *
 * پوشش:
 *  - AiFlagService: override بدون re-deploy (kill switch + capability مستقل)
 *  - audit row برای هر تغییر flag سراسری (bot_id NULL)
 *  - قرارداد resourceهای Nova: AiFlag (ویرایش‌پذیر)، AiJob (read-only)،
 *    AiSettingsAuditLog (read-only)، Bot (فیلدهای stage/pro-override)
 */
class AiAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private AiFeatureGate $gate;
    private Bot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gate = new AiFeatureGate;
        $this->bot  = Bot::create(['telegram_bot_name' => 'admin-panel-bot']);

        AiFeatureGate::clearHealthCache();
        Config::set('ai.rollout.stage', 3);
        Carbon::setTestNow('2026-11-14 11:30:00'); // 15:00 تهران (بیرون پنجره)
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function novaRequest(): \Laravel\Nova\Http\Requests\NovaRequest
    {
        return \Laravel\Nova\Http\Requests\NovaRequest::create('/nova-admin/bots', 'GET');
    }

    private function configFlags(array $on = ['ocr', 'asr']): void
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

    // ─────────────── Kill switch بدون re-deploy (brief §4) ───────────────

    public function test_global_kill_switch_override_denies_without_config_change(): void
    {
        $this->configFlags();

        $before = $this->gate->check('ocr', $this->bot->id, null, 5);
        $this->assertTrue($before['allowed']);

        // override بدون re-deploy (row جدول ai_flags) — config دست‌نخورده
        AiFlagService::set('ai_global_enabled', false, 'admin', 'incident drill');

        $after = $this->gate->check('ocr', $this->bot->id, null, 5);
        $this->assertFalse($after['allowed']);
        $this->assertSame('global_off', $after['reason']);

        // config هنوز خاموش/روشن نیست — اثبات اینکه override منبع تصمیم است
        $this->assertTrue((bool) config('ai.flags.ai_global_enabled'));

        // حذف override → برمی‌گردد به config (روشن)
        AiFlagService::set('ai_global_enabled', null, 'admin', 'incident resolved');
        $restored = $this->gate->check('ocr', $this->bot->id, null, 5);
        $this->assertTrue($restored['allowed']);
    }

    public function test_per_capability_override_is_independent(): void
    {
        $this->configFlags(['ocr', 'asr']);

        // فقط OCR خاموش — ASR مستقل و فعال می‌ماند (brief §9: independent
        // per-feature shutdown)
        AiFlagService::set('ai_ocr_enabled', false, 'admin', 'vision model degraded');

        $ocr = $this->gate->check('ocr', $this->bot->id, null, 5);
        $asr = $this->gate->check('asr', $this->bot->id, null, 5);

        $this->assertFalse($ocr['allowed']);
        $this->assertSame('global_off', $ocr['reason']);
        $this->assertTrue($asr['allowed']);
        $this->assertSame('ok', $asr['reason']);
    }

    public function test_capability_override_can_enable_without_config(): void
    {
        // config خاموش، override روشن → capability فعال (deploy-free enable)
        $this->configFlags([]);

        $off = $this->gate->check('rag', $this->bot->id, null, 5);
        $this->assertFalse($off['allowed']);

        AiFlagService::set('ai_rag_enabled', true, 'admin', 'pilot RAG');

        $on = $this->gate->check('rag', $this->bot->id, null, 5);
        $this->assertTrue($on['allowed']);
    }

    // ─────────────── Audit log flagهای سراسری (brief §4) ───────────────

    public function test_global_flag_changes_are_audited_with_null_bot(): void
    {
        AiFlagService::set('ai_global_enabled', false, 'admin', 'rollback');

        $row = AiSettingsAuditLog::where('key', 'ai_flags.ai_global_enabled')
            ->whereNull('bot_id')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('false', (string) $row->new_value);
        $this->assertNull($row->old_value);
        $this->assertSame('admin', $row->actor);
        $this->assertSame('rollback', $row->reason);

        AiFlagService::set('ai_global_enabled', true, 'admin', 'restore');
        $row2 = AiSettingsAuditLog::where('key', 'ai_flags.ai_global_enabled')
            ->latest('id')
            ->first();
        $this->assertSame('false', (string) $row2->old_value);
        $this->assertSame('true', (string) $row2->new_value);
    }

    public function test_same_flag_value_writes_no_duplicate_audit_row(): void
    {
        AiFlagService::set('ai_global_enabled', false, 'admin', 'first');
        AiFlagService::set('ai_global_enabled', false, 'admin', 'no change');

        $this->assertSame(
            1,
            AiSettingsAuditLog::where('key', 'ai_flags.ai_global_enabled')->count()
        );
    }

    public function test_flag_service_never_throws_and_falls_back_to_config(): void
    {
        $this->configFlags(['ocr']);

        // بدون row → null → config حاکم
        $this->assertNull(AiFlagService::value('ai_global_enabled'));

        $r = $this->gate->check('ocr', $this->bot->id, null, 5);
        $this->assertTrue($r['allowed']);
    }

    public function test_known_keys_cover_all_config_flags(): void
    {
        $keys = AiFlagService::knownKeys();

        foreach (['ai_global_enabled', 'ai_llm_enabled', 'ai_ocr_enabled',
                  'ai_asr_enabled', 'ai_rag_enabled', 'ai_tts_enabled',
                  'ai_growth_companion_enabled'] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    // ─────────────── Nova resource contracts ───────────────

    public function test_nova_ai_flag_resource_is_editable_and_binds_flag_model(): void
    {
        $this->assertTrue(class_exists(\App\Nova\AiFlag::class));

        $resource = new \App\Nova\AiFlag;
        $this->assertSame(\App\Models\AiFlag::class, \App\Nova\AiFlag::$model);
        $this->assertTrue(\App\Nova\AiFlag::$displayInNavigation ?? false);

        $req = $this->novaRequest();
        $fields = $resource->fields($req);
        $attrs  = array_map(fn ($f) => $f->attribute, $fields);
        $this->assertContains('key', $attrs);
        $this->assertContains('value', $attrs);

        // ویرایش‌پذیر (بدون re-deploy) — create/update مجاز
        $this->assertTrue($resource->authorizedToCreate($req));
        $this->assertTrue($resource->authorizedToUpdate($req));
    }

    public function test_nova_ai_job_resource_is_read_only_index(): void
    {
        $this->assertTrue(class_exists(\App\Nova\AiJob::class));

        $resource = new \App\Nova\AiJob;
        $this->assertSame(\App\Models\AiJob::class, \App\Nova\AiJob::$model);

        $req = $this->novaRequest();
        $fields = $resource->fields($req);
        $attrs  = array_map(fn ($f) => $f->attribute, $fields);
        foreach (['state', 'feature', 'bot_id', 'error_code', 'attempt_number', 'created_at'] as $attr) {
            $this->assertContains($attr, $attrs, "فیلد {$attr} در نمای jobs نیست");
        }

        // jobs از پنل ویرایش/ساخت نمی‌شوند (read-only view — brief §4)
        $this->assertFalse($resource->authorizedToCreate($req));
        $this->assertFalse($resource->authorizedToUpdate($req));
        $this->assertFalse($resource->authorizedToDelete($req));
    }

    public function test_nova_audit_log_resource_is_read_only(): void
    {
        $this->assertTrue(class_exists(\App\Nova\AiSettingsAuditLog::class));

        $resource = new \App\Nova\AiSettingsAuditLog;
        $this->assertSame(\App\Models\AiSettingsAuditLog::class, \App\Nova\AiSettingsAuditLog::$model);

        $req = $this->novaRequest();
        $fields = $resource->fields($req);
        $attrs  = array_map(fn ($f) => $f->attribute, $fields);
        foreach (['key', 'old_value', 'new_value', 'actor', 'reason', 'created_at'] as $attr) {
            $this->assertContains($attr, $attrs, "فیلد {$attr} در نمای audit log نیست");
        }

        $this->assertFalse($resource->authorizedToCreate($req));
        $this->assertFalse($resource->authorizedToUpdate($req));
        $this->assertFalse($resource->authorizedToDelete($req));
    }

    public function test_nova_bot_resource_exposes_rollout_stage_and_pro_override(): void
    {
        $this->assertTrue(class_exists(\App\Nova\Bot::class));

        $resource = new \App\Nova\Bot;
        $fields   = $resource->fields($this->novaRequest());

        $attrs = array_map(fn ($f) => $f->attribute, $fields);

        $this->assertContains('ai_rollout_stage', $attrs, 'فیلد stage rollout در Nova Bot نیست');
        $this->assertContains('ai_pro_override', $attrs, 'فیلد pro-override در Nova Bot نیست');
        $this->assertContains('ai_test_group', $attrs, 'فیلد allowlist در Nova Bot نیست');
    }
}
