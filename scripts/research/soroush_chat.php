<?php
$t = file_get_contents('C:\Users\s.tabatabaei\soroush_doc.txt');
$lines = explode("\n", $t);
// print lines mentioning channel-related keywords
foreach ($lines as $i => $l) {
    if (preg_match('/channel|کانال|is_channel|can_post|can_send|photo_url|file_id|file_path|file_size|thumbnail|duration|width|height/', $l) === 1) {
        $l2 = trim($l);
        if (strlen($l2) > 0 && strlen($l2) < 220) {
            echo ($i + 1), ' | ', $l2, "\n";
        }
    }
}
