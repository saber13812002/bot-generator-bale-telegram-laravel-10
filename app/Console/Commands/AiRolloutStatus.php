<?php

namespace App\Console\Commands;

use App\Models\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * AI-PILOT-ROLLOUT-05 (R3) — ماتریس rollout: bot × capability (brief §3/§4).
 *
 * برای هر bot:
 *  - stage effective (override bot یا stage سراسری)
 *  - allowlist (ai_test_group)
 *  - وضعیت هر capability با precedence:
 *      kill switch global off → off
 *      bot override on/off   → آن مقدار (L2)
 *      وگرنه flag سراسری     → مقدار env/config (L0.5/L1)
 *
 * همین precedence که AiFeatureGate اجرا می‌کند (L0.5 → L1 → L2).
 * خروجی: جدول خوانا + --json برای اسکریپت/داشبورد.
 */
class AiRolloutStatus extends Command
{
    protected $signature = 'ai:rollout-status {--json : خروجی به‌صورت JSON برای اسکریپت‌نویسی}';

    protected $description = 'ماتریس rollout: stage و وضعیت هر capability برای همه bots (brief §3)';

    private const CAPABILITIES = [
        'llm'              => 'ai_llm_enabled',
        'ocr'              => 'ai_ocr_enabled',
        'asr'              => 'ai_asr_enabled',
        'rag'              => 'ai_rag_enabled',
        'tts'              => 'ai_tts_enabled',
        'growth_companion' => 'ai_growth_companion_enabled',
    ];

    public function handle(): int
    {
        $missing = array_values(array_filter(
            ['bots', 'ai_jobs'],
            fn (string $t) => ! Schema::hasTable($t)
        ));

        if ($missing) {
            $this->error('❌ جدول(s) موجود نیست: ' . implode(', ', $missing) . ' — ابتدا migrate کنید.');
            return 2;
        }

        $report = $this->buildReport();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return 0;
        }

        $stageNames = [
            0 => 'Internal', 1 => 'Pilot', 2 => 'All Pro', 3 => 'Public',
        ];

        $this->line('=== وضعیت Rollout AI (brief §3) ===');
        $this->line(sprintf(
            '  Stage سراسری: %d (%s) — flags سراسری: %s',
            $report['global_stage'],
            $stageNames[$report['global_stage']] ?? '?',
            json_encode($report['global_flags'], JSON_UNESCAPED_UNICODE)
        ));
        $this->line('');

        foreach ($report['bots'] as $bot) {
            $this->line(sprintf(
                '  🤖 Bot #%d (%s) — stage: %s — allowlist: %s',
                $bot['bot_id'],
                $bot['name'] ?? '?',
                $bot['stage_label'],
                $bot['allowlist'] ?: '—'
            ));
            foreach ($bot['capabilities'] as $cap => $status) {
                $marker = $status === 'on' ? '🟢' : '🔴';
                $this->line(sprintf('      %s %-18s %s', $marker, $cap, $status));
            }
            $this->line('');
        }

        $this->line('precedence: kill switch global → bot override → flag سراسری (همان لایه‌های AiFeatureGate).');

        return 0;
    }

    /**
     * @return array
     */
    private function buildReport(): array
    {
        $flags = (array) config('ai.flags', []);
        $globalStage = (int) config('ai.rollout.stage', 0);
        $killSwitch  = (bool) ($flags['ai_global_enabled'] ?? false);

        $stageNames = [0 => 'Internal', 1 => 'Pilot', 2 => 'All Pro', 3 => 'Public'];

        $bots = [];
        foreach (Bot::orderBy('id')->get(['id', 'telegram_bot_name', 'ai_settings']) as $bot) {
            $settings = $bot->ai_settings ?? [];
            $stage    = $bot->aiRolloutStage() ?? $globalStage;

            $capabilities = [];
            foreach (self::CAPABILITIES as $cap => $flagKey) {
                $botOverride = $bot->aiFlag($cap); // ?bool: true|false|null (follow)

                if (! $killSwitch) {
                    $status = 'off';                    // L0: kill switch
                } elseif ($botOverride !== null) {
                    $status = $botOverride ? 'on' : 'off'; // L2: bot override
                } else {
                    $status = (bool) ($flags[$flagKey] ?? false) ? 'on' : 'off'; // L0.5/L1: global
                }
                $capabilities[$cap] = $status;
            }

            $bots[] = [
                'bot_id'       => $bot->id,
                'name'         => $bot->telegram_bot_name,
                'stage'        => $stage,
                'stage_label'  => $stage . ' (' . ($stageNames[$stage] ?? '?') . ')',
                'allowlist'    => implode(', ', (array) $bot->aiTestGroup()),
                'capabilities' => $capabilities,
            ];
        }

        return [
            'global_stage' => $globalStage,
            'global_flags' => array_map(fn ($v) => (bool) $v, $flags),
            'bots' => $bots,
        ];
    }
}
