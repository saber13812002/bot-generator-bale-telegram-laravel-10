<?php

namespace Tests\Unit;

use App\Services\ChannelPoster\ChannelHandleExtractor;
use PHPUnit\Framework\TestCase;

/**
 * CP-CHANNEL-HANDLE-SIGNATURE: استخراج شناسهٔ کوتاه کانال (handle)
 * فقط از دادهٔ معتبرِ خودِ مقصد — هرگز تخمین/ساختن.
 */
class ChannelHandleExtractorTest extends TestCase
{
    // ─────────────────────────── telegram ───────────────────────────

    public function test_telegram_username_only_url(): void
    {
        $this->assertSame('saberblog', ChannelHandleExtractor::extract('telegram', 'https://t.me/saberblog'));
    }

    public function test_telegram_username_with_message_id(): void
    {
        $this->assertSame('saberblog', ChannelHandleExtractor::extract('telegram', 'https://t.me/saberblog/42'));
    }

    public function test_telegram_accepts_all_known_hosts(): void
    {
        $this->assertSame('saberblog', ChannelHandleExtractor::extract('telegram', 'https://telegram.me/saberblog/42'));
        $this->assertSame('saberblog', ChannelHandleExtractor::extract('telegram', 'https://tlg.sk/saberblog'));
    }

    public function test_telegram_private_c_path_is_rejected(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://t.me/c/12345/42'));
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://t.me/c/12345'));
    }

    public function test_telegram_all_numeric_first_segment_is_a_chat_id_not_a_handle(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://t.me/12345/42'));
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://t.me/12345'));
    }

    public function test_telegram_wrong_host_is_rejected(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://eitaa.com/saberblog/42'));
    }

    public function test_telegram_extra_segments_are_rejected(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://t.me/saberblog/42/extra'));
    }

    // ─────────────────────────── bale ───────────────────────────

    public function test_bale_three_segment_url_with_numeric_id_and_message(): void
    {
        // نمونهٔ واقعی تأییدشده
        $this->assertSame('Saberblog', ChannelHandleExtractor::extract('bale', 'https://ble.ir/Saberblog/5062926176/1461'));
    }

    public function test_bale_accepts_all_known_hosts(): void
    {
        $this->assertSame('Saberblog', ChannelHandleExtractor::extract('bale', 'https://bale.ir/Saberblog/5062926176/1461'));
        $this->assertSame('Saberblog', ChannelHandleExtractor::extract('bale', 'https://bale.ai/Saberblog/5062926176/1461'));
        $this->assertSame('Saberblog', ChannelHandleExtractor::extract('bale', 'https://mybale.ai/Saberblog/5062926176/1461'));
    }

    public function test_bale_one_segment_public_link_yields_handle(): void
    {
        // CP-CHANNEL-LINK: ble.ir/{username} — لینک عمومی خودِ کانال (community-level)
        $this->assertSame('Saberblog', ChannelHandleExtractor::extract('bale', 'https://ble.ir/Saberblog'));
        $this->assertSame('Saberblog', ChannelHandleExtractor::extract('bale', 'https://bale.ir/Saberblog'));
        $this->assertSame('Saberblog', ChannelHandleExtractor::extract('bale', 'https://mybale.ai/Saberblog'));
        // ۱ بخشِ همه‌عددی chat id است، نه نام کاربری
        $this->assertNull(ChannelHandleExtractor::extract('bale', 'https://ble.ir/5062926176'));
    }

    public function test_bale_other_unverified_structures_are_rejected(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('bale', 'https://ble.ir/Saberblog/5062926176'));
        $this->assertNull(ChannelHandleExtractor::extract('bale', 'https://ble.ir/Saberblog/5062926176/1461/extra'));
        $this->assertNull(ChannelHandleExtractor::extract('bale', 'https://ble.ir/5062926176/1461'));
    }

    // ─────────────────────────── eitaa ───────────────────────────

    public function test_eitaa_username_with_message_id(): void
    {
        $this->assertSame('alirezamehran_3', ChannelHandleExtractor::extract('eitaa', 'https://eitaa.com/alirezamehran_3/1182'));
        $this->assertSame('alirezamehran_3', ChannelHandleExtractor::extract('eitaa', 'https://my.eitaa.com/alirezamehran_3'));
    }

    public function test_eitaa_rejects_unverified_structures(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('eitaa', 'https://eitaa.com/alirezamehran_3/1182/extra'));
        $this->assertNull(ChannelHandleExtractor::extract('eitaa', 'https://eitaa.com/12345'));
        $this->assertNull(ChannelHandleExtractor::extract('eitaa', 'https://eitaa.com/12345/1182'));
    }

    // ─────────────────────────── soroush ───────────────────────────

    public function test_soroush_username_with_message_id(): void
    {
        $this->assertSame('foo', ChannelHandleExtractor::extract('soroush', 'https://soroush.chat/foo/9'));
        $this->assertSame('bar', ChannelHandleExtractor::extract('soroush', 'https://splus.ir/bar'));
    }

    public function test_soroush_rejects_unverified_structures(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('soroush', 'https://soroush.chat/foo/9/extra'));
        $this->assertNull(ChannelHandleExtractor::extract('soroush', 'https://soroush.chat/12345'));
    }

    // ─────────────────────────── rubika ───────────────────────────

    public function test_rubika_extracts_from_public_channel_links(): void
    {
        // CP-CHANNEL-LINK: rubika.ir/{username}[/msgId] — community-level (unverified)
        $this->assertSame('SomeChannel', ChannelHandleExtractor::extract('rubika', 'https://rubika.ir/SomeChannel'));
        $this->assertSame('SomeChannel', ChannelHandleExtractor::extract('rubika', 'https://rubika.ir/SomeChannel/42'));
        $this->assertSame('SomeChannel', ChannelHandleExtractor::extract('rubika', 'https://rubika.ru/SomeChannel'));
    }

    public function test_rubika_rejects_unverified_structures(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('rubika', 'https://rubika.ir/12345'));
        $this->assertNull(ChannelHandleExtractor::extract('rubika', 'https://rubika.ir/a/b/c'));
        $this->assertNull(ChannelHandleExtractor::extract('rubika', 'https://example.com/SomeChannel'));
    }

    public function test_rubika_trusted_column_still_works(): void
    {
        $this->assertSame('somechannel', ChannelHandleExtractor::extract('rubika', null, 'somechannel'));
    }

    // ─────────────────────────── trusted column priority ───────────────────────────

    public function test_trusted_channel_username_wins_over_url(): void
    {
        $this->assertSame(
            'from_column',
            ChannelHandleExtractor::extract('telegram', 'https://t.me/from_url/42', 'from_column')
        );
    }

    public function test_at_prefix_in_trusted_column_is_stripped(): void
    {
        $this->assertSame('saberblog', ChannelHandleExtractor::extract('telegram', null, '@saberblog'));
    }

    // ─────────────────────────── sanitizeHandle (trusted column) ───────────────────────────

    public function test_sanitize_handle_strips_at_prefix(): void
    {
        $this->assertSame('saberblog', ChannelHandleExtractor::sanitizeHandle('@saberblog'));
        $this->assertSame('saberblog', ChannelHandleExtractor::sanitizeHandle('  @saberblog  '));
    }

    public function test_sanitize_handle_rejects_invalid_values(): void
    {
        $this->assertNull(ChannelHandleExtractor::sanitizeHandle(null));
        $this->assertNull(ChannelHandleExtractor::sanitizeHandle(''));
        $this->assertNull(ChannelHandleExtractor::sanitizeHandle('@'));
        $this->assertNull(ChannelHandleExtractor::sanitizeHandle('bad handle'));
        $this->assertNull(ChannelHandleExtractor::sanitizeHandle('bad/handle'));
        $this->assertNull(ChannelHandleExtractor::sanitizeHandle('fa:channel'));
    }

    public function test_sanitize_handle_rejects_all_numeric_values_they_are_chat_ids(): void
    {
        $this->assertNull(ChannelHandleExtractor::sanitizeHandle('8995754940207167599'));
    }

    public function test_sanitize_handle_enforces_64_char_limit(): void
    {
        $this->assertSame(str_repeat('a', 64), ChannelHandleExtractor::sanitizeHandle(str_repeat('a', 64)));
        $this->assertNull(ChannelHandleExtractor::sanitizeHandle(str_repeat('a', 65)));
    }

    // ─────────────────────────── input guards ───────────────────────────

    public function test_null_or_empty_link_returns_null(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('telegram', null));
        $this->assertNull(ChannelHandleExtractor::extract('telegram', ''));
        $this->assertNull(ChannelHandleExtractor::extract('telegram', '   '));
    }

    public function test_non_http_scheme_is_rejected(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'ftp://t.me/saberblog'));
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'saberblog'));
    }

    public function test_unknown_platform_returns_null(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('whatsapp', 'https://t.me/saberblog'));
    }

    // ─────────────────────────── reserved paths (P1) ───────────────────────────

    public function test_bale_reserved_paths_are_rejected(): void
    {
        // join / joinc / invite / c — مسیری‌های رزرو‌شدهٔ بله
        $this->assertNull(ChannelHandleExtractor::extract('bale', 'https://ble.ir/join'));
        $this->assertNull(ChannelHandleExtractor::extract('bale', 'https://ble.ir/joinc'));
        $this->assertNull(ChannelHandleExtractor::extract('bale', 'https://ble.ir/invite'));
        $this->assertNull(ChannelHandleExtractor::extract('bale', 'https://ble.ir/c'));
        // 3-segment with reserved first segment
        $this->assertNull(ChannelHandleExtractor::extract('bale', 'https://ble.ir/join/5062926176/1461'));
        $this->assertNull(ChannelHandleExtractor::extract('bale', 'https://ble.ir/invite/5062926176/1461'));
    }

    public function test_rubika_reserved_paths_are_rejected(): void
    {
        // مسیری‌های رزرو‌شدهٔ روبیکا
        $this->assertNull(ChannelHandleExtractor::extract('rubika', 'https://rubika.ir/join'));
        $this->assertNull(ChannelHandleExtractor::extract('rubika', 'https://rubika.ir/joinc'));
        $this->assertNull(ChannelHandleExtractor::extract('rubika', 'https://rubika.ir/invite'));
        $this->assertNull(ChannelHandleExtractor::extract('rubika', 'https://rubika.ir/c'));
        // 2-segment with reserved first segment
        $this->assertNull(ChannelHandleExtractor::extract('rubika', 'https://rubika.ir/join/42'));
        $this->assertNull(ChannelHandleExtractor::extract('rubika', 'https://rubika.ir/invite/42'));
    }

    public function test_telegram_reserved_paths_are_rejected(): void
    {
        // مسیری‌های رزرو‌شدهٔ تلگرام
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://t.me/addstickers'));
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://t.me/addemoji'));
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://t.me/login'));
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://t.me/settings'));
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://t.me/bot'));
        // 2-segment with reserved first segment
        $this->assertNull(ChannelHandleExtractor::extract('telegram', 'https://t.me/share/42'));
    }

    public function test_valid_handles_still_work_after_denylist(): void
    {
        // مطمئن شو لیست سیاه بر روی شناسه‌های معتبر اثر ندارد
        $this->assertSame('Saberblog', ChannelHandleExtractor::extract('bale', 'https://ble.ir/Saberblog'));
        $this->assertSame('SomeChannel', ChannelHandleExtractor::extract('rubika', 'https://rubika.ir/SomeChannel'));
        $this->assertSame('saberblog', ChannelHandleExtractor::extract('telegram', 'https://t.me/saberblog'));
        $this->assertSame('alirezamehran_3', ChannelHandleExtractor::extract('eitaa', 'https://eitaa.com/alirezamehran_3'));
        $this->assertSame('foo', ChannelHandleExtractor::extract('soroush', 'https://soroush.chat/foo'));
        // 3-segment valid
        $this->assertSame('Saberblog', ChannelHandleExtractor::extract('bale', 'https://ble.ir/Saberblog/5062926176/1461'));
    }

    public function test_eitaa_soroush_reserved_paths_are_rejected(): void
    {
        $this->assertNull(ChannelHandleExtractor::extract('eitaa', 'https://eitaa.com/join'));
        $this->assertNull(ChannelHandleExtractor::extract('eitaa', 'https://eitaa.com/invite'));
        $this->assertNull(ChannelHandleExtractor::extract('eitaa', 'https://eitaa.com/login/42'));
        $this->assertNull(ChannelHandleExtractor::extract('soroush', 'https://soroush.chat/join'));
        $this->assertNull(ChannelHandleExtractor::extract('soroush', 'https://soroush.chat/invite'));
        $this->assertNull(ChannelHandleExtractor::extract('soroush', 'https://soroush.chat/c'));
    }
}
