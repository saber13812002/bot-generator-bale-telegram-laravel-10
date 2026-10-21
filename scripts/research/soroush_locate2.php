<?php
$h = file_get_contents('C:\Users\s.tabatabaei\soroush_botplatform.html');

// all script tag boundaries
preg_match_all('/<script\b[^>]*>/i', $h, $open, PREG_OFFSET_CAPTURE);
$close = [];
$pos = 0;
while (($pos = strpos($h, '</script>', $pos)) !== false) {
    $close[] = $pos;
    $pos += 8;
}
echo 'open tags: ', count($open[0]), " close tags: ", count($close), "\n";

// for the offset of 'api.splus.ir' (104328), find enclosing script
$target = 104328;
$enclosing = null;
foreach ($open[0] as $o) {
    $op = $o[1];
    if ($op < $target) {
        $enclosing = $op;
    } else {
        break;
    }
}
echo 'enclosing script open at: ', $enclosing, "\n";
echo 'open tag: ', substr($h, $enclosing, 120), "\n";
// next close after target
$nextClose = null;
foreach ($close as $c) {
    if ($c > $target) { $nextClose = $c; break; }
}
echo 'next close: ', $nextClose, "\n";
$segLen = $nextClose - $enclosing;
echo 'container length: ', $segLen, "\n";
file_put_contents('C:\Users\s.tabatabaei\soroush_container.txt', substr($h, $enclosing, $segLen));
