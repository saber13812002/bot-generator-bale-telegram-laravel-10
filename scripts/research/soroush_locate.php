<?php
$h = file_get_contents('C:\Users\s.tabatabaei\soroush_botplatform.html');
echo 'raw: ', strlen($h), "\n";

foreach (['sendMessage', 'getUpdates', 'api.splus.ir', 'updateBotEndpoint'] as $k) {
    $i = strpos($h, $k);
    echo $k, ' @ ', $i, "\n";
}

// What surrounds the first sendMessage occurrence?
$i = strpos($h, 'sendMessage');
$start = max(0, $i - 500);
echo "=== around sendMessage ===\n", substr($h, $start, 1200), "\n";

// check structure: is there an <article> or <main>?
preg_match('/<main[^>]*>/', $h, $main);
echo "\n<main>: ", $main[0] ?? 'none', "\n";
// find where body content (non-script) is
$noScript = preg_replace('/<script\b.*?<\/script>/is', ' ', $h);
echo 'without scripts: ', strlen($noScript), "\n";
$noScript = preg_replace('/<style\b.*?<\/style>/is', ' ', $noScript);
echo 'without styles: ', strlen($noScript), "\n";
// strip tags to see what text remains outside scripts
$tt = strip_tags($noScript);
$tt = preg_replace('/\s+/', ' ', $tt);
echo 'visible text len: ', strlen($tt), "\n";
echo "visible text: ", substr($tt, 0, 800), "\n";
