<?php
/**
 * T8 splice v3 — fixes remaining 3 failures:
 *   A. Scenarios 3+4: $tg 'telegram', 1 → 'telegram', 2 (2 occurrences)
 *      so channel_link matches TELEGRAM_URL (https://telegramlink.example/2)
 *   B. Scenario 7 line 346: assertion must expect the ESCAPED label
 *      (<b>ناقص</b>) because htmlspecialchars was applied.
 */

$file = 'tests/Unit/SignatureScenariosTest.php';
$lines = file($file);

$q1  = chr(39); // single quote
$amp = chr(38); // &
$lt  = chr(60); // <
$gt  = chr(62); // >
$gtE = $amp . 'gt;'; // >

// ── FIX A: both $tg makeDest lines: 'telegram', 1 → 'telegram', 2 ──
$oldA = '$tg = $this->makeDest($bot->id, ' . $q1 . 'telegram' . $q1 . ', 1, [';
$newA = '$tg = $this->makeDest($bot->id, ' . $q1 . 'telegram' . $q1 . ', 2, [';
$cnt = 0;
foreach ($lines as $i => $l) {
    if (strpos($l, $oldA) !== false) {
        $lines[$i] = str_replace($oldA, $newA, $l);
        $cnt++;
        echo "FIXA OK: line " . ($i + 1) . " → " . trim($lines[$i]) . "\n";
    }
}
if ($cnt !== 2) {
    fwrite(STDERR, "FIXA: expected 2 occurrences, got $cnt\n");
    exit(1);
}

// ── FIX B: scenario 7 line — raw <b> assertion → escaped-entity assertion ──
$rawLabel    = $lt . 'b' . $gt . 'ناقص' . $lt . '/b' . $gt; // <b>ناقص</b>
$escLabel    = $amp . 'lt;b' . $gtE . 'ناقص' . $amp . 'lt;/b' . $gtE; // <b>ناقص</b>
$oldB = 'assertStringContainsString(' . $q1 . $rawLabel . $q1;
$newB = 'assertStringContainsString(' . $q1 . $escLabel . $q1;
$cnt = 0;
foreach ($lines as $i => $l) {
    if (strpos($l, $oldB) !== false) {
        $lines[$i] = str_replace($oldB, $newB, $l);
        $cnt++;
        echo "FIXB OK: line " . ($i + 1) . " → " . trim($lines[$i]) . "\n";
    }
}
if ($cnt !== 1) {
    fwrite(STDERR, "FIXB: expected 1 occurrence, got $cnt\n");
    exit(1);
}

file_put_contents($file, implode('', $lines));
echo "DONE: v3 fixes applied.\n";
