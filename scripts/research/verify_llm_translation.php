<?php

/**
 * PRODUCTION-STABILIZATION-06 / Phase 3 — real LLM translation test.
 *
 * Runs the production code path end-to-end:
 *   TranslationService::call() -> LlmTranslationService::translate()
 *   -> selectHealthyProvider() -> AiProviderService::chat(timeout 20s)
 *
 * Usage: php scripts/research/verify_llm_translation.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AiProvider;
use App\Services\LlmTranslationService;

echo "=== AI provider table (local DB) ===" . PHP_EOL;
$providers = AiProvider::all();
if ($providers->isEmpty()) {
    echo "(no providers in local DB — selectHealthyProvider() will fall back to ensureDefaultProvider())" . PHP_EOL;
} else {
    foreach ($providers as $p) {
        echo "id={$p->id} name={$p->name} active=" . ($p->is_active ? '1' : '0')
            . " last_test_status=" . ($p->last_test_status ?? 'null')
            . " last_tested_at=" . ($p->last_tested_at ?? 'null') . PHP_EOL;
    }
}
echo PHP_EOL;

$text = 'امروز هوا خیلی خوب است و ما به بازار رفتیم.';
echo "Input text : {$text}" . PHP_EOL;
echo "Driver chain: TranslationService driver=" . config('translation.driver') . PHP_EOL;
echo PHP_EOL;

// 1) Direct LLM path
$t0 = microtime(true);
$llm = LlmTranslationService::translate($text, 'en');
$llmMs = round((microtime(true) - $t0) * 1000, 1);
echo "LlmTranslationService::translate(text, 'en'):" . PHP_EOL;
echo "  result  : " . ($llm === null ? 'NULL (skipped/failed)' : $llm) . PHP_EOL;
echo "  elapsed : {$llmMs} ms (timeout cap is 20000 ms)" . PHP_EOL;
echo PHP_EOL;

// 2) Full driver chain
$t0 = microtime(true);
$out = App\Services\TranslationService::call($text, 'en');
$chainMs = round((microtime(true) - $t0) * 1000, 1);
echo "TranslationService::call(text, 'en') [full driver chain]:" . PHP_EOL;
echo "  result  : " . (is_string($out) ? $out : var_export($out, true)) . PHP_EOL;
echo "  elapsed : {$chainMs} ms" . PHP_EOL;
echo PHP_EOL;

$ok = is_string($llm) && trim($llm) !== '' && stripos($llm, $text) === false;
echo $ok ? "RESULT: PASS — real LLM translation produced new text within the 20s cap." : "RESULT: FAIL — LLM translation returned null or the original text." . PHP_EOL;
