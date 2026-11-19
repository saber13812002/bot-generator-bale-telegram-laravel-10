<?php
// t7_sync_flow_counts.php — bump the two remaining owner-menu row counts
// (9 -> 10) in tests/Feature/ContentSyncFlowTest.php (CP-DELETE-PURELINK T7
// added the 'cp:dpl' owner row).

$file = dirname(__DIR__, 2) . '/tests/Feature/ContentSyncFlowTest.php';
$src = file_get_contents($file);
if ($src === false) {
    fwrite(STDERR, "cannot read $file\n");
    exit(1);
}

$needle = '$this->assertCount(9, $msg[\'keyboard\']);';
if (substr_count($src, $needle) !== 2) {
    fwrite(STDERR, "expected exactly 2 occurrences, found " . substr_count($src, $needle) . "\n");
    exit(1);
}

$src = str_replace($needle, '$this->assertCount(10, $msg[\'keyboard\']);', $src);

if (file_put_contents($file, $src) === false) {
    fwrite(STDERR, "cannot write $file\n");
    exit(1);
}

echo "OK: 2 owner-menu row counts updated 9 -> 10\n";
