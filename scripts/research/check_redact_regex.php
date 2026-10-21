<?php
// One-off sanity check for the rubika redaction regex in TokenHelper.
require __DIR__ . '/../../vendor/autoload.php';

use App\Helpers\TokenHelper;

$cases = [
    'https://botapi.rubika.ir/v3/rubika-dest-token-1/sendMessage failed',
    'rubika: HTTP 401 calling getMe — body with token https://botapi.rubika.ir/v3/abcdefghijklmnop/sendMessage',
    'no token here at all',
    'https://tapi.bale.ai/bot123:SECRET/sendPhoto failed',
];

foreach ($cases as $in) {
    $out = TokenHelper::redactTokens($in);
    echo $in, "\n  => ", $out, "\n";
    if (str_contains($in, 'rubika.ir/v3/') && !str_contains($out, 'rubika-dest-token-1') && !str_contains($out, 'abcdefghijklmnop')) {
        echo "  OK: token redacted\n";
    }
}

// confirm the pattern itself compiles (null = invalid)
$err = preg_last_error_msg();
echo "last preg error: ", $err, "\n";
echo "DONE\n";
