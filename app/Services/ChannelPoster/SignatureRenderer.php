<?php

namespace App\Services\ChannelPoster;

use App\Helpers\ProductLinkMessageHelper;

/**
 * رندر معنایی امضا — خروجی نهایی فقط در لحظهٔ انتشار ساخته می‌شود.
 *
 * اصل معماری (دستورالعمل مأموریت): دیتابیس فقط داده‌های معنایی امضا را نگه
 * می‌دارد (label، url، type، enabled)؛ این رندرر در زمان انتشار، خروجی
 * متن/parse_mode مخصوص همان پلتفرم را تولید می‌کند. هیچ HTML/Markdown
 * پیش‌ساخته در دیتابیس ذخیره نمی‌شود.
 *
 * انواع امضا (signature_type):
 *  inline    : لینک داخل متن با برچسب (فرمت هر پلتفرم؛ با fallback امن)
 *  raw       : فقط URL خالص (بدون برچسب)
 *  plain     : فقط متن (label؛ بدون لینک)
 *  button    : متن + دکمهٔ URL (فقط در publish-path؛ در متن امضا چیزی نیست)
 *  composite : برچسب + لینک inline
 *  none      : بدون امضا
 *
 * Fallback‌های امن (پیش‌بینی‌پذیر):
 *  - هر پلتفرمی که فرمت inline تأییدشده ندارد (soroush/rubika) → فرمت ساده
 *    «برچسب + لینک در خط بعد» (خودرسان).
 *  - label حاوی کاراکترهای مخربِ Markdown ([ ] ( ) # * _ ~ `) در بله →
 *    همان فرمت ساده (جلوگیری از شکست رندر/تزریق).
 *  - در فرمت HTML، برچسب و URL هر دو با htmlspecialchars escape می‌شوند
 *    (مبارزهٔ injection؛ offset‌های entities آسیب نمی‌بینند چون parse_mode=html
 *    از offset استفاده نمی‌کند).
 */
class SignatureRenderer
{
    /** نوع پیش‌فرض امضا */
    public const TYPE_INLINE = 'inline';
    public const TYPE_RAW = 'raw';
    public const TYPE_PLAIN = 'plain';
    public const TYPE_BUTTON = 'button';
    public const TYPE_COMPOSITE = 'composite';
    public const TYPE_NONE = 'none';

    /**
     * رندر امضا برای یک پلتفرم.
     *
     * @param string $platform  bale|telegram|eitaa|soroush|rubika
     * @param string $url       URL کانال
     * @param string $type      یکی از TYPE_*
     * @param string|null $label برچسب نمایشی کاربر (null/خالی = بدون خط برچسب)
     *
     * @return array{
     *   text: string,             # بک‌اسپیس + خطوط امضا (برای پیوستن به متن پست)
     *   parse_mode: string|null,  # html برای telegram/eitaa، وگرنه null
     *   type: string,             # نوعی که در نهایت رندر شد (پس از fallback)
     *   fallback: bool,           # آیا از fallback امن استفاده شد؟
     *   button: array|null        # markup دکمه (فقط type=button + پلتفرم پشتیبانی‌شده)
     * }
     */
    public static function render(string $platform, string $url, string $type, ?string $label = null): array
    {
        $label = trim((string) $label);
        $url = trim($url);
        $parseMode = ProductLinkMessageHelper::parseModeForPlatform($platform);

        if ($type === self::TYPE_NONE || $url === '') {
            return self::empty($type);
        }

        // فقط متن — بدون هیچ لینکی
        if ($type === self::TYPE_PLAIN) {
            return [
                'text' => $label !== '' ? "\n\n" . $label : '',
                'parse_mode' => null,
                'type' => self::TYPE_PLAIN,
                'fallback' => false,
                'button' => null,
            ];
        }

        // فقط URL — بدون برچسب
        if ($type === self::TYPE_RAW) {
            return [
                'text' => "\n\n" . $url,
                'parse_mode' => null,
                'type' => self::TYPE_RAW,
                'fallback' => false,
                'button' => null,
            ];
        }

        // دکمهٔ URL — متن امضا خالی؛ markup جداگانه به publish-path می‌رود.
        // در پلتفرم‌های بدون دکمه، fallback به inline می‌شود تا لینک گم نشود.
        if ($type === self::TYPE_BUTTON) {
            if (SignatureCapability::supportsUrlButton($platform)) {
                return [
                    'text' => $label !== '' ? "\n\n" . self::escapeForPlatform($label, $platform) : '',
                    'parse_mode' => $label !== '' ? $parseMode : null,
                    'type' => self::TYPE_BUTTON,
                    'fallback' => false,
                    'button' => [
                        'inline_keyboard' => [
                            [['text' => $label !== '' ? $label : $url, 'url' => $url]],
                        ],
                    ],
                ];
            }

            $fallback = self::render($platform, $url, self::TYPE_INLINE, $label !== '' ? $label : null);
            $fallback['fallback'] = true;

            return $fallback;
        }

        // inline / composite — لینک داخل متن با فرمت پلتفرم یا fallback ساده
        $isComposite = $type === self::TYPE_COMPOSITE;

        if ($label === '' && !$isComposite) {
            // بدون برچسب: فقط خود لینک (فرمت هر پلتفرم)
            return [
                'text' => "\n\n" . self::linkLine($url, $platform),
                'parse_mode' => $parseMode,
                'type' => self::TYPE_INLINE,
                'fallback' => false,
                'button' => null,
            ];
        }

        $line = $label !== '' ? $label . ': ' . self::linkLine($url, $platform) : self::linkLine($url, $platform);

        // فلت‌فرم‌های HTML: escape برچسب (خط برچسب جدا از خط لینک escape می‌شود
        // — در خط برچسب ما "label:" را به‌صورت خام می‌نویسیم)
        if (in_array($platform, ['telegram', 'eitaa'], true)) {
            // فرمت «برچسب:\nلینک» — دقیقاً هم‌فرمت با خروجی قدیمی buildSignature
            // (برای سطرهای legacy: signature_enabled = null) تا ظاهر امزا تغییر نکند.
            $labelLine = $label !== '' ? htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ":\n" : '';
            $line = $labelLine . self::linkLine($url, $platform);

            return [
                'text' => "\n\n" . $line,
                'parse_mode' => $parseMode,
                'type' => $isComposite ? self::TYPE_COMPOSITE : self::TYPE_INLINE,
                'fallback' => false,
                'button' => null,
            ];
        }

        // بله: Markdown فقط اگر برچسب امن باشد؛ وگرنه fallback ساده
        if ($platform === 'bale' && self::markdownSafeLabel($label)) {
            $link = self::linkLine($url, $platform); // [url](url)

            return [
                // «برچسب:\nلینک» — هم‌فرمت با خروجی قدیمی (backward-compatible)
                'text' => "\n\n" . ($label !== '' ? $label . ":\n" . $link : $link),
                'parse_mode' => null,
                'type' => $isComposite ? self::TYPE_COMPOSITE : self::TYPE_INLINE,
                'fallback' => false,
                'button' => null,
            ];
        }

        // soroush/rubika/bale-ناامن: فرمت ساده (برچسب + لینک خودرسان)
        $plainLine = $label !== '' ? $label . ':' . "\n" . $url : $url;

        return [
            'text' => "\n\n" . $plainLine,
            'parse_mode' => null,
            'type' => self::TYPE_INLINE,
            'fallback' => true,
            'button' => null,
        ];
    }

    /**
     * رندر امضای چندلینکی (CP-SMART-SIGNATURE-SETTINGS).
     *
     * سیاست انتخاب لینک/چیدمان را SignatureSettingsService تعیین می‌کند؛
     * این رندرر فقط خروجی نهاییِ مخصوص پلتفرم را می‌سازد. فقط لینک‌های
     * عمومیِ ثبت‌شده قبول می‌شود (همان قرارداد render()).
     *
     * @param string $platform  bale|telegram|eitaa|soroush|rubika
     * @param array<int, array{url: string, label: string|null}> $links
     * @param string $layout    single_line|multi_line
     * @param string $separator جداکنندهٔ single_line (پیش‌فرض « | »)
     *
     * @return array{
     *   text: string,
     *   parse_mode: string|null,
     *   type: string,           # 'multi'
     *   fallback: bool,
     *   button: array|null      # برای چندلینک هیچ دکمه‌ای ساخته نمی‌شود
     * }
     */
    public static function renderMulti(
        string $platform,
        array $links,
        string $layout,
        string $separator = ' | '
    ): array {
        $links = array_values(array_filter(
            $links,
            fn (array $l) => trim((string) ($l['url'] ?? '')) !== ''
        ));

        if ($links === []) {
            return self::empty('multi');
        }

        $layout = $layout === self::LAYOUT_MULTI_LINE ? self::LAYOUT_MULTI_LINE : self::LAYOUT_SINGLE_LINE;
        $isHtml = in_array($platform, ['telegram', 'eitaa'], true);
        $parseMode = $isHtml ? 'html' : null;
        $separator = $separator !== '' ? $separator : ' | ';
        $fallback = false;

        // هر خط لینک — فرمت پلتفرم + fallback امن (برچسب ناامن/خارجی)
        $lines = [];
        foreach ($links as $link) {
            $url = trim((string) $link['url']);
            $label = trim((string) ($link['label'] ?? ''));
            $label = $label !== '' ? mb_substr($label, 0, 64) : null;

            if ($isHtml) {
                $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                $anchor = '<a href="' . $escaped . '">'
                    . ($label !== null ? htmlspecialchars($label, ENT_QUOTES, 'UTF-8') : $escaped)
                    . '</a>';
                $lines[] = ['display' => $label ?? $url, 'rendered' => $anchor];
            } elseif ($platform === 'bale' && $label !== null && self::markdownSafeLabel($label)) {
                $lines[] = ['display' => $label, 'rendered' => '[' . $label . '](' . $url . ')'];
            } else {
                // soroush/rubika و bale-ناامن: فرمت سادهٔ خودرسان
                // (لینک بدون برچسب «fallback» نیست — فرمت ساده همان فرمت بومی است)
                if ($label !== null) {
                    $fallback = true;
                }
                $lines[] = [
                    'display' => $label ?? $url,
                    'rendered' => $label !== null ? $label . ': ' . $url : $url,
                ];
            }
        }

        if ($layout === self::LAYOUT_MULTI_LINE) {
            // هر لینک در یک خط مستقل:
            //  - پلتفرم‌های ساده: «برچسب: لینک» (خود rendered برچسب را دارد)
            //  - پلتفرم‌های html: خط برچسب جدا + لینک 앵کر
            $out = [];
            foreach ($links as $i => $link) {
                if ($isHtml) {
                    $label = trim((string) ($link['label'] ?? ''));
                    $out[] = $label !== ''
                        ? $label . ':' . "\n" . $lines[$i]['rendered']
                        : $lines[$i]['rendered'];
                } else {
                    $out[] = $lines[$i]['rendered'];
                }
            }
            $text = "\n\n" . implode("\n", $out);
        } else {
            // single_line: همهٔ نمایش‌ها با جداکننده در یک خط
            $text = "\n\n" . implode($separator, array_map(
                fn (array $l) => $l['rendered'],
                $lines
            ));
        }

        return [
            'text' => $text,
            'parse_mode' => $parseMode,
            'type' => 'multi',
            'fallback' => $fallback,
            'button' => null,
        ];
    }

    /**
     * چیدمان‌های امضای چندلینکی (CP-SMART-SIGNATURE-SETTINGS).
     */
    public const LAYOUT_SINGLE_LINE = 'single_line';
    public const LAYOUT_MULTI_LINE = 'multi_line';

    /**
     * حد طول caption رسانه (تلگرام/ایتا رسمی ۱۰۲۴؛ بقیه حد محافظه‌کارانهٔ مشترک).
     * اگر امضا را به caption اضافه کنیم و مجموع از این حد بگذرد، امزا باید
     * حذف شود — هرگز متن اصلی.
     */
    public const CAPTION_MAX = 1024;

    /**
     * آیا امزا به caption این ظرفیت می‌گنجد؟
     */
    public static function fitsCaption(int $captionLength, int $signatureLength, int $max = self::CAPTION_MAX): bool
    {
        return ($captionLength + $signatureLength) <= $max;
    }

    /**
     * فرمت قدیمی امضا (مقصد نامشخص / legacy): «برچسب پلتفرم:\nلینک خودرسان».
     * این فرمت باید دقیقاً با خروجی قدیمی buildSignature یکسان بماند تا
     * رفتار backward-compatible حفظ شود (signature_enabled = null).
     */
    public static function renderLegacy(string $platform, ?string $url, string $platformLabel): string
    {
        return $platformLabel . ":\n" . ($url ?? '');
    }

    /**
     * خط لینک با فرمت مناسب پلتفرم (با escape).
     */
    private static function linkLine(string $url, string $platform): string
    {
        if (in_array($platform, ['telegram', 'eitaa'], true)) {
            $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

            return '<a href="' . $escaped . '">' . $escaped . '</a>';
        }

        if ($platform === 'bale') {
            return '[' . $url . '](' . $url . ')';
        }

        // soroush / rubika / default: خودرسان
        return $url;
    }

    /**
     * escape برچسب متن ساده برحسب فرمت پلتفرم.
     */
    private static function escapeForPlatform(string $label, string $platform): string
    {
        return in_array($platform, ['telegram', 'eitaa'], true)
            ? htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            : $label;
    }

    /**
     * آیا برچسب برای Markdown بله امن است؟
     * کاراکترهای مخربِ Markdown/URI که می‌توانند ساختار [text](url) را بشکنند.
     */
    private static function markdownSafeLabel(string $label): bool
    {
        // توجه: «=== 1» باید داخل پرانتز نباشد — «!preg_match(...) === 1» همیشه false است
        // (عملگر «!» اول اجرا می‌شود). regex: هر کاراکتر مخرب Markdown/URI → ناامن.
        return $label !== '' && preg_match('/[[\\]()#*_~`\\\\]/', $label) !== 1;
    }

    private static function empty(string $type): array
    {
        return ['text' => '', 'parse_mode' => null, 'type' => $type, 'fallback' => false, 'button' => null];
    }
}
