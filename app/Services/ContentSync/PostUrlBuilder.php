<?php

namespace App\Services\ContentSync;

use App\Models\ChannelPosterDestination;

/**
 * ساخت post_url بعد از انتشار موفق (فقط از اطلاعات واقعی API).
 *
 * سه سطح اعتماد:
 *  - confirmed:  لینک عمومی که خود API/پلتفرم تأیید کرده (کانال عمومی تلگرام با username)
 *  - constructed: لینکی که از شناسه‌های واقعی منتشرشده ساخته شد ولی تست نشده
 *  - unverified: فرمت لینک پلتفرم هنوز نمونه واقعی ندارد (سروش)
 *
 * هیچ آدرس ساختگی (بدون message_id واقعی) ذخیره نمی‌شود.
 */
class PostUrlBuilder
{
    /**
     * @return array{url: string|null, status: string}
     */
    public static function build(ChannelPosterDestination $destination, ?string $messageId, string $channelUsernameHint = ''): array
    {
        if (!$messageId) {
            return ['url' => null, 'status' => 'unverified'];
        }

        $platform = $destination->platform;
        $chatId = (string) $destination->channel_chat_id;

        // username فقط از منابع قابل‌اعتماد گرفته می‌شود:
        // ۱) ستون channel_username (ثبت‌شده از لینک واقعی کانال)
        // ۲) مقدار ارسالی هنگام ثبت مقصد (hint)
        // نام نمایشی (channel_title) هرگز به‌عنوان username استفاده نمی‌شود.
        $colUsername = ltrim(trim((string) ($destination->channel_username ?? '')), '@');
        if (preg_match('/^[A-Za-z0-9_\-]+$/', $colUsername) !== 1) {
            $colUsername = '';
        }
        $username = ltrim(trim((string) $channelUsernameHint), '@');
        if (preg_match('/^[A-Za-z0-9_\-]+$/', $username) !== 1) {
            $username = '';
        }
        if ($colUsername !== '') {
            $username = $colUsername;
        }

        switch ($platform) {
            case 'telegram':
                if ($username !== '') {
                    // کانال عمومی با username: لینک t.me/{user}/{id} عمومی و قابل استفاده است
                    return [
                        'url' => 'https://t.me/' . $username . '/' . $messageId,
                        'status' => 'confirmed',
                    ];
                }
                if (str_starts_with($chatId, '@')) {
                    return [
                        'url' => 'https://t.me/' . ltrim($chatId, '@') . '/' . $messageId,
                        'status' => 'confirmed',
                    ];
                }
                if (preg_match('/^-100(\d+)$/', $chatId, $m) === 1) {
                    return [
                        'url' => 'https://t.me/c/' . $m[1] . '/' . $messageId,
                        'status' => 'constructed',
                    ];
                }
                return ['url' => null, 'status' => 'unverified'];

            case 'eitaa':
                // در مقصدهای ایتا، channel_chat_id خودش username کانال است (قابل اعتماد).
                $id = preg_match('/^[A-Za-z0-9_\-]+$/', $chatId) ? $chatId : $username;
                if ($id === '') {
                    return ['url' => null, 'status' => 'unverified'];
                }
                return [
                    'url' => 'https://eitaa.com/' . $id . '/' . $messageId,
                    'status' => 'constructed',
                ];

            case 'bale':
                // فرمت ble.ir/{username}/{chatId}/{messageId}
                $numId = preg_match('/^\d+$/', $chatId) ? $chatId : '';
                if ($numId === '' || $username === '') {
                    return ['url' => null, 'status' => 'unverified'];
                }
                return [
                    'url' => 'https://ble.ir/' . $username . '/' . $numId . '/' . $messageId,
                    'status' => 'constructed',
                ];

            case 'soroush':
                // هنوز نمونه واقعی لینک سروش نداریم — unverified
                return ['url' => null, 'status' => 'unverified'];

            case 'rubika':
                // فرمت لینک پست روبیکا در مستندات اعلام نشده (BLOCKED) — unverified
                return ['url' => null, 'status' => 'unverified'];

            default:
                return ['url' => null, 'status' => 'unverified'];
        }
    }
}
