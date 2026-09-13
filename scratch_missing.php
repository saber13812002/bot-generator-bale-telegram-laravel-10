<?php
$botOwnerKeys = array_keys(require('c:/Users/s.tabatabaei/Documents/saberprojects/bot-generator-bale-telegram-laravel-10/lang/fa/bot-owner.php'));
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('c:/Users/s.tabatabaei/Documents/saberprojects/bot-generator-bale-telegram-laravel-10/resources/views/bot-owner'));
$foundKeys = [];
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() == 'php') {
        $content = file_get_contents($file->getPathname());
        preg_match_all('/trans\(\'bot-owner\.([^\']+)\'/', $content, $matches);
        $foundKeys = array_merge($foundKeys, $matches[1]);
    }
}
$foundKeys = array_unique($foundKeys);
$missingKeys = array_diff($foundKeys, $botOwnerKeys);
echo "Missing: \n" . implode("\n", $missingKeys) . "\n";
