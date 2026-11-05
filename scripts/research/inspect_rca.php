<?php
/**
 * READ-ONLY RCA inspection for CP-DUPLICATE-SIGNATURE-RCA.
 * No writes. Run: php scripts/research/inspect_rca.php [mode]
 *   mode: queue (default) | dest | versions | both
 */

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mode = $argv[1] ?? 'both';

function section(string $title): void
{
    echo PHP_EOL . '══════ ' . $title . ' ' . str_repeat('═', max(0, 60 - mb_strlen($title))) . PHP_EOL;
}

if (in_array($mode, ['queue', 'both'], true)) {
    section('RECENT channel_poster_queue (last 12)');
    $rows = DB::table('channel_poster_queue')
        ->orderByDesc('id')
        ->limit(12)
        ->get();

    foreach ($rows as $q) {
        echo PHP_EOL;
        echo "--- queue id={$q->id} bot={$q->bot_id} tag=" . var_export($q->tag, true) . PHP_EOL;
        echo 'status=' . $q->status . ' content_type=' . $q->content_type
            . ' signature_enabled=' . var_export($q->signature_enabled, true)
            . ' content_id=' . var_export($q->content_id, true)
            . ' source_attribution=' . var_export($q->source_attribution, true)
            . ' updated_at=' . var_export($q->updated_at ?? null, true) . PHP_EOL;

        $text = (string) ($q->text ?? '');
        echo 'TEXT(len=' . mb_strlen($text) . '):' . PHP_EOL;
        echo mb_substr($text, 0, 1500) . (mb_strlen($text) > 1500 ? ' …[TRUNC]' : '') . PHP_EOL;

        $sel = $q->signature_selection ?? null;
        $sel = is_string($sel) ? json_decode($sel, true) : $sel;
        echo 'signature_selection: ' . var_export($sel, true) . PHP_EOL;
    }
}

if (in_array($mode, ['dest', 'both'], true)) {
    section('channel_poster_destinations (active, signature-related cols)');
    $rows = DB::table('channel_poster_destinations')
        ->where('is_active', true)
        ->orderBy('bot_id')
        ->orderBy('platform')
        ->get();

    foreach ($rows as $d) {
        $settings = $d->signature_settings ?? null;
        $settings = is_string($settings) ? json_decode($settings, true) : $settings;
        echo PHP_EOL . "bot={$d->bot_id} id={$d->id} platform={$d->platform} tag=" . var_export($d->tag, true)
            . ' title=' . var_export($d->channel_title, true)
            . ' link=' . var_export($d->channel_link, true)
            . ' username=' . var_export($d->channel_username, true)
            . ' sig_enabled=' . var_export($d->signature_enabled, true)
            . ' sig_type=' . var_export($d->signature_type, true)
            . ' sig_label=' . var_export($d->signature_label, true) . PHP_EOL;
        if ($settings !== null) {
            echo '  signature_settings: ' . json_encode($settings, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
    }
}

if (in_array($mode, ['versions', 'both'], true)) {
    section('RECENT content_sync_versions (last 15)');
    $rows = DB::table('content_sync_versions')
        ->orderByDesc('id')
        ->limit(15)
        ->get();

    foreach ($rows as $v) {
        echo 'content=' . $v->content_id . ' dest=' . var_export($v->destination_id, true)
            . ' status=' . $v->publish_status
            . ' queue_id=' . var_export($v->queue_id, true)
            . ' msg=' . var_export($v->message_id, true)
            . ' post_url=' . var_export($v->post_url, true)
            . ' created=' . var_export($v->created_at, true) . PHP_EOL;
    }

    section('RECENT content_sync_contents (last 6)');
    $rows = DB::table('content_sync_contents')
        ->orderByDesc('id')
        ->limit(6)
        ->get();

    foreach ($rows as $c) {
        $text = (string) ($c->text ?? '');
        echo PHP_EOL . 'content id=' . $c->id . ' bot=' . $c->bot_id
            . ' source_platform=' . var_export($c->source_platform, true)
            . ' source_url=' . var_export($c->source_url, true)
            . ' source_channel=' . var_export($c->source_channel_id, true) . PHP_EOL;
        echo 'TEXT(len=' . mb_strlen($text) . '):' . PHP_EOL;
        echo mb_substr($text, 0, 1200) . (mb_strlen($text) > 1200 ? ' …[TRUNC]' : '') . PHP_EOL;
    }
}

echo PHP_EOL . 'DONE (read-only).';
