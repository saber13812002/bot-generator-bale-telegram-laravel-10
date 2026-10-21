<?php

/**
 * Phase 3: add Soroush Plus + Rubika lang keys to every locale's bot.php.
 *
 * Idempotent: skips files that already contain all keys.
 * Anchors (verified present in all 15 locales):
 *   - 'channel_poster_btn_soroush'   -> insert btn_rubika after it
 *   - 'channel_poster_sig_soroush'   -> insert sig_rubika after it (fallback: after btn_rubika line)
 *   - 'channel_poster_ask_eitaa_chat_id' -> insert 4 ask_* keys after it
 *   - 'channel_poster_eitaa_connected'   -> insert 2 *_connected keys after it
 */

$fa = [
    'channel_poster_btn_rubika' => 'روبیکا',
    'channel_poster_sig_rubika' => 'در روبیکا',
    'channel_poster_ask_soroush_token' => 'توکن ربات سروش را بفرست. همان ربات را ادمین کانال سروش کن.',
    'channel_poster_ask_soroush_chat_id' => 'شناسه کانال سروش را بفرست.',
    'channel_poster_ask_rubika_token' => 'توکن ربات روبیکا را بفرست.',
    'channel_poster_ask_rubika_chat_id' => 'شناسه کانال روبیکا را بفرست.',
    'channel_poster_soroush_connected' => 'کانال سروش به تگ «:tag» وصل شد.',
    'channel_poster_rubika_connected' => 'کانال روبیکا به تگ «:tag» وصل شد.',
];

// All non-fa locales follow the repo convention: English strings
// (zh-CN reuses the en strings per existing convention).
$en = [
    'channel_poster_btn_rubika' => 'Rubika',
    'channel_poster_sig_rubika' => 'Rubika',
    'channel_poster_ask_soroush_token' => 'Send the Soroush bot token. Make that bot an admin of the Soroush channel.',
    'channel_poster_ask_soroush_chat_id' => 'Send the Soroush channel id.',
    'channel_poster_ask_rubika_token' => 'Send the Rubika bot token.',
    'channel_poster_ask_rubika_chat_id' => 'Send the Rubika channel id.',
    'channel_poster_soroush_connected' => 'Soroush channel linked to tag ":tag".',
    'channel_poster_rubika_connected' => 'Rubika channel linked to tag ":tag".',
];

$locales = [
    'ar-IQ', 'az', 'bs', 'de-DE', 'en', 'es', 'fa', 'fr',
    'he', 'pt-BR', 'pt-PT', 'ru', 'tr', 'ur', 'zh-CN',
];

function escapeValue(string $value): string
{
    // Lang files use single-quoted PHP strings; escape backslash + single quote.
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
}

function insertAfterLine(array $lines, int $anchorIndex, array $newKeys): array
{
    $out = array_slice($lines, 0, $anchorIndex + 1);
    foreach ($newKeys as $k => $v) {
        $out[] = "    '{$k}' => " . escapeValue($v) . ',';
    }
    $out = array_merge($out, array_slice($lines, $anchorIndex + 1));
    return $out;
}

function findLine(array $lines, string $needle): int
{
    foreach ($lines as $i => $line) {
        if (str_contains($line, $needle)) {
            return $i;
        }
    }
    return -1;
}

$report = [];

foreach ($locales as $locale) {
    $path = __DIR__ . '/../../lang/' . $locale . '/bot.php';
    if (! is_file($path)) {
        $report[$locale] = 'MISSING FILE';
        continue;
    }
    $content = file_get_contents($path);
    $values = $locale === 'fa' ? $fa : $en;

    // Idempotency: all keys already present?
    $missing = [];
    foreach (array_keys($values) as $key) {
        if (! str_contains($content, "'{$key}' =>")) {
            $missing[] = $key;
        }
    }
    if ($missing === []) {
        $report[$locale] = 'already complete (0 added)';
        continue;
    }

    $lines = explode("\n", rtrim($content, "\n"));

    // 1) btn_rubika after btn_soroush
    $i = findLine($lines, "'channel_poster_btn_soroush' =>");
    if ($i >= 0 && ! str_contains(implode("\n", $lines), "'channel_poster_btn_rubika' =>")) {
        $lines = insertAfterLine($lines, $i, ['channel_poster_btn_rubika' => $values['channel_poster_btn_rubika']]);
    }

    // 2) sig_rubika after sig_soroush (fallback: after btn_rubika line)
    if (! str_contains(implode("\n", $lines), "'channel_poster_sig_rubika' =>")) {
        $i = findLine($lines, "'channel_poster_sig_soroush' =>");
        if ($i < 0) {
            $i = findLine($lines, "'channel_poster_btn_rubika' =>");
        }
        if ($i >= 0) {
            $lines = insertAfterLine($lines, $i, ['channel_poster_sig_rubika' => $values['channel_poster_sig_rubika']]);
        }
    }

    // 3) four ask_* keys after ask_eitaa_chat_id
    $askKeys = [
        'channel_poster_ask_soroush_token' => $values['channel_poster_ask_soroush_token'],
        'channel_poster_ask_soroush_chat_id' => $values['channel_poster_ask_soroush_chat_id'],
        'channel_poster_ask_rubika_token' => $values['channel_poster_ask_rubika_token'],
        'channel_poster_ask_rubika_chat_id' => $values['channel_poster_ask_rubika_chat_id'],
    ];
    $todo = array_filter($askKeys, fn ($k) => ! str_contains(implode("\n", $lines), "'{$k}' =>"));
    if ($todo !== []) {
        $i = findLine($lines, "'channel_poster_ask_eitaa_chat_id' =>");
        if ($i >= 0) {
            $lines = insertAfterLine($lines, $i, $todo);
        }
    }

    // 4) two *_connected keys after eitaa_connected
    $connKeys = [
        'channel_poster_soroush_connected' => $values['channel_poster_soroush_connected'],
        'channel_poster_rubika_connected' => $values['channel_poster_rubika_connected'],
    ];
    $todo = array_filter($connKeys, fn ($k) => ! str_contains(implode("\n", $lines), "'{$k}' =>"));
    if ($todo !== []) {
        $i = findLine($lines, "'channel_poster_eitaa_connected' =>");
        if ($i >= 0) {
            $lines = insertAfterLine($lines, $i, $todo);
        }
    }

    file_put_contents($path, implode("\n", $lines) . "\n");
    $added = count($missing);
    $report[$locale] = "{$added} key(s) added";
}

echo "=== add_rubika_lang_keys report ===\n";
foreach ($report as $locale => $status) {
    printf("%-8s %s\n", $locale, $status);
}

// Syntax check every modified file.
echo "\n=== php -l ===\n";
$ok = true;
foreach ($locales as $locale) {
    $path = __DIR__ . '/../../lang/' . $locale . '/bot.php';
    exec('C:\php855\php.exe -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    $line = $out[0] ?? '';
    if ($code !== 0 || ! str_contains($line, 'No syntax errors')) {
        $ok = false;
        echo "FAIL {$locale}: {$line}\n";
    }
}
echo $ok ? 'All 15 locale files pass php -l' : 'SYNTAX FAILURES present';
echo "\n";
