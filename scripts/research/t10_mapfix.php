<?php
// T10: replace the stale origin/15esfand1404 row in PROJECT_MASTER_MAP.md
$file = __DIR__ . '/../../docs/PROJECT_MASTER_MAP.md';
$lines = file($file);
if ($lines === false) { fwrite(STDERR, "cannot read\n"); exit(1); }

$eol = (strpos(implode('', array_slice($lines, 0, 3)), "\r\n") !== false) ? "\r\n" : "\n";
// strip eol for processing
$raw = $lines;
$target = null;
for ($i = 0; $i < count($raw); $i++) {
    $line = $raw[$i];
    if (strpos($line, '`origin/15esfand1404`') !== false && strpos($line, 'cb5b8423') !== false) {
        $target = $i;
        break;
    }
}
if ($target === null) { fwrite(STDERR, "row not found\n"); exit(1); }

$nl = $eol;
$newRow = '| `origin/15esfand1404` | `64de7a3a` (T3) | 2026-11-10 | B | ';
$newRow .= "آخرین نسخهٔ push شده = T3 (multi-line contract). زنجیرهٔ ";
$newRow .= '`e66bd888` ← T1 ← T2 ← T3 روی GitHub است؛ T4–T9 محلی‌اند. |' . $nl;

$raw[$target] = $newRow;
if (file_put_contents($file, implode('', $raw)) === false) { fwrite(STDERR, "write failed\n"); exit(1); }
echo "OK replaced line " . ($target + 1) . "\n";
