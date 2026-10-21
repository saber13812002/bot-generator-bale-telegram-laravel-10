<?php
$h = file_get_contents('C:\Users\s.tabatabaei\soroush_botplatform.html');
echo 'bytes: ', strlen($h), "\n";

// 1) count keyword occurrences
foreach (['sendMessage', 'sendText', 'botapi', 'api.rubika', 'rubika', 'getUpdates', 'webhook', 'token', 'سروش', 'ربات'] as $kw) {
    $c = substr_count($h, $kw);
    echo str_pad($kw, 20), ': ', $c, "\n";
}

// 2) dump all self.__next_f.push payloads decoded
preg_match_all('/self\.__next_f\.push\(\[1,"((?:[^"\\\\]|\\\\.)*)"\]\)/', $h, $c);
echo 'next_f chunks: ', count($c[1]), "\n";
$decoded = [];
foreach ($c[1] as $chunk) {
    // it's a JS string literal; use json_decode after wrapping
    $json = '"' . str_replace('"', '\"', str_replace('\\', '\\\\', $chunk)) . '"';
    // simpler: use eval-free approach — decode via preg_replace of common escapes
    $d = json_decode('"' . $chunk . '"', true);
    if (is_string($d)) {
        $decoded[] = $d;
    }
}
$all = implode('', $decoded);
echo 'decoded total: ', strlen($all), "\n";
file_put_contents('C:\Users\s.tabatabaei\soroush_botplatform_nextf.txt', $all);

// 3) look for <title>
preg_match('/<title>(.*?)<\/title>/s', $h, $t);
echo 'title: ', $t[1] ?? 'n/a', "\n";
