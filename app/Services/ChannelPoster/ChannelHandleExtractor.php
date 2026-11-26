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
     * مسیری‌های رزرو‌شدهٔ سیستمی که هرگز نباید به‌عنوان username تفسیر شوند.
     * این مسیرها در پلتفرم‌های ایرانی (بلی، روبیکا) و تلگرام به لینک‌های
     * دعوت/کانال خصوصی/سایر ساختارهای سیستمی اختصاص دارند.
     */
    private const RESERVED_PATHS = [
        // Telegram
        'c', 'addstickers', 'addemoji', 'addcustomemoji', 'share',
        // Bale / Rubika / general
        'join', 'joinc', 'invite', 'login', 'register', 'signup',
        'signin', 'logout', 'settings', 'profile', 'search',
        'api', 'help', 'about', 'terms', 'privacy', 'support',
        'admin', 'manage', 'channel', 'group', 'bot', 'bots',
        'users', 'user', 'files', 'file', 'docs', 'document',
        'photo', 'image', 'video', 'audio', 'sticker', 'stickers',
        'poll', 'polls', 'quiz', 'vote', 'votes',
        'link', 'links', 'url', 'urls',
        'home', 'index', 'main', 'start', 'end',
        'new', 'old', 'edit', 'delete', 'remove', 'add', 'create',
        'update', 'save', 'cancel', 'confirm', 'verify', 'verified',
        'pending', 'active', 'inactive', 'disabled', 'enabled',
        'public', 'private', 'secret', 'hidden', 'visible',
        'all', 'none', 'default', 'test', 'debug', 'error',
        'true', 'false', 'yes', 'no', 'ok', 'fail', 'failed',
        'success', 'warning', 'info', 'notice', 'alert',
        'message', 'messages', 'chat', 'chats', 'conversation',
        'conversations', 'thread', 'threads', 'post', 'posts',
        'comment', 'comments', 'reply', 'replies', 'like', 'likes',
        'share', 'shares', 'forward', 'forwarded', 'copy', 'copied',
        'paste', 'upload', 'download', 'send', 'receive', 'received',
        'send', 'sended', 'sent', 'delivered', 'read', 'unread',
        'online', 'offline', 'busy', 'away', 'do_not_disturb',
        'notification', 'notifications', 'setting', 'settings',
        'account', 'accounts', 'security', 'privacy', 'data',
        'storage', 'memory', 'cache', 'clear', 'empty', 'fill',
    ];

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
     * آیا یک بخش از URL در فهرست مسیری‌های رزرو‌شده است؟
     * این مسیری‌ها هرگز نباید به‌عنوان username تفسیر شوند.
     */
    private static function isReservedPath(string $segment): bool
    {
        return in_array(strtolower($segment), self::RESERVED_PATHS, true);
    }

    /**
     * استخراج شناسه از URL بر اساس ساختار تأییدشدهٔ پلتفرم.
     *
     * - telegram : /{username} یا /{username}/{msgId} (مسار c/ و همه‌عددی ممنوع)
     * - bale     : /{username}/{chatId}/{msgId} (فرمت تأییدشدهٔ سه‌بخشی) یا
     *              /{username} (لینک عمومی خودِ کانال — community-level)
     * - eitaa    : /{username} یا /{username}/{msgId}
     * - soroush  : /{username} یا /{username}/{msgId}
     * - rubika   : /{username} یا /{username}/{msgId} (community-level، unverified)
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
            'rubika' => self::fromNamedPathUrl($segments),
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
            if (self::isReservedPath($s[0])) {
                return null;
            }
            return self::sanitizeHandle($s[0]);
        }
        if (count($s) === 2 && preg_match('/^\d+$/', $s[1]) === 1) {
            if (self::isReservedPath($s[0])) {
                return null;
            }
            return self::sanitizeHandle($s[0]);
        }

        return null;
    }

    /**
     * bale: /{username}/{chatId}/{msgId} (نمونهٔ واقعی تأییدشده:
     * https://ble.ir/Saberblog/5062926176/1461) یا /{username} (لینک عمومی
     * خودِ کانال، community-level). ساختارهای دیگر → null.
     */
    private static function fromBaleUrl(array $s): ?string
    {
        if (
            count($s) === 3
            && preg_match('/^\d+$/', $s[1]) === 1
            && preg_match('/^\d+$/', $s[2]) === 1
        ) {
            if (self::isReservedPath($s[0])) {
                return null;
            }
            return self::sanitizeHandle($s[0]);
        }

        if (count($s) === 1) {
            if (self::isReservedPath($s[0])) {
                return null;
            }
            return self::sanitizeHandle($s[0]);
        }

        return null;
    }

    /**
     * eitaa / soroush / rubika: /{username} یا /{username}/{msgId}
     */
    private static function fromNamedPathUrl(array $s): ?string
    {
        if (count($s) === 1) {
            if (self::isReservedPath($s[0])) {
                return null;
            }
            return self::sanitizeHandle($s[0]);
        }
        if (count($s) === 2 && preg_match('/^\d+$/', $s[1]) === 1) {
            if (self::isReservedPath($s[0])) {
                return null;
            }
            return self::sanitizeHandle($s[0]);
        }

        return null;
    }
}
