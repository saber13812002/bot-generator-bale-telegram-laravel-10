<?php
$t = file_get_contents('C:\Users\s.tabatabaei\soroush_doc.txt');
$lines = explode("\n", $t);

// print lines that look like method headings (camelCase single tokens)
foreach ($lines as $i => $l) {
    $l2 = trim($l);
    if (preg_match('/^(send|get|set|delete|answer|ban|unban|restrict|promote|demote|export|kick|pin|unpin|edit|copy|forward|media|file|webhook|chat|user|message|messageId|fileId|caption|reply|parseMode|disable)[A-Z][A-Za-z0-9]*$/', $l2) === 1) {
        echo ($i + 1), ' | ', $l2, "\n";
    }
}
