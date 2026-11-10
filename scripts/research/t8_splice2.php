<?php
/**
 * T8 splice v2 — fixes all 6 test failures in SignatureScenariosTest.php
 *
 * Fixes:
 *   1. Scenario 1: $pure 'bale' n=1 → n=3 (line 79)
 *   2. Scenario 3: $bale 'bale' n=2 → n=3 (line 168)
 *   3. Scenario 4: $soroush 'soroush' n=3 → n=4 (line 209)
 *   4. expectedMulti bale branch: single-quoted ':\n[' → double-quoted ":\n[" (line 678)
 *   5. expectedMulti else branch: single-quoted ':\n' → double-quoted ":\n" (line 680)
 *   6. Scenario 7: <b> → <b> and </b> → </b> (line 312)
 *   7. Scenario 9: two Http::fake → single Http::sequence with both pushes (lines 432-434 + 462-465)
 *
 * All entity/escape strings built via chr() to avoid HTML-decoding by write_to_file.
 */

$file = 'tests/Unit/SignatureScenariosTest.php';
$lines = file($file);

$eol = (strpos($lines[0], "\r\n") !== false) ? "\r\n" : "\n";
$total = count($lines);
if ($total < 760) {
    fwrite(STDERR, "Unexpected line count: $total (expected >= 760)\n");
    exit(1);
}

$bs  = chr(92); // backslash
$q1  = chr(39); // single quote
$q2  = chr(34); // double quote
$amp = chr(38); // &
$gt  = chr(62); // >

// ── FIX 1: line 79 (idx 78) — $pure 'bale', 1 → 'bale', 3 ──
$l = $lines[78];
$needle1 = '$pure = $this->makeDest($bot->id, ' . $q1 . 'bale' . $q1 . ', 1, [';
if (strpos($l, $needle1) === false) {
    fwrite(STDERR, "FIX1: line 79 unexpected content: " . trim($l) . "\n");
    exit(1);
}
$lines[78] = str_replace("'bale', 1, [", "'bale', 3, [", $l);
echo "FIX1 OK: line 79 → " . trim($lines[78]) . "\n";

// ── FIX 2: line 168 (idx 167) — $bale 'bale', 2 → 'bale', 3 ──
$l = $lines[167];
$needle2 = '$bale = $this->makeDest($bot->id, ' . $q1 . 'bale' . $q1 . ', 2, [';
if (strpos($l, $needle2) === false) {
    fwrite(STDERR, "FIX2: line 168 unexpected content: " . trim($l) . "\n");
    exit(1);
}
$lines[167] = str_replace("'bale', 2, [", "'bale', 3, [", $l);
echo "FIX2 OK: line 168 → " . trim($lines[167]) . "\n";

// ── FIX 3: line 209 (idx 208) — $soroush 'soroush', 3 → 'soroush', 4 ──
$l = $lines[208];
$needle3 = '$soroush = $this->makeDest($bot->id, ' . $q1 . 'soroush' . $q1 . ', 3, [';
if (strpos($l, $needle3) === false) {
    fwrite(STDERR, "FIX3: line 209 unexpected content: " . trim($l) . "\n");
    exit(1);
}
$lines[208] = str_replace("'soroush', 3, [", "'soroush', 4, [", $l);
echo "FIX3 OK: line 209 → " . trim($lines[208]) . "\n";

// ── FIX 4: line 678 (idx 677) — ':\n[' → ":\n[" (literal backslash-n) ──
$l = $lines[677];
$old4 = $q1 . ':' . $bs . 'n[' . $q1;   // ':\n['  (single-quoted, literal \n)
$new4 = $q2 . ':' . $bs . 'n[' . $q2;   // ":\n["  (double-quoted, real newline)
$cnt4 = substr_count($l, $old4);
if ($cnt4 !== 1) {
    fwrite(STDERR, "FIX4: expected 1 match in line 678, got $cnt4. Line: " . trim($l) . "\n");
    exit(1);
}
$lines[677] = str_replace($old4, $new4, $l);
echo "FIX4 OK: line 678 → " . trim($lines[677]) . "\n";

// ── FIX 5: line 680 (idx 679) — ':\n' → ":\n" (literal backslash-n) ──
$l = $lines[679];
$old5 = $q1 . ':' . $bs . 'n' . $q1;    // ':\n'
$new5 = $q2 . ':' . $bs . 'n' . $q2;    // ":\n"
$cnt5 = substr_count($l, $old5);
if ($cnt5 !== 1) {
    fwrite(STDERR, "FIX5: expected 1 match in line 680, got $cnt5. Line: " . trim($l) . "\n");
    exit(1);
}
$lines[679] = str_replace($old5, $new5, $l);
echo "FIX5 OK: line 680 → " . trim($lines[679]) . "\n";

// ── FIX 6: line 312 (idx 311) — fix corrupted entities ──
$ltE    = $amp . 'lt;';   // <
$gtE    = $amp . 'gt;';   // >
$badOpen   = $ltE . 'b' . $gt;       // <b>   (corrupted: raw > instead of >)
$goodOpen  = $ltE . 'b' . $gtE;      // <b> (correct)
$badClose  = $ltE . '/b' . $gt;      // </b>  (corrupted)
$goodClose = $ltE . '/b' . $gtE;     // </b> (correct)

$l = $lines[311];
$cntO = substr_count($l, $badOpen);
$cntC = substr_count($l, $badClose);
if ($cntO !== 1 || $cntC !== 1) {
    fwrite(STDERR, "FIX6: expected 1 badOpen + 1 badClose in line 312, got $cntO + $cntC.\n");
    fwrite(STDERR, "Line 312: " . trim($l) . "\n");
    exit(1);
}
$l = str_replace($badOpen, $goodOpen, $l);
$l = str_replace($badClose, $goodClose, $l);
$lines[311] = $l;
echo "FIX6 OK: line 312 → " . trim($lines[311]) . "\n";

// ── FIX 7: Scenario 9 — single Http::sequence instead of two Http::fake ──
// Process from bottom to top to preserve line indices.

// Verify lines 462-465 (idx 461-464) are the second Http::fake block
if (strpos($lines[461], 'Http::fake') === false) {
    fwrite(STDERR, "FIX7: line 462 not Http::fake: " . trim($lines[461]) . "\n");
    exit(1);
}
if (strpos($lines[462], 'Http::sequence') === false) {
    fwrite(STDERR, "FIX7: line 463 not Http::sequence: " . trim($lines[462]) . "\n");
    exit(1);
}
if (strpos($lines[464], ']);') === false) {
    fwrite(STDERR, "FIX7: line 465 not closing bracket: " . trim($lines[464]) . "\n");
    exit(1);
}
// Remove lines 462-465 (idx 461-464, 4 lines)
array_splice($lines, 461, 4);
echo "FIX7a OK: removed lines 462-465 (second Http::fake)\n";

// Verify lines 432-434 (idx 431-433) are the first Http::fake block
if (strpos($lines[431], 'Http::fake') === false) {
    fwrite(STDERR, "FIX7: line 432 not Http::fake: " . trim($lines[431]) . "\n");
    exit(1);
}
if (strpos($lines[432], 'Http::response') === false) {
    fwrite(STDERR, "FIX7: line 433 not Http::response: " . trim($lines[432]) . "\n");
    exit(1);
}
if (strpos($lines[433], ']);') === false) {
    fwrite(STDERR, "FIX7: line 434 not closing bracket: " . trim($lines[433]) . "\n");
    exit(1);
}
// Replace lines 432-434 (idx 431-433, 3 lines) with sequence fake (5 lines)
$newFake = [
    '        Http::fake([' . $eol,
    '            ' . $q1 . 'eitaayar.ir*' . $q1 . ' => Http::sequence()' . $eol,
    '                ->push([' . $q1 . 'ok' . $q1 . ' => true, ' . $q1 . 'result' . $q1 . ' => [' . $q1 . 'message_id' . $q1 . ' => 555]])' . $eol,
    '                ->push([' . $q1 . 'ok' . $q1 . ' => true, ' . $q1 . 'result' . $q1 . ' => [' . $q1 . 'message_id' . $q1 . ' => 666]]),' . $eol,
    '        ]);' . $eol,
];
array_splice($lines, 431, 3, $newFake);
echo "FIX7b OK: replaced lines 432-434 with Http::sequence (555 + 666)\n";

// ── Write back ──
file_put_contents($file, implode('', $lines));
echo "DONE: all 7 fixes applied. New line count: " . count($lines) . "\n";
