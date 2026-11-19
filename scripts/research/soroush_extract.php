<?php
// Extract the full embedded document from the Soroush Next.js page.
// The doc HTML is embedded as JSON-escaped strings inside the page payload.
$h = file_get_contents('C:\Users\s.tabatabaei\soroush_botplatform.html');

// 1) Find the largest region between the first "ارسال درخواست" and the last "file/bot"
//    Actually, just pull ALL JSON-escaped string chunks that look like doc content:
//    they are of the form "....\u003c span ..." or contain Persian + HTML tags.
preg_match_all('/"((?:[^"\\\\]|\\\\.){200,})"/', $h, $m);
$blobs = $m[1];
echo 'candidate blobs: ', count($blobs), "\n";

$decoded = [];
foreach ($blobs as $b) {
    // decode JSON string escapes safely
    $d = json_decode('"' . $b . '"', true);
    if (!is_string($d)) {
        continue;
    }
    if (strpos($d, '<') === false && strpos($d, 'سروش') === false && strpos($d, 'متد') === false && strpos($d, 'bot') === false) {
        continue;
    }
    $decoded[] = $d;
}
echo 'doc blobs: ', count($decoded), "\n";

// 2) The doc is one big HTML string (possibly split). Sort by length desc and take the biggest ones.
usort($decoded, fn ($a, $b) => strlen($b) - strlen($a));
$big = array_slice($decoded, 0, 5);
foreach ($big as $i => $d) {
    file_put_contents("C:\Users\s.tabatabaei\soroush_blob_$i.html", $d);
    echo "blob_$i: ", strlen($d), " bytes\n";
}
