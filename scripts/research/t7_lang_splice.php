<?php
// t7_lang_splice.php — insert CP-DELETE-PURELINK (T7) lang keys + help line
// into lang/fa/bot.php using nowdoc literals (byte-safe for Persian content).

$file = dirname(__DIR__, 2) . '/lang/fa/bot.php';
$src = file_get_contents($file);
if ($src === false) {
    fwrite(STDERR, "cannot read $file\n");
    exit(1);
}

// --- 1) dpl block: insert before the (unique) Publish Queue section ---
$anchorBlock = "    // === Publish Queue ===";
if (substr_count($src, $anchorBlock) !== 1) {
    fwrite(STDERR, "block anchor not unique\n");
    exit(1);
}

$insert = <<<'EOT'
    // === Delete Pure Link (CP-DELETE-PURELINK, T7) ===
    // type-aware purelink removal: only pure_url signature is cleared.
    'channel_poster_dpl_menu_entry' => '🗑 حذف امزای purelink',
    'channel_poster_dpl_no_tag' => 'هنوز تگی نداری. اول /start بزن و کانال وصل کن.',
    'channel_poster_dpl_ask_tag' => 'امزای purelink کدام تگ را پاک بکنی؟',
    'channel_poster_dpl_no_channels' => 'این تگ کانالی ندارد.',
    'channel_poster_dpl_pick_channel' => 'کدام کانال؟ (نوع فعلی امزای هر کانال داخل کروشه نشان داده شده: pure_url / handle / هوشمند / legacy / غیرفعال)',
    'channel_poster_dpl_type_smart' => 'هوشمند',
    'channel_poster_dpl_type_disabled' => 'غیرفعال',
    'channel_poster_dpl_confirm_pur' => '👁 امزای purelink فعلی این کانال:'."\n".'«:preview»'."\n".'حذف purelink امزای دیگر (handle/هوشمند) و تنظیمات امزا را دست نمی‌زند. چیکار کنم؟',
    'channel_poster_dpl_confirm_all' => '⚠️ «غیرفعال‌سازی کامل همهٔ امضاها» تمام امزاها و تنظیمات امزای این کانال را خاموش می‌کند (signature_enabled=false).',
    'channel_poster_dpl_no_purelink' => 'این کانال purelink ندارد (نوع فعلی: :type).',
    'channel_poster_dpl_btn_pur' => '🗑 حذف purelink',
    'channel_poster_dpl_btn_all' => '⚠️ غیرفعال‌سازی کامل همهٔ امضاها',
    'channel_poster_dpl_btn_cancel' => '✖️ انصراف',
    'channel_poster_dpl_deleted' => '✅ امزای purelink این کانال حذف شد. امزای دیگر (handle/هوشمند) و تنظیمات دست‌نخورده ماند.',
    'channel_poster_dpl_all_disabled' => '✅ همهٔ امزاها روی این کانال غیرفعال شد (signature_enabled=false).',
    'channel_poster_dpl_cancelled' => 'انصراف — تنظیمات پیشین دست‌نخورده ماند.',
    'channel_poster_dpl_invalid_number' => 'شمارهٔ گزینه را درست بفرست (یا از دکمه‌ها استفاده کن).',
    'channel_poster_dpl_confirm_invalid' => '«حذف» / «همه» / «انصراف» بفرست (یا از دکمه‌ها استفاده کن).',
    'channel_poster_dpl_hint_tag' => 'شمارهٔ تگ / کانال را تایپ کن (یا انصراف)',
    'channel_poster_dpl_hint_confirm' => 'حذف / همه / انصراف',

EOT;

$src = str_replace($anchorBlock, $insert . $anchorBlock, $src);

// --- 2) help line: insert after the (unique) /sign help line ---
$helpAnchor = <<<'EOT'
        "\n".'/sign
EOT;
$pos = strpos($src, $helpAnchor);
if ($pos === false) {
    fwrite(STDERR, "help anchor not found\n");
    exit(1);
}
$eol = strpos($src, "\n", $pos);
if ($eol === false) {
    fwrite(STDERR, "help eol not found\n");
    exit(1);
}

$helpLine = <<<'EOT'
        "\n".'/deletepurelink — حذف نوع‌آگاه امزای purelink (امزای دیگر دست نمی‌خورد)'.
EOT;

$src = substr_replace($src, $helpLine, $eol + 1, 0);

// --- sanity guards ---
if (substr_count($src, 'channel_poster_dpl_menu_entry') !== 1) {
    fwrite(STDERR, "dpl key count wrong\n");
    exit(1);
}
if (!str_ends_with(trim($src), '];')) {
    fwrite(STDERR, "file tail damaged\n");
    exit(1);
}

if (file_put_contents($file, $src) === false) {
    fwrite(STDERR, "cannot write $file\n");
    exit(1);
}

echo "OK: dpl lang block + help line inserted\n";
