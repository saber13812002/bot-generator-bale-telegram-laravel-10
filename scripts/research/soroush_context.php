<?php
$h = file_get_contents('C:\Users\s.tabatabaei\soroush_botplatform.html');
$keys = ['getUpdates', 'webhook', 'api', 'token', 'sendMessage', 'ربات', 'رویداد'];
$pos = [];
foreach ($keys as $k) {
    $i = 0;
    while (($i = strpos($h, $k, $i)) !== false && count($pos) < 60) {
        $pos[] = $i;
        $i += strlen($k);
    }
}
sort($pos);
// print a context window around each unique region
$printed = 0;
$last = -1000;
foreach ($pos as $p) {
    if ($p - $last < 800) continue;
    $last = $p;
    $start = max(0, $p - 300);
    $seg = substr($h, $start, 900);
    $seg = html_entity_decode(strip_tags($seg), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $seg = preg_replace('/\s+/', ' ', $seg);
    echo "---- @", $p, " ----\n", $seg, "\n";
    if (++$printed >= 25) break;
}
