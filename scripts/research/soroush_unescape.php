<?php
$h = file_get_contents('C:\Users\s.tabatabaei\soroush_botplatform.html');
echo 'raw: ', strlen($h), "\n";

// Next.js flight data: self.__next_f.push([1,"<json-escaped string>"])
// Extract each JSON string and decode it -> real HTML
preg_match_all('/self\.__next_f\.push\(\[1,"((?:[^"\\\\]|\\\\.)*)"\]\)/', $h, $m);
echo 'flight chunks: ', count($m[1]), "\n";

$decoded = [];
foreach ($m[1] as $chunk) {
    $d = json_decode('"' . $chunk . '"', true);
    if (is_string($d)) {
        $decoded[] = $d;
    }
}
$html = implode('', $decoded);
echo 'decoded html: ', strlen($html), "\n";

$html = preg_replace('/<script\b.*?<\/script>/is', ' ', (string) $html);
$html = preg_replace('/<style\b.*?<\/style>/is', ' ', (string) $html);
$html = preg_replace('/<br\s*\/?>/i', "\n", (string) $html);
$html = preg_replace('/<\/(p|div|h1|h2|h3|h4|li|tr|table|pre|code)>/i', "\n", (string) $html);
$t = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
echo 'after strip_tags: ', strlen($t), "\n";
$t = preg_replace('/[ \t]+/', ' ', $t);
$t = preg_replace('/\n\s*\n+/', "\n\n", $t);
file_put_contents('C:\Users\s.tabatabaei\soroush_doc.txt', $t);
echo 'final: ', strlen($t), "\n";
echo '--- first 400 ---\n', substr($t, 0, 400), "\n";
