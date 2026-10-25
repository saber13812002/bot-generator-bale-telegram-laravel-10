<?php

namespace App\Services\ChannelPoster;

/**
 * Capability Matrix — فرمت‌دهی لینک/امضا به‌ازای هر پیام‌رسان.
 *
 * هر سطر یک قابلیت + وضعیت استاندارد آن را ثبت می‌کند:
 *
 *  OFFICIAL_CONFIRMED      : در مستندات رسمی پیام‌رسان تأیید شده (doc link موجود)
 *  COMMUNITY_DOCUMENTED    : فقط در مستندات جامعه/SDK غیررسمی
 *  MOCK_TESTED             : فقط در تست mock این پروژه (هیچ پاسخ زنده API ندیده)
 *  LIVE_API_ACCEPTED       : API زنده payload را پذیرفته (message_id دریافت شده)
 *  CLIENT_RENDER_VERIFIED  : رندر نهایی در کلاینت به‌صورت بصری تأیید شده
 *  UNVERIFIED              : ممکن است باشد اما هیچ منبعی ندارد
 *  UNSUPPORTED             : مستنداً/عملیاً پشتیبانی نمی‌شود
 *  OFFICIAL_DOC_NOT_FOUND  : در مستندات رسمی نیافت (نه تأیید و نه رد)
 *
 * منبع‌ها:
 *  - docs/features/PLATFORM_LINK_FORMAT_COMPATIBILITY.md (ماتریس ۱۱×۵ با source links)
 *  - docs/features/platform-api-research.md (سند رسمی SoroushPlus §1.3، سند رسمی روبیکا §2)
 *  - docs/features/LINKED_SIGNATURE.md (نتایج تست زندهٔ فزایی)
 */
class SignatureCapability
{
    public const OFFICIAL_CONFIRMED = 'OFFICIAL_CONFIRMED';
    public const COMMUNITY_DOCUMENTED = 'COMMUNITY_DOCUMENTED';
    public const MOCK_TESTED = 'MOCK_TESTED';
    public const LIVE_API_ACCEPTED = 'LIVE_API_ACCEPTED';
    public const CLIENT_RENDER_VERIFIED = 'CLIENT_RENDER_VERIFIED';
    public const UNVERIFIED = 'UNVERIFIED';
    public const UNSUPPORTED = 'UNSUPPORTED';
    public const OFFICIAL_DOC_NOT_FOUND = 'OFFICIAL_DOC_NOT_FOUND';

    /**
     * ماتریس کامل: [platform => [capability => ['status' => ..., 'note' => ...]]]
     *
     * قابلیت‌ها:
     *  parse_mode_html   : آیا parse_mode=html روی sendMessage پذیرفته می‌شود
     *  markdown          : آیا Markdown ([text](url) / **bold** ...) پذیرفته می‌شود
     *  inline_link       : آیا لینک داخل متن (label روی URL) رندر می‌شود
     *  url_button        : آیا reply_markup با دکمه url (inline keyboard) پذیرفته می‌شود
     *  caption_link      : آیا لینک در caption رسانه رندر می‌شود
     *  entities          : آیا API entities-based formatting دارد
     */
    public static function matrix(): array
    {
        return [
            'telegram' => [
                'parse_mode_html' => [
                    'status' => self::OFFICIAL_CONFIRMED,
                    'note' => 'core.telegram.org/bots/api — HTML/MarkdownV2/Markdown مستند رسمی',
                ],
                'markdown' => [
                    'status' => self::OFFICIAL_CONFIRMED,
                    'note' => 'Markdown + MarkdownV2 (escape \\ اجباری در V2) — مستند رسمی',
                ],
                'inline_link' => [
                    'status' => self::LIVE_API_ACCEPTED,
                    'note' => '<a href> html در production این پروژه تست زنده‌شده (LINKED_SIGNATURE.md)',
                ],
                'url_button' => [
                    'status' => self::OFFICIAL_CONFIRMED,
                    'note' => 'InlineKeyboardButton.url — مستند رسمی',
                ],
                'caption_link' => [
                    'status' => self::LIVE_API_ACCEPTED,
                    'note' => 'caption با parse_mode در production استفاده شده؛ حد طول caption=1024 (مستند رسمی)',
                ],
                'entities' => [
                    'status' => self::OFFICIAL_CONFIRMED,
                    'note' => 'MessageEntity — مستند رسمی',
                ],
            ],
            'bale' => [
                'parse_mode_html' => [
                    'status' => self::UNSUPPORTED,
                    'note' => 'API بله (balepy) parse_mode ندارد؛ کلاینت vendor فقط text می‌فرستد',
                ],
                'markdown' => [
                    'status' => self::COMMUNITY_DOCUMENTED,
                    'note' => 'Markdown [text](url) در نمونه‌های SDK جامعه؛ سند رسمی مستقل نیافت',
                ],
                'inline_link' => [
                    'status' => self::LIVE_API_ACCEPTED,
                    'note' => '[url](url) در production تست زنده‌شده (message_id 1182)؛ رندر بصری کلاینت VERIFIED نشده',
                ],
                'url_button' => [
                    'status' => self::COMMUNITY_DOCUMENTED,
                    'note' => 'buildInlineKeyboardButton در SDK بله؛ رندر بصری VERIFIED نشده',
                ],
                'caption_link' => [
                    'status' => self::UNVERIFIED,
                    'note' => 'media caption در بله بدون parse_mode؛ حد طول مستند نیست',
                ],
                'entities' => [
                    'status' => self::OFFICIAL_DOC_NOT_FOUND,
                    'note' => 'در API بله نیافت',
                ],
            ],
            'eitaa' => [
                'parse_mode_html' => [
                    'status' => self::LIVE_API_ACCEPTED,
                    'note' => 'سازگار با API تلگرام؛ در production تست زنده‌شده؛ مستندات رسمی مستقل ندارد',
                ],
                'markdown' => [
                    'status' => self::UNVERIFIED,
                    'note' => 'سازگاری با تلگرام محتمل اما تست‌نشده',
                ],
                'inline_link' => [
                    'status' => self::LIVE_API_ACCEPTED,
                    'note' => '<a href> html در production (message_id 177641559)',
                ],
                'url_button' => [
                    'status' => self::UNSUPPORTED,
                    'note' => 'کلاینت ایتا reply_markup نمی‌فرستد؛ preview فقط‌متن است (⛔ در LINKED_SIGNATURE.md)',
                ],
                'caption_link' => [
                    'status' => self::LIVE_API_ACCEPTED,
                    'note' => 'سازگار با تلگرام؛ حد caption=1024 فرض‌شده از تلگرام (فرض محفظه)',
                ],
                'entities' => [
                    'status' => self::UNVERIFIED,
                    'note' => 'سازگاری با تلگرام محتمل اما تست‌نشده',
                ],
            ],
            'soroush' => [
                'parse_mode_html' => [
                    'status' => self::OFFICIAL_CONFIRMED,
                    'note' => 'سند رسمی SoroushPlus (bot-platform) §1.3: parse_mode MarkdownV2/HTML/Markdown + reply_markup — اما live-unverified و کلاینت پروژه parse_mode را می‌پالاید',
                ],
                'markdown' => [
                    'status' => self::OFFICIAL_CONFIRMED,
                    'note' => 'در همان سند رسمی (legacy Markdown/MarkdownV2/HTML) — live-unverified',
                ],
                'inline_link' => [
                    'status' => self::UNVERIFIED,
                    'note' => 'مستند رسمی فرمت لینک داخل متن را نشان نمی‌دهد؛ پیام‌رسان URL ساده را خودکار لینک‌دار می‌کند',
                ],
                'url_button' => [
                    'status' => self::UNVERIFIED,
                    'note' => 'reply_markup در سند رسمی هست اما کلاینت پروژه استفاده نمی‌کند و تست‌نشده',
                ],
                'caption_link' => [
                    'status' => self::UNVERIFIED,
                    'note' => 'حد طول caption مستند نیست',
                ],
                'entities' => [
                    'status' => self::OFFICIAL_DOC_NOT_FOUND,
                    'note' => 'در سند رسمی SoroushPlus نیافت',
                ],
            ],
            'rubika' => [
                'parse_mode_html' => [
                    'status' => self::OFFICIAL_DOC_NOT_FOUND,
                    'note' => 'سند رسمی botapi.rubika.ir (v3) پارامتر parse_mode ندارد → کلاینت می‌پالاید',
                ],
                'markdown' => [
                    'status' => self::OFFICIAL_DOC_NOT_FOUND,
                    'note' => 'در سند رسمی نیافت؛ در نمونه‌های جامعه هم نیست',
                ],
                'inline_link' => [
                    'status' => self::OFFICIAL_DOC_NOT_FOUND,
                    'note' => 'sendMessage فقط text/chat_id/chat_keypad/inline_keypad/... — فرمت لینک داخل متن نیافت',
                ],
                'url_button' => [
                    'status' => self::OFFICIAL_DOC_NOT_FOUND,
                    'note' => 'inline_keypad در سند هست اما فرمت دکمه url مستند نیست → UNVERIFIED',
                ],
                'caption_link' => [
                    'status' => self::UNVERIFIED,
                    'note' => 'sendFile text بدون parse_mode؛ حد طول مستند نیست',
                ],
                'entities' => [
                    'status' => self::OFFICIAL_DOC_NOT_FOUND,
                    'note' => 'در سند رسمی نیافت',
                ],
            ],
        ];
    }

    /**
     * آیا پلتفرم می‌تواند با parse_mode=html لینک inline (HTML <a href>) رندر کند؟
     */
    public static function supportsInlineHtml(string $platform): bool
    {
        return in_array($platform, ['telegram', 'eitaa'], true);
    }

    /**
     * آیا پلتفرم Markdown link را می‌پذیرد؟ (فقط بله — COMMUNITY_DOCUMENTED)
     */
    public static function supportsMarkdownLink(string $platform): bool
    {
        return $platform === 'bale';
    }

    /**
     * آیا دکمهٔ URL (inline keyboard) قابل‌اعتماد است؟
     * (telegram = رسمی؛ bale = community؛ بقیه نه)
     */
    public static function supportsUrlButton(string $platform): bool
    {
        return in_array($platform, ['telegram', 'bale'], true);
    }
}
