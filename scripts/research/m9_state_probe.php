<?php

/**
 * MISSION 09 — Phase A state probe (read-only).
 *
 * Prints: bots table columns, Book Pixel / Growth Companion bot rows
 * (with token redaction), ai_jobs state counts, queue table, ai_providers,
 * ai_flags, and webhook URL hints. NO secrets are printed.
 *
 * Run: php scripts/research/m9_state_probe.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

function hr(string $title): void
{
    echo PHP_EOL . '===== ' . $title . ' =====' . PHP_EOL;
}

// 1. bots schema
hr('bots table columns');
$cols = DB::select('SHOW COLUMNS FROM bots');
foreach ($cols as $c) {
    echo sprintf("  %-28s %s", $c->Field, $c->Type . PHP_EOL);
}

// 2. candidate bot rows
hr('book pixel / growth companion bot rows');
$all = DB::table('bots')->get();
echo '  total bots: ' . count($all) . PHP_EOL;
foreach ($all as $b) {
    $row = $b->toArray();
    $aiSettings = null;
    if (isset($row['ai_settings']) && is_string($row['ai_settings']) && $row['ai_settings'] !== '') {
        $aiSettings = json_decode($row['ai_settings'], true);
    }
    $blob = json_encode($row, JSON_UNESCAPED_UNICODE);
    $match = preg_match('/book[_\s-]?pixel|growth|رشدیار|bookpixel/i', $blob);
    if (! $match) {
        // also check token-adjacent hint columns via json dump keys
        continue;
    }
    // redact anything that looks like a token
    foreach ($row as $k => $v) {
        if (is_string($v) && (str_contains(strtolower($k), 'token') || str_contains(strtolower($k), 'key'))) {
            $row[$k] = 'REDACTED(' . strlen($v) . ' chars)';
        }
    }
    echo '  bot_id=' . ($row['id'] ?? '?') . ': ' . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    if ($aiSettings) {
        echo '    ai_settings: ' . json_encode($aiSettings, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}

// 3. ai_jobs state counts
hr('ai_jobs state counts');
$states = DB::table('ai_jobs')->select('state', DB::raw('count(*) as c'))->groupBy('state')->orderBy('state')->get();
foreach ($states as $s) {
    echo '  ' . $s->state . ': ' . $s->c . PHP_EOL;
}
echo '  total ai_jobs: ' . DB::table('ai_jobs')->count() . PHP_EOL;
$byFeature = DB::table('ai_jobs')->select('feature', DB::raw('count(*) as c'))->groupBy('feature')->get();
foreach ($byFeature as $f) {
    echo '  feature ' . $f->feature . ': ' . $f->c . PHP_EOL;
}

// 4. database queue table
hr('database queue (driver=database) pending');
try {
    $q = DB::table('jobs')->count();
    echo '  jobs table rows: ' . $q . PHP_EOL;
    $failed = DB::table('failed_jobs')->count();
    echo '  failed_jobs rows: ' . $failed . PHP_EOL;
    $failedRecent = DB::table('failed_jobs')->orderByDesc('id')->limit(5)->get(['id', 'queue', 'created_at']);
    foreach ($failedRecent as $f) {
        echo '  failed#' . $f->id . ' queue=' . $f->queue . ' created=' . $f->created_at . PHP_EOL;
    }
} catch (Throwable $e) {
    echo '  queue tables not accessible: ' . $e->getMessage() . PHP_EOL;
}

// 5. ai_providers (no key!)
hr('ai_providers (key redacted)');
try {
    $providers = DB::table('ai_providers')->get();
    foreach ($providers as $p) {
        $row = $p->toArray();
        foreach ($row as $k => $v) {
            if (is_string($v) && (str_contains(strtolower($k), 'key'))) {
                $row[$k] = $v === '' ? 'EMPTY' : 'REDACTED(' . strlen($v) . ')';
            }
        }
        echo '  ' . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
} catch (Throwable $e) {
    echo '  error: ' . $e->getMessage() . PHP_EOL;
}

// 6. ai_flags
hr('ai_flags rows');
try {
    $flags = DB::table('ai_flags')->get();
    foreach ($flags as $fl) {
        echo '  ' . json_encode($fl->toArray(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    if (count($flags) === 0) {
        echo '  (empty — env/config fallback active)' . PHP_EOL;
    }
} catch (Throwable $e) {
    echo '  error: ' . $e->getMessage() . PHP_EOL;
}

// 7. growth assistant messages / book pixel ocr columns sanity
hr('growth_assistant_messages + ocr/asr columns');
try {
    echo '  growth_assistant_messages rows: ' . DB::table('growth_assistant_messages')->count() . PHP_EOL;
    echo '  book_page_scans rows: ' . DB::table('book_page_scans')->count() . PHP_EOL;
    echo '  book_page_scans with ocr_text: ' . DB::table('book_page_scans')->whereNotNull('ocr_text')->count() . PHP_EOL;
    echo '  book_page_voices rows: ' . DB::table('book_page_voices')->count() . PHP_EOL;
    echo '  book_page_voices with asr_text: ' . DB::table('book_page_voices')->whereNotNull('asr_text')->count() . PHP_EOL;
} catch (Throwable $e) {
    echo '  error: ' . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . 'DONE (read-only probe).' . PHP_EOL;
