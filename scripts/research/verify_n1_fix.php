<?php

/**
 * PRODUCTION-STABILIZATION-06 / Phase 4 — verify the calculateRanking() N+1 fix.
 *
 * Compares the OLD algorithm (2 COUNT queries per user) against the NEW
 * algorithm (single GROUP BY aggregate) on the real local database, and
 * reports row counts, timing, and the query plan.
 *
 * Usage: php scripts/research/verify_n1_fix.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BotLog;
use Carbon\Carbon;

$pdo = DB::connection()->getPdo();
$one = function (string $sql) use ($pdo): array {
    return $pdo->query($sql)->fetch() ?: [];
};

// ---------- Synthetic seed (local dev DB is empty) ----------
// Create test data so old vs new algorithms can be compared on a non-trivial
// dataset. Uses chat_id 990000001..990000040 (bigInteger column) with a
// distinct marker so real data is never affected.
$seedStart = microtime(true);
DB::table('bot_logs')->whereBetween('chat_id', [990000001, 990000040])->delete();

$pdo2 = DB::connection()->getPdo();
$stmtIns = $pdo2->prepare("INSERT INTO bot_logs (chat_id, webhook_endpoint_uri, language, type, text, created_at) VALUES (?, 'webhook-quran-word', 'fa', ?, 'n1test', ?)");
$now = Carbon::now();
$seededRows = 0;
$cidList = [];
for ($u = 1; $u <= 40; $u++) {
    $cid = 990000000 + $u;
    $cidList[] = $cid;
    // 5..300 rows per user across the last 70 days (some older than 60d to be excluded)
    $rows = mt_rand(5, 300);
    for ($r = 0; $r < $rows; $r++) {
        $daysAgo = mt_rand(0, 70);
        $created = $now->copy()->subDays($daysAgo)->startOfDay()->addSeconds(mt_rand(0, 86399));
        $stmtIns->execute([$cid, mt_rand(0, 1) ? 'bale' : 'telegram', $created->toDateTimeString()]);
        $seededRows++;
    }
    // non-quran rows that must be ignored by both algorithms
    DB::table('bot_logs')->insert([
        'chat_id' => $cid, 'webhook_endpoint_uri' => 'webhook-weather', 'language' => 'fa',
        'type' => 'bale', 'text' => 'n1test', 'created_at' => $now->copy()->subDays(5)->toDateTimeString(),
    ]);
    $seededRows++;
}
echo "Seeded {$seededRows} rows (40 users, chat_id 990000001..990000040) in " . round((microtime(true) - $seedStart) * 1000, 1) . " ms." . PHP_EOL;
echo PHP_EOL;

$totalRows = $one('SELECT COUNT(*) c FROM bot_logs')['c'];
$quranRows = $one("SELECT COUNT(*) c FROM bot_logs WHERE webhook_endpoint_uri='webhook-quran-word'")['c'];
$distinctUsers = $one("SELECT COUNT(DISTINCT chat_id) c FROM bot_logs WHERE webhook_endpoint_uri='webhook-quran-word'")['c'];
$recentRows = $one("SELECT COUNT(*) c FROM bot_logs WHERE webhook_endpoint_uri='webhook-quran-word' AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)")['c'];

echo "=== Table sizes ===" . PHP_EOL;
echo "bot_logs total rows          : {$totalRows}" . PHP_EOL;
echo "webhook-quran-word rows      : {$quranRows}" . PHP_EOL;
echo "distinct quran-word users    : {$distinctUsers}" . PHP_EOL;
echo "quran-word rows last 60 days : {$recentRows}" . PHP_EOL;
echo PHP_EOL;

// The exact user set /report works on (same query as generateReportThenSend),
// scoped to the seeded chat_ids so only our test rows are considered.
$logs = BotLog::whereLanguage('fa')->whereWebhookEndpointUri('webhook-quran-word')
    ->whereIn('chat_id', $cidList)
    ->select('chat_id', 'type')->distinct('chat_id')->get();
$chatIds = $logs->pluck('chat_id')->unique()->values();
$n = $chatIds->count();
echo "=== /report user set (fa, distinct chat_id) ===" . PHP_EOL;
echo "users: {$n}" . PHP_EOL;
echo PHP_EOL;

if ($n === 0) {
    echo "No users in local DB to compare — skipping algorithm comparison." . PHP_EOL;
    exit(0);
}

$cutoffMonth = Carbon::now()->subDay(30);
$cutoffLastMonth = Carbon::now()->subDay(60);
$monthStr = $cutoffMonth->toDateTimeString();
$lastMonthStr = $cutoffLastMonth->toDateTimeString();

// ---------- OLD algorithm (reproduced faithfully, 2 COUNTs per user) ----------
$oldStart = microtime(true);
$oldQueries = 0;
$oldMonth = [];
$oldLast = [];
foreach ($chatIds as $cid) {
    $a = BotLog::where('chat_id', $cid)->where('webhook_endpoint_uri', 'webhook-quran-word')
        ->where('created_at', '>=', $cutoffMonth)->count();
    $b = BotLog::where('chat_id', $cid)->where('webhook_endpoint_uri', 'webhook-quran-word')
        ->where('created_at', '>=', $cutoffLastMonth)
        ->where('created_at', '<', $cutoffMonth)->count();
    $oldMonth[$cid] = (int) $a;
    $oldLast[$cid] = (int) $b;
    $oldQueries += 2;
}
$oldElapsed = microtime(true) - $oldStart;

// ---------- NEW algorithm (single GROUP BY aggregate, as in calculateRanking) ----------
$newStart = microtime(true);
$aggregates = BotLog::where('webhook_endpoint_uri', 'webhook-quran-word')
    ->whereIn('chat_id', $chatIds)
    ->where('created_at', '>=', $cutoffLastMonth)
    ->selectRaw('chat_id, COUNT(*) as cnt, SUM(created_at >= ?) as cnt_month', [$monthStr])
    ->groupBy('chat_id')
    ->get();
$newElapsed = microtime(true) - $newStart;

$newMonth = $aggregates->mapWithKeys(fn ($row) => [(string) $row->chat_id => (int) $row->cnt_month]);
$newLast = $aggregates->mapWithKeys(fn ($row) => [(string) $row->chat_id => (int) ($row->cnt - $row->cnt_month)]);

echo "=== Timing ===" . PHP_EOL;
echo "OLD: {$oldQueries} COUNT queries, " . round($oldElapsed * 1000, 1) . " ms" . PHP_EOL;
echo "NEW: 1 aggregate query,   " . round($newElapsed * 1000, 1) . " ms" . PHP_EOL;
echo PHP_EOL;

// ---------- Correctness comparison ----------
$mismatch = 0;
foreach ($chatIds as $cid) {
    $cid = (string) $cid;
    if ((int) $oldMonth[$cid] !== $newMonth->get($cid, 0) || (int) $oldLast[$cid] !== $newLast->get($cid, 0)) {
        $mismatch++;
        echo "MISMATCH user {$cid}: old(month={$oldMonth[$cid]}, last={$oldLast[$cid]}) vs new(month={$newMonth->get($cid,0)}, last={$newLast->get($cid,0)})" . PHP_EOL;
    }
}
echo "=== Correctness ===" . PHP_EOL;
echo "Compared {$n} users; mismatches: {$mismatch}" . PHP_EOL;
echo PHP_EOL;

// ---------- Query plan for the new aggregate ----------
echo "=== EXPLAIN (new aggregate) ===" . PHP_EOL;
$explains = DB::select("EXPLAIN SELECT chat_id, COUNT(*) as cnt, SUM(created_at >= ?) as cnt_month FROM bot_logs WHERE webhook_endpoint_uri = 'webhook-quran-word' AND created_at >= ? AND chat_id IN (" . $chatIds->map(fn ($c) => DB::connection()->getPdo()->quote($c))->implode(',') . ") GROUP BY chat_id", [$monthStr, $lastMonthStr]);
foreach ($explains as $e) {
    echo "table={$e->table} type={$e->type} key={$e->key} rows=" . ($e->rows ?? '?') . " Extra={$e->Extra}" . PHP_EOL;
}

echo PHP_EOL;
echo ($mismatch === 0 ? "RESULT: PASS — new aggregate matches old per-user counts exactly." : "RESULT: FAIL — output divergence detected.") . PHP_EOL;

// ---------- Cleanup seeded rows ----------
$deleted = DB::table('bot_logs')->whereBetween('chat_id', [990000001, 990000040])->delete();
echo "Cleanup: removed {$deleted} seeded rows." . PHP_EOL;
