<?php
$c = file_get_contents('C:\Users\s.tabatabaei\soroush_container.txt');
echo 'container: ', strlen($c), "\n";

$q = chr(34); // double quote
$prefix = 'self.__next_f.push([1,' . $q;
$s = strpos($c, $prefix);
$e = strrpos($c, $q . '])');
echo 's=', var_export($s, true), ' e=', var_export($e, true), "\n";
if ($s === false || $e === false) {
    echo "markers not found; head: ", substr($c, 0, 150), "\n";
    exit(1);
}
$escaped = substr($c, $s + strlen($prefix), $e - ($s + strlen($prefix)));
echo 'escaped payload: ', strlen($escaped), "\n";

$decoded = json_decode($q . $escaped . $q, true);
if (!is_string($decoded)) {
    echo 'json_decode failed: ', json_last_error_msg(), "\n";
    exit(1);
}
echo 'decoded html: ', strlen($decoded), "\n";

$decoded = preg_replace('/<br\s*\/?>/i', "\n", $decoded);
$decoded = preg_replace('/<\/(p|div|h1|h2|h3|h4|h5|li|tr|table|pre|code|ul|ol)>/i', "\n", $decoded);
$t = html_entity_decode(strip_tags($decoded), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$t = preg_replace('/[ \t]+/', ' ', $t);
$t = preg_replace('/\n\s*\n+/', "\n\n", $t);
file_put_contents('C:\Users\s.tabatabaei\soroush_doc.txt', $t);
echo 'final text: ', strlen($t), "\n";
echo '--- first 500 ---\n', substr($t, 0, 500), "\n";
