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

        // username فقط از منبع قابل‌اعتماد (لینک/مقدار ارسالی) گرفته می‌شود؛
        // channel_title برای تلگرام/ایتا نام نمایشی است و نمی‌تواند username باشد.
        $username = ltrim(trim((string) $channelUsernameHint), '@');
        if (preg_match('/^[A-Za-z0-9_]+$/', $username) !== 1) {
            $username = '';
        }

        // در بله، نام نمایشی کانال همان username آدرس کانال است (بر اساس نمونه واقعی
        // ble.ir/stackoverflow_com/8995754940207167599/1789852322655)؛ بنابراین فقط
        // برای بله اجازه استفاده از title را می‌دهیم.
        $title = trim((string) ($destination->channel_title ?? ''));
        $titleUsername = preg_match('/^[A-Za-z0-9_]+$/', $title) === 1 ? $title : '';

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
                $user = $username !== '' ? $username : $titleUsername;
                if ($numId === '' || $user === '') {
                    return ['url' => null, 'status' => 'unverified'];
                }
                return [
                    'url' => 'https://ble.ir/' . $user . '/' . $numId . '/' . $messageId,
                    'status' => 'constructed',
                ];

            case 'soroush':
                // هنوز نمونه واقعی لینک سروش نداریم — unverified
                return ['url' => null, 'status' => 'unverified'];

            default:
                return ['url' => null, 'status' => 'unverified'];
        }
    }
}
