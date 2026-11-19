<?php

namespace App\Services\ContentSync;

use App\Models\ChannelPosterDestination;

/**
 * پارسر لینک‌های پیام‌های منتشرشده در هر پلتفرم.
 *
 * خروجی استاندارد هر parser:
 *   platform            — bale | telegram | eitaa | soroush
 *   channel_identifier  — شناسه کانال (username یا chat id)
 *   message_identifier  — شناسه پیام (عدد)
 *   canonical_url       — لینک نرمال‌شده
 *
 * فقط host‌های مجاز (allowlist) پذیرفته می‌شوند؛ سایر لینک‌ها به عنوان
 * لینک پیام شناخته نمی‌شوند (null).
 *
 * توجه: فرمت لینک سروش هنوز نمونه واقعی ندارد و unverified باقی می‌ماند.
 */
class PostUrlParser
{
    /**
     * هاست‌های مجاز هر پلتفرم (بدون پروتکل).
     */
    public const HOSTS = [
        'telegram' => ['t.me', 'telegram.me', 'tlg.sk'],
        'bale' => ['ble.ir', 'bale.ir', 'bale.ai', 'mybale.ai'],
        'eitaa' => ['eitaa.com', 'my.eitaa.com'],
        'soroush' => ['soroush.chat', 'splus.ir'],
    ];

    /**
     * لینک را تشخیص و پارس می‌کند. null = لینک پیام شناخته‌شده نیست.
     */
    public static function parse(?string $raw): ?array
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
        // subdomain: دو بخش آخر دامنه را نگه می‌داریم (my.eitaa.com → eitaa.com)
        $hostParts = explode('.', $host);
        if (count($hostParts) > 2) {
            $host = implode('.', array_slice($hostParts, -2));
        }

        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');
        $segments = $path === '' ? [] : array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));

        foreach (self::HOSTS as $platform => $hosts) {
            if (!in_array($host, $hosts, true)) {
                continue;
            }

            $parsed = match ($platform) {
                'telegram' => self::parseTelegram($segments),
                'bale' => self::parseBale($segments),
                'eitaa' => self::parseEitaa($segments),
                'soroush' => self::parseSoroush($segments),
                default => null,
            };

            if ($parsed !== null) {
                $parsed['platform'] = $platform;
                $parsed['canonical_url'] = self::canonical(
                    $platform,
                    $parsed['channel_identifier'],
                    $parsed['message_identifier'],
                    $parsed['channel_username'] ?? ''
                );

                return $parsed;
            }
        }

        return null;
    }

    private static function parseTelegram(array $segments): ?array
    {
        // t.me/{username}/{messageId} — کانال عمومی
        if (count($segments) === 2
            && preg_match('/^[A-Za-z0-9_]+$/', $segments[0]) === 1
            && preg_match('/^\d+$/', $segments[1]) === 1
        ) {
            return [
                'channel_identifier' => $segments[0],
                'channel_username' => $segments[0],
                'message_identifier' => ltrim($segments[1], '0') ?: '0',
            ];
        }

        // t.me/c/{shortId}/{messageId} — کانال خصوصی
        if (count($segments) === 3
            && $segments[0] === 'c'
            && preg_match('/^\d+$/', $segments[1]) === 1
            && preg_match('/^\d+$/', $segments[2]) === 1
        ) {
            return [
                'channel_identifier' => 'c/' . $segments[1],
                'message_identifier' => ltrim($segments[2], '0') ?: '0',
            ];
        }

        return null;
    }

    private static function parseBale(array $segments): ?array
    {
        // ble.ir/{username}/{chatId}/{messageId}
        if (count($segments) === 3
            && preg_match('/^[A-Za-z0-9_\-]+$/', $segments[0]) === 1
            && preg_match('/^\d+$/', $segments[1]) === 1
            && preg_match('/^\d+$/', $segments[2]) === 1
        ) {
            return [
                'channel_identifier' => $segments[1],
                'channel_username' => $segments[0],
                'message_identifier' => ltrim($segments[2], '0') ?: '0',
            ];
        }

        return null;
    }

    private static function parseEitaa(array $segments): ?array
    {
        // eitaa.com/{username}/{messageId}
        if (count($segments) === 2
            && preg_match('/^[A-Za-z0-9_\-]+$/', $segments[0]) === 1
            && preg_match('/^\d+$/', $segments[1]) === 1
        ) {
            return [
                'channel_identifier' => $segments[0],
                'message_identifier' => ltrim($segments[1], '0') ?: '0',
            ];
        }

        return null;
    }

    private static function parseSoroush(array $segments): ?array
    {
        // نمونه واقعی نداریم؛ فرض: soroush.chat/{username}/{messageId} (unverified)
        if (count($segments) === 2
            && preg_match('/^[A-Za-z0-9_\-]+$/', $segments[0]) === 1
            && preg_match('/^\d+$/', $segments[1]) === 1
        ) {
            return [
                'channel_identifier' => $segments[0],
                'message_identifier' => ltrim($segments[1], '0') ?: '0',
            ];
        }

        return null;
    }

    /**
     * لینک رسمی (canonical) هر پلتفرم — برای تطبیق و ذخیره.
     *
     * فرمت واقعی لینک پیام بله سه‌بخشی است: ble.ir/{username}/{chatId}/{messageId}
     * (نمونه واقعی: https://ble.ir/Saberblog/5062926176/1461)؛ بنابراین اگر
     * username موجود باشد در canonical هم حفظ می‌شود.
     */
    public static function canonical(string $platform, string $channelIdentifier, string $messageId, string $channelUsername = ''): string
    {
        $username = trim((string) $channelUsername);

        return match ($platform) {
            'telegram' => 'https://t.me/' . $channelIdentifier . '/' . $messageId,
            'bale' => $username !== ''
                ? 'https://ble.ir/' . $username . '/' . $channelIdentifier . '/' . $messageId
                : 'https://ble.ir/' . $channelIdentifier . '/' . $messageId,
            'eitaa' => 'https://eitaa.com/' . $channelIdentifier . '/' . $messageId,
            'soroush' => 'https://soroush.chat/' . $channelIdentifier . '/' . $messageId,
            default => '',
        };
    }

    /**
     * آیا مقصد (destination) با پارسِ لینک مطابقت دارد؟
     *
     * تطبیق فقط بر پایه‌ی شناسه‌های واقعی (نه شباهت نام نمایشی):
     *  - تطبیق دقیق channel_chat_id (بی‌تفاوت به upper/lower و @)
     *  - تطبیق با username ذخیره‌شده‌ی قابل‌اعتماد (channel_username)
     *  - تطبیق با username/شناسه‌ی درون لینک ذخیره‌شده (channel_link)
     *
     * برای کانال‌های خصوصی تلگرام (c/{shortId}) تطبیق فقط با chat id عددی
     * معتبر است؛ short id با username قابل تطبیق نیست.
     */
    public static function matchesDestination(array $parsed, ChannelPosterDestination $destination): bool
    {
        if ($parsed['platform'] !== $destination->platform) {
            return false;
        }

        $linkId = self::normalizeIdentifier((string) $parsed['channel_identifier']);
        if ($linkId === '') {
            return false;
        }

        $destChatId = self::normalizeIdentifier((string) $destination->channel_chat_id);

        if ($destChatId !== '' && $linkId === $destChatId) {
            return true;
        }

        // کانال خصوصی تلگرام: short id فقط با chat id عددی مقصد قابل تطبیق است
        if ($parsed['platform'] === 'telegram' && str_starts_with((string) $parsed['channel_identifier'], 'c/')) {
            return false;
        }

        // username ذخیره‌شده‌ی قابل‌اعتماد (برگرفته از لینک واقعی کانال هنگام اتصال)
        $destUsername = self::normalizeIdentifier((string) ($destination->channel_username ?? ''));
        if ($destUsername !== '' && $linkId === $destUsername) {
            return true;
        }

        // لینک ذخیره‌شده‌ی کانال (channel_link) — پارس و تطبیق شناسه‌های درون آن
        if ($destination->channel_link) {
            $stored = self::parse((string) $destination->channel_link);
            if ($stored !== null) {
                $storedId = self::normalizeIdentifier((string) $stored['channel_identifier']);
                if ($storedId !== '' && $linkId === $storedId) {
                    return true;
                }

                $storedUser = self::normalizeIdentifier((string) ($stored['channel_username'] ?? ''));
                if ($storedUser !== '' && $linkId === $storedUser) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * نرمال‌سازی شناسه برای تطبیق: trim + حذف @ + بی‌تفاوت به upper/lower.
     */
    private static function normalizeIdentifier(string $value): string
    {
        return strtolower(ltrim(trim($value), '@'));
    }

    /**
     * آیا متن ارسالی فقط یک لینک پیام شناخته‌شده است؟ (ورودی سریع fast mode)
     */
    public static function extractFromText(?string $text): ?array
    {
        if (!is_string($text)) {
            return null;
        }

        $url = trim($text);
        if (preg_match('#^https?://\S+$#i', $url) !== 1) {
            return null;
        }

        return self::parse($url);
    }
}
