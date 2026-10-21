<?php
// Usage: php extract_html.php <input.html> <output.txt>
$in  = $argv[1];
$out = $argv[2];
$h = file_get_contents($in);
$h = preg_replace('/<script.*?<\/script>/is', ' ', (string) $h);
$h = preg_replace('/<style.*?<\/style>/is', ' ', $h);
// keep <br> as newlines
$h = preg_replace('/<br\s*\/?>/i', "\n", $h);
$t = html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$t = preg_replace('/[ \t]+/', ' ', $t);
$t = preg_replace('/\n\s*\n+/', "\n\n", $t);
file_put_contents($out, $t);
echo 'chars: ', strlen($t), "\n";
