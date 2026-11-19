<?php
$h = file_get_contents('C:\Users\s.tabatabaei\soroush_docs.html');
echo 'bytes: ', strlen($h), "\n";
// find all internal links
preg_match_all('/href="([^"]+)"/', $h, $m);
$links = array_values(array_unique($m[1]));
echo 'links: ', count($links), "\n";
foreach ($links as $l) {
    if (preg_match('/doc|api|bot|robot|message|send/i', $l)) {
        echo $l, "\n";
    }
}
// try to find __NEXT_DATA__ or self.__next_f streaming data
if (preg_match('/self\.__next_f\.push/', $h)) {
    echo "has next streaming data\n";
    // extract chunks
    preg_match_all('/self\.__next_f\.push\(\[1,"((?:[^"\\\\]|\\\\.)*)"\]\)/', $h, $c);
    $total = 0;
    foreach ($c[1] as $chunk) {
        $total += strlen($chunk);
    }
    echo 'next_f chunks: ', count($c[1]), ' total: ', $total, "\n";
    // dump to file for inspection
    $out = implode("\n", $c[1]);
    file_put_contents('C:\Users\s.tabatabaei\soroush_nextf.txt', $out);
}
