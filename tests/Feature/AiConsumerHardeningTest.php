<?php

namespace Tests\Feature;

use App\Interfaces\Services\GrowthLlmProvider;
use App\Models\AiProvider;
use App\Models\Bot;
use App\Services\Ai\AiFeatureGate;
use App\Services\AiProviderService;
use App\Services\HttpGrowthLlmProvider;
use App\Services\NullGrowthLlmProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WS-J: Harden existing AI consumers.
 *
 *  - J1: لایه‌ی L5 دروازه — strict mode (ai.require_active_provider):
 *        بدون provider row فعال → deny 'provider_missing'
 *        (قبلاً safe-by-default pass؛ در تست‌ها پیش‌فرض false می‌ماند).
 *  - J2: انتخاب مشترک provider سالم (AiProviderService::healthyProvider)
 *        برای همه consumerها — provider «failed» هزینه‌ی HTTP اضافی ندارد.
 *  - J3: GrowthLlmProvider بدون کلید اختصاصی → central LiteLLM
 *        (قبلاً NullGrowthLlmProvider → variants خالی).
 */
class AiConsumerHardeningTest extends TestCase
{
    use RefreshDatabase;

    private AiFeatureGate $gate;
    private Bot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gate = new AiFeatureGate;
        $this->bot  = Bot::create(['telegram_bot_name' => 'hardening-test-bot']);

        // 15:00 تهران — خارج از پنجره‌ی deferral (L4 → L5 می‌رسد)
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));
        AiFeatureGate::clearHealthCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
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

    private function makeProvider(array $attrs = []): AiProvider
    {
        return AiProvider::create(array_merge([
            'name'      => 'litellm',
            'is_active' => true,
            'base_url'  => 'https://ai.example.test',
            'api_key'   => 'sk-test',
        ], $attrs));
    }

    // ─────────────────────────── J1: strict L5 ───────────────────────────

    public function test_strict_gate_no_provider_row_denies_provider_missing(): void
    {
        $this->flags(['ocr']);
        Config::set('ai.require_active_provider', true);
        AiFeatureGate::clearHealthCache();

        $r = $this->gate->check('ocr', $this->bot->id);

        $this->assertFalse($r['allowed']);
        $this->assertSame('provider_missing', $r['reason']);
    }

    public function test_strict_gate_healthy_provider_allows(): void
    {
        $this->flags(['ocr']);
        Config::set('ai.require_active_provider', true);
        $this->makeProvider();
        Http::fake(fn () => Http::response(['data' => []], 200));
        AiFeatureGate::clearHealthCache();

        $r = $this->gate->check('ocr', $this->bot->id);

        $this->assertTrue($r['allowed']);
        $this->assertSame('ok', $r['reason']);
    }

    public function test_non_strict_gate_no_provider_row_passes(): void
    {
        $this->flags(['ocr']);
        Config::set('ai.require_active_provider', false);
        AiFeatureGate::clearHealthCache();

        $r = $this->gate->check('ocr', $this->bot->id);

        $this->assertTrue($r['allowed'], 'بدون provider row + non-strict → pass (پیش‌فرض تست)');
        $this->assertSame('ok', $r['reason']);
    }

    // ─────────────────── J2: shared healthy provider selection ───────────────────

    public function test_healthy_provider_helper_skips_failed(): void
    {
        $failed = $this->makeProvider(['name' => 'failed-1', 'last_test_status' => 'failed']);
        $ok     = $this->makeProvider(['name' => 'ok-2', 'last_test_status' => null]);

        $selected = AiProviderService::healthyProvider();

        $this->assertNotNull($selected);
        $this->assertSame($ok->id, $selected->id);
        $this->assertNotSame($failed->id, $selected->id);
    }

    public function test_healthy_provider_helper_empty_table_ensures_default(): void
    {
        $selected = AiProviderService::healthyProvider();

        $this->assertNotNull($selected);
        $this->assertSame((string) config('ai.default_base_url'), $selected->base_url);
        $this->assertTrue(AiProvider::where('id', $selected->id)->exists());
    }

    // ─────────────────── J3: growth LLM central fallback ───────────────────

    public function test_growth_binding_falls_back_to_central_litellm(): void
    {
        Config::set('growth.llm.api_key', null);
        Config::set('ai.default_api_key', 'sk-central');

        $provider = app(GrowthLlmProvider::class);

        $this->assertInstanceOf(HttpGrowthLlmProvider::class, $provider);
    }

    public function test_growth_binding_null_when_no_keys_anywhere(): void
    {
        Config::set('growth.llm.api_key', null);
        Config::set('ai.default_api_key', null);

        $provider = app(GrowthLlmProvider::class);

        $this->assertInstanceOf(NullGrowthLlmProvider::class, $provider);
    }
}
