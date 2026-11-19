<?php

/**
 * CP-RELEASE-GATE / F3 — trial-DB schema probe.
 * Prints the channel_poster_destinations columns (name/type/null/default/position),
 * row count, and a NULL/true/false sample of the 3 signature columns.
 * Usage: php scripts/cp_gate_schema_probe.php
 */

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = DB::select('SHOW FULL COLUMNS FROM channel_poster_destinations');
echo "=== COLUMNS (name | type | null | default | position) ===\n";
foreach ($cols as $c) {
    echo sprintf(
        "%-28s | %-34s | %-5s | %-12s | %s\n",
        $c->Field,
        $c->Type,
        $c->Null,
        $c->Default === null ? 'NULL' : (string) $c->Default,
        (string) $c->Extra
    );
}

$count = DB::table('channel_poster_destinations')->count();
echo "\n=== ROWS: {$count} ===\n";

$sample = DB::table('channel_poster_destinations')
    ->select('id', 'channel_link', 'signature_label', 'signature_type', 'signature_enabled')
    ->limit(10)
    ->get();

echo "\n=== SAMPLE (id | channel_link | sig_label | sig_type | sig_enabled) ===\n";
foreach ($sample as $r) {
    echo sprintf(
        "%-6s | %-30s | %-20s | %-10s | %s\n",
        $r->id,
        (string) $r->channel_link,
        (string) $r->signature_label,
        (string) $r->signature_type,
        var_export($r->signature_enabled, true)
    );
}

// NULL/0/1 distribution of signature_enabled (null = legacy row must stay null)
$dist = DB::table('channel_poster_destinations')
    ->selectRaw("SUM(signature_enabled IS NULL) AS n_null, SUM(signature_enabled = 1) AS n_true, SUM(signature_enabled = 0) AS n_false, COUNT(*) AS n_total")
    ->first();
echo "\n=== signature_enabled distribution: null={$dist->n_null} true={$dist->n_true} false={$dist->n_false} total={$dist->n_total} ===\n";

$types = DB::table('channel_poster_destinations')->selectRaw('signature_type, COUNT(*) c')->groupBy('signature_type')->get();
echo "=== signature_type distribution: " . $types->map(fn ($t) => "{$t->signature_type}:{$t->c}")->implode(', ') . " ===\n";

echo "\nPROBE_OK\n";
