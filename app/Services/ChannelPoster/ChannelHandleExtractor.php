<?php

namespace App\Services\ChannelPoster;

use App\Services\ContentSync\PostUrlParser;

/**
 * استخراج شناسهٔ کوتاه کانال (handle) از دادهٔ اعتباریِ خودِ همان مقصد.
 *
 * اولویت‌ها:
 *  1. ستون معتبر channel_username (فقط از لینک‌های واقعی پارس‌شده پر می‌شود)
 *  2. پارس URL کانال با ساختار سخت‌گیرانهٔ همان پلتفرم
 *
 * اصل «تخمین ممنوع»: اگر هیچ شناسهٔ معتبری استخراج نشود، null برمی‌گردد
 * و هیچ امضایی ساخته نمی‌شود (chat id عددی ≠ نام کاربری).
 */
class ChannelHandleExtractor
{
    /** الگوی مجاز شناسه: فقط حرف انگلیسی، عدد، _ و - */
    private const HANDLE_PATTERN = '/^[A-Za-z0-9_\-]+$/';

    /**
     * @return string|null شناسه بدون «@» یا null اگر معتبر نیست
     */
    public static function extract(string $platform, ?string $channelLink, ?string $channelUsername = null): ?string
    {
        $trusted = self::sanitizeHandle($channelUsername);
        if ($trusted !== null) {
            return $trusted;
        }

        return self::fromUrl($platform, $channelLink);
    }

    /**
     * اعتبارسنجی یک شناسهٔ معتبر (مستقیم از ستون channel_username).
     *
     * @return string|null شناسه تمیز‌شده (بدون «@») یا null
     */
    public static function sanitizeHandle(?string $value): ?string
    {
        $v = ltrim(trim((string) $value), '@');
        if ($v === '' || mb_strlen($v) > 64) {
            return null;
        }
        if (preg_match(self::HANDLE_PATTERN, $v) !== 1) {
            return null;
        }
        // شناسهٔ همه‌عددی chat id است، نه نام کاربری
        if (ctype_digit($v)) {
            return null;
        }

        return $v;
    }

    /**
     * استخراج شناسه از URL بر اساس ساختار تأییدشدهٔ پلتفرم.
     *
     * - telegram : /{username} یا /{username}/{msgId} (مسار c/ و همه‌عددی ممنوع)
     * - bale     : فقط /{username}/{chatId}/{msgId} (فرمت تأییدشدهٔ سه‌بخشی)
     * - eitaa    : /{username} یا /{username}/{msgId}
     * - soroush  : /{username} یا /{username}/{msgId}
     * - rubika   : از URL استخراج نمی‌شود (ساختار عمومی تأییدشده ندارد)
     *
     * @return string|null شناسه بدون «@» یا null
     */
    private static function fromUrl(string $platform, ?string $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $url = trim($raw);
        if (preg_match('#^https?://#i', $url) !== 1) {
            return null;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '') {
            return null;
        }
        // subdomain: دو بخش آخر دامنه (همان منطق PostUrlParser)
        $hostParts = explode('.', $host);
        if (count($hostParts) > 2) {
            $host = implode('.', array_slice($hostParts, -2));
        }

        $allowed = PostUrlParser::HOSTS[$platform] ?? null;
        if (!is_array($allowed) || !in_array($host, $allowed, true)) {
            return null;
        }

        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');
        $segments = $path === '' ? [] : array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));

        return match ($platform) {
            'telegram' => self::fromTelegramUrl($segments),
            'bale' => self::fromBaleUrl($segments),
            'eitaa', 'soroush' => self::fromNamedPathUrl($segments),
            default => null,
        };
    }

    /**
     * telegram: /{username} یا /{username}/{msgId}
     * - مسیر c/{chatId}/{msgId} (کانال خصوصی) → null
     * - بخش اول همه‌عددی (chat id) → null
     */
    private static function fromTelegramUrl(array $s): ?string
    {
        if (($s[0] ?? '') === 'c') {
            return null;
        }
        if (count($s) === 1) {
            return self::sanitizeHandle($s[0]);
        }
        if (count($s) === 2 && preg_match('/^\d+$/', $s[1]) === 1) {
            return self::sanitizeHandle($s[0]);
        }

        return null;
    }

    /**
     * bale: فقط /{username}/{chatId}/{msgId} (نمونهٔ واقعی تأییدشده:
     * https://ble.ir/Saberblog/5062926176/1461). ساختارهای کوتاه‌تر
     * تأیید نشده‌اند → null.
     */
    private static function fromBaleUrl(array $s): ?string
    {
        if (
            count($s) === 3
            && preg_match('/^\d+$/', $s[1]) === 1
            && preg_match('/^\d+$/', $s[2]) === 1
        ) {
            return self::sanitizeHandle($s[0]);
        }

        return null;
    }

    /**
     * eitaa / soroush: /{username} یا /{username}/{msgId}
     */
    private static function fromNamedPathUrl(array $s): ?string
    {
        if (count($s) === 1) {
            return self::sanitizeHandle($s[0]);
        }
        if (count($s) === 2 && preg_match('/^\d+$/', $s[1]) === 1) {
            return self::sanitizeHandle($s[0]);
        }

        return null;
    }
}
