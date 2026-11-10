<?php
// T8 — fix scenario-7 expected string in SignatureScenariosTest.php.
// write_to_file decodes HTML entities, so the entity-escaped form must be
// assembled at runtime from chr() (no literal ampersand-entities in this file).

$file = __DIR__ . '/../../tests/Unit/SignatureScenariosTest.php';
$src = file_get_contents($file);
if ($src === false) {
    fwrite(STDERR, "cannot read $file\n");
    exit(1);
}

$q = chr(34);      // "
$bs = chr(92);     // backslash
$lb = chr(38) . 'lt;b' . chr(62);   // entity form: open b
$rb = chr(38) . 'lt;/b' . chr(62);  // entity form: close b
$amp = chr(38) . 'amp;';
$qt = chr(38) . 'quot;';

// Corrupted line on disk (raw open-b tags inside a double-quoted PHP string).
$search = '            ' . $q . $bs . 'n' . $bs . 'nبرچسب <b>ناقص</b> & ' . $q . 'کوتاه' . $q . ':' . $bs . 'n' . $q;

// Corrected line: label escaped via htmlspecialchars(ENT_QUOTES).
$replace = '            ' . $q . $bs . 'n' . $bs . 'nبرچسب ' . $lb . 'ناقص' . $rb . ' ' . $amp . ' ' . $qt . 'کوتاه' . $qt . ':' . $bs . 'n' . $q;

if ($search === $replace) {
    fwrite(STDERR, "search and replace are identical\n");
    exit(1);
}

$count = substr_count($src, $search);
if ($count !== 1) {
    fwrite(STDERR, "expected exactly 1 occurrence of the corrupted line, found $count\n");
    exit(1);
}

if (!str_ends_with(trim($src), '}')) {
    fwrite(STDERR, "tail guard failed: file does not end with '}'\n");
    exit(1);
}

$src = str_replace($search, $replace, $src);

if (strpos($src, $search) !== false) {
    fwrite(STDERR, "corrupted line still present after replace\n");
    exit(1);
}
if (substr_count($src, $replace) !== 1) {
    fwrite(STDERR, "replacement line count is not 1\n");
    exit(1);
}

file_put_contents($file, $src);
echo "OK: scenario-7 expected string now uses HTML entities\n";
