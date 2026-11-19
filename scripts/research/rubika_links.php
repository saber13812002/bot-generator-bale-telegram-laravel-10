<?php
$h = file_get_contents('C:\Users\s.tabatabaei\rubika_botapi.html');
preg_match_all('/href="([^"]+)"/', $h, $m);
$links = array_unique($m[1]);
foreach ($links as $l) {
    echo $l, "\n";
}
