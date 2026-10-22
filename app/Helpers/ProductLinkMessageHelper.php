<?php

namespace App\Helpers;

class ProductLinkMessageHelper
{
    public static function buildSharabeBeheshtiIntro(): string
    {
        return 'به سایت شراب بهشتی سر بزنید؛ صفحه محصول را ببینید، فایل‌های بیشتری بشنوید و با یک صلوات و ماندن چند دقیقه در صفحه به رتبه سایت کمک کنید.';
    }

    public static function productLinkLabel(): string
    {
        return (string) config('sharabe_beheshti.product_link_label', 'مشاهده صفحه محصول');
    }

    public static function needsParseMode(?string $platformSlug): bool
    {
        return in_array($platformSlug, ['eitaa', 'telegram'], true);
    }

    /**
     * parse_mode مستقل برای هر پیام‌رسان:
     * - telegram : html (مستندات رسمی)
     * - eitaa    : html (سازگار با API تلگرام؛ تست زنده‌شده)
     * - bale/soroush/rubika : بدون parse_mode (در API آن‌ها مستند نیست)
     */
    public static function parseModeForPlatform(?string $platformSlug): ?string
    {
        return match ($platformSlug) {
            'telegram' => 'html',
            'eitaa' => 'html',
            default => null,
        };
    }

    public static function buildShortLink(string $url, string $label, ?string $platformSlug = null): string
    {
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return match ($platformSlug) {
            'eitaa', 'telegram' => '<a href="' . $escapedUrl . '">' . $escapedLabel . '</a>',
            'bale' => '[' . $label . '](' . $url . ')',
            default => $label . ': ' . $url,
        };
    }

    /**
     * امضای لینک‌دار — فقط خط لینک (بدون لیبل).
     *
     * روش ۱: Inline Link (لینک داخل متن) — به‌طور مستقل برای هر پیام‌رسان
     * برنامه‌ریزی شده (هر پیام‌رسان متد جداگانه دارد).
     *
     * @return string خط لینک (بدون خط‌بعد)
     */
    public static function signatureLink(string $url, ?string $platformSlug = null): string
    {
        return match ($platformSlug) {
            'eitaa'    => self::eitaaSignatureLink($url),
            'telegram' => self::telegramSignatureLink($url),
            'bale'     => self::baleSignatureLink($url),
            'soroush'  => self::soroushSignatureLink($url),
            'rubika'   => self::rubikaSignatureLink($url),
            default    => $url,
        };
    }

    /**
     * تلگرام — لینک داخل متن.
     * مستندات رسمی: core.telegram.org/bots/api#formatting-options (HTML).
     * نیازمند parse_mode=html — وضعیت: تأییدشده در مستندات رسمی + تست زنده.
     */
    public static function telegramSignatureLink(string $url): string
    {
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $url . '</a>';
    }

    /**
     * ایتا — لینک داخل متن.
     * API ایتا (eitaayar) سازگار با API تلگرام است؛ HTML <a href> در production
     * این پروژه استفاده و تست زنده‌شده است. مستندات رسمی مستقل ایتا موجود نیست.
     */
    public static function eitaaSignatureLink(string $url): string
    {
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $url . '</a>';
    }

    /**
     * بله — لینک داخل متن.
     * Markdown [url](url) در کتابخانه‌های رسمی/جامعه‌ای بله دیده شده؛
     * سند رسمی و مستقل پیدا نشد → با تست زنده تأیید شده (بدون parse_mode).
     */
    public static function baleSignatureLink(string $url): string
    {
        return '[' . $url . '](' . $url . ')';
    }

    /**
     * سروش — لینک ساده (خودرسان).
     * parse_mode در API Soroush Plus مستند نیست (BLOCKED) و کلاینت
     * (SoroushBotClient) parse_mode را قبل از ارسال حذف می‌کند؛
     * خود پیام‌رسان URL ساده را معمولاً لینک‌دار می‌کند.
     */
    public static function soroushSignatureLink(string $url): string
    {
        return $url;
    }

    /**
     * روبیکا — لینک ساده (خودرسان).
     * parse_mode در API Bot روبیکا مستند نیست (BLOCKED) و کلاینت
     * (RubikaBotClient) parse_mode را قبل از ارسال حذف می‌کند؛
     * خود پیام‌رسان URL ساده را معمولاً لینک‌دار می‌کند.
     */
    public static function rubikaSignatureLink(string $url): string
    {
        return $url;
    }

    /**
     * روش ۲: URL Button (دکمه لینک‌دار زیر پیام) — پشتیبانی هر پیام‌رسان.
     *
     * - telegram : پشتیبانی رسمی (InlineKeyboardButton.url — مستندات رسمی)
     * - eitaa    : سازگاری با API تلگرام (غیرتأییدشده رسمی — آزمایش عملی)
     * - bale     : SDK بله (buildInlineKeyboardButton) — غیرتأییدشده رسمی
     * - soroush/rubika : در API فعلی کلاینت، inline keyboard مستند نیست
     */
    public static function signatureButtonSupported(?string $platformSlug): bool
    {
        return match ($platformSlug) {
            'telegram', 'eitaa', 'bale' => true,
            default => false,
        };
    }

    /**
     * Markup دکمه URL (یک دکمه با متن $label و آدرس $url) برای پلتفرم‌های
     * پشتیبانی‌شده؛ برای بقیه null برمی‌گرداند.
     *
     * @return array{inline_keyboard: array}|null
     */
    public static function signatureButtonMarkup(string $label, string $url, ?string $platformSlug): ?array
    {
        if (!self::signatureButtonSupported($platformSlug)) {
            return null;
        }

        return [
            'inline_keyboard' => [
                [
                    ['text' => $label, 'url' => $url],
                ],
            ],
        ];
    }

    public static function buildSharabeProductBlock(string $shareUrl, ?string $platformSlug = null): string
    {
        return self::buildSharabeBeheshtiIntro() . "\n\n"
            . self::buildShortLink($shareUrl, self::productLinkLabel(), $platformSlug);
    }

    public static function buildSharabeProductBlockFromPostLink(string $postLink, ?string $platformSlug = null): string
    {
        $id = \App\Http\Controllers\SharabeBeheshtiMp3Controller::getId($postLink);
        $shareUrl = $id
            ? (\App\Http\Controllers\SharabeBeheshtiMp3Controller::buildSharabeBeheshtiShareUrlById((int) $id, $platformSlug) ?: $postLink)
            : $postLink;

        return self::buildSharabeProductBlock($shareUrl, $platformSlug);
    }

    public static function isSharabeBeheshtiLink(?string $url): bool
    {
        return $url !== null && str_contains($url, 'sharabebeheshti.ir');
    }

    /**
     * @return list<array{slug: string, url: string, label: string}>
     */
    public static function channelEntries(): array
    {
        $channels = config('sharabe_beheshti.channels', []);
        $entries = [];

        foreach ($channels as $slug => $channel) {
            $url = trim((string) ($channel['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $entries[] = [
                'slug' => (string) $slug,
                'url' => $url,
                'label' => (string) ($channel['label'] ?? $slug),
            ];
        }

        return $entries;
    }

    public static function buildChannelPromoBlock(?string $platformSlug = null): string
    {
        $entries = self::channelEntries();
        if ($entries === []) {
            return '';
        }

        $lines = [];
        foreach ($entries as $entry) {
            $lines[] = self::buildShortLink($entry['url'], $entry['label'], $platformSlug);
        }

        return implode("\n", $lines);
    }

    /** @deprecated Use buildSharabeBeheshtiIntro() */
    public static function buildSeoIntroText(): string
    {
        return self::buildSharabeBeheshtiIntro();
    }

    public static function buildFullMessage(string $shareUrl, ?string $title = null, bool $withIcon = false, ?string $platformSlug = 'eitaa'): string
    {
        if ($withIcon) {
            $escapedUrl = htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8');
            $escapedLabel = htmlspecialchars(self::productLinkLabel(), ENT_QUOTES, 'UTF-8');
            $link = '<a title="' . $escapedLabel . '" href="' . $escapedUrl . '" target="_blank" rel="noopener">'
                . '<i class="xf xf-eitaa fs-3"></i> '
                . $escapedLabel
                . '</a>';
        } else {
            $link = self::buildShortLink($shareUrl, self::productLinkLabel(), $platformSlug);
        }

        $parts = [self::buildSharabeBeheshtiIntro(), $link];
        if ($title !== null && $title !== '') {
            $parts[] = $title;
        }

        return implode("\n\n", $parts);
    }

    public static function buildPlainUrlMessage(string $shareUrl, ?string $title = null): string
    {
        $parts = [self::buildSharabeBeheshtiIntro(), $shareUrl];
        if ($title !== null && $title !== '') {
            $parts[] = $title;
        }

        return implode("\n\n", $parts);
    }
}
