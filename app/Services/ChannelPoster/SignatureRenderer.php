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
 *  pure_url  : متن دلخواه کاربر (به‌صورت کامل و بدون تغییر) + URL خالص در خط بعد
 *              (خروجی plain در همهٔ پلتفرم‌ها؛ بدون anchor/Markdown — فقط برای
 *               لینک خودِ همان مقصد استفاده می‌شود)
 *  handle    : ایموجی + شناسهٔ کوتاه کانال (مثلاً «🔰@saberblog») — فقط از
 *              دادهٔ معتبرِ خودِ مقصد؛ هیچ URL/HTML/Markdown تولید نمی‌کند و
 *              اگر شناسهٔ معتبری موجود نباشد، امضا ساخته نمی‌شود (renderHandle)
 *  none      : بدون امضا
 *
 * Fallback‌های امن (پیش‌بینی‌پذیر):
 *  - هر پلتفرمی که فرمت inline تأییدشده ندارد (eitaa/soroush/rubika) →
 *    فرمت ساده «برچسب + لینک در خط بعد» (خودرسان). ایتا در مستندات رسمی
 *    parse_mode/HTML ندارد و متن verbatim رندر می‌شود (باگ RCA: نمایش تگ خام).
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
    public const TYPE_PURE_URL = 'pure_url';
    public const TYPE_HANDLE = 'handle';
    public const TYPE_NONE = 'none';

    /** ایموجی پیش‌فرض امضای «شناسهٔ کوتاه کانال» (قابل تغییر در label) */
    public const HANDLE_SIGNATURE_EMOJI = '🔰';

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
     *   parse_mode: string|null,  # html فقط برای telegram، وگرنه null (eitaa: plain — بدون parse_mode در مستندات رسمی)
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

        // Pure URL — متن دلخواه کاربر (عینِ واردشده، بدون هیچ بازنویسی/escape)
        // در خط اول + URL خالص در خط دوم. خروجی plain در همهٔ پلتفرم‌ها؛
        // anchor/Markdown استفاده نمی‌شود و هیچ escape‌ای صورت نمی‌گیرد
        // (parse_mode=null ⇒ متن به‌همین‌صورت نمایش داده می‌شود).
        if ($type === self::TYPE_PURE_URL) {
            return [
                'text' => $label !== '' ? "\n\n" . $label . "\n" . $url : "\n\n" . $url,
                'parse_mode' => null,
                'type' => self::TYPE_PURE_URL,
                'fallback' => false,
                'button' => null,
            ];
        }

        // handle از طریق renderHandle رندر می‌شود (بدون URL). اگر به‌طور
        // اشتباهی از render() صدا زده شد، هیچ URLی خروجی نمی‌دهد.
        if ($type === self::TYPE_HANDLE) {
            return self::empty($type);
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
        // فقط تلگرام: در مستندات رسمی ایتایار parse_mode/HTML وجود ندارد و متن
        // verbatim رندر می‌شود (ارسال HTML → نمایش تگ خام؛ باگ RCA). ایتا به
        // فرمت سادهٔ خودرسان پایین سقوط می‌کند.
        if (SignatureCapability::supportsInlineHtml($platform)) {
            // فرمت «برچسب:\nلینک» — دقیقاً هم‌فرمت با خروجی قدیمی buildSignature
            // (برای سطرهای legacy: signature_enabled = null) تا ظاهر امضا تغییر نکند.
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

        // eitaa/soroush/rubika/bale-ناامن: فرمت ساده (برچسب + لینک خودرسان)
        // (ایتا: API رسمی parse_mode ندارد و متن verbatim است؛ کلاینت URL را
        // خودکار لینک‌دار می‌کند — همان رویکرد soroush/rubika)
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
        // CP-SIGNATURE-MULTILINE (addendum D): url خالی‌ها حذف + dedup بر اساس
        // url — هر لینک فقط یک‌بار نمایش داده می‌شود (اولین label برنده است).
        $seen = [];
        $links = array_values(array_filter($links, function (array $l) use (&$seen) {
            $u = trim((string) ($l['url'] ?? ''));
            if ($u === '') {
                return false;
            }
            if (isset($seen[$u])) {
                return false;
            }
            $seen[$u] = true;

            return true;
        }));

        if ($links === []) {
            return self::empty('multi');
        }

        $layout = $layout === self::LAYOUT_MULTI_LINE ? self::LAYOUT_MULTI_LINE : self::LAYOUT_SINGLE_LINE;
        // فقط تلگرام فرمت HTML دارد؛ ایتا در مستندات رسمی parse_mode ندارد
        // (متن verbatim) → فرمت سادهٔ خودرسان (خودکار لینک‌دارشده در کلاینت).
        $isHtml = SignatureCapability::supportsInlineHtml($platform);
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
                // eitaa/soroush/rubika و bale-ناامن: فرمت سادهٔ خودرسان
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
            // قرارداد دوخطه (addendum D): هر ورودی در خط مستقل و حالت
            // label‌دار = «برچسب:» خط اول + لینک خط دوم — هم‌فرمت render()
            //  - html (تلگرام): خط برچسب جدا + 앵کر
            //  - bale: Markdown [url](url) فقط وقتی برچسب امن است (همان
            //    منطق single-line همین تابع)؛ وگرنه url خالص
            //  - بقیه: url خالص (خودرسان)
            $out = [];
            foreach ($links as $i => $link) {
                $label = trim((string) ($link['label'] ?? ''));
                $label = $label !== '' ? mb_substr($label, 0, 64) : '';
                if ($isHtml) {
                    $out[] = $label !== ''
                        ? $label . ':' . "\n" . $lines[$i]['rendered']
                        : $lines[$i]['rendered'];
                } else {
                    $url = trim((string) $link['url']);
                    $linkLine = ($platform === 'bale' && $label !== '' && self::markdownSafeLabel($label))
                        ? '[' . $url . '](' . $url . ')'
                        : $url;
                    $out[] = $label !== '' ? $label . ':' . "\n" . $linkLine : $linkLine;
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
     * اگر امضا را به caption اضافه کنیم و مجموع از این حد بگذرد، امضا باید
     * حذف شود — هرگز متن اصلی.
     */
    public const CAPTION_MAX = 1024;

    /**
     * آیا امضا به caption این ظرفیت می‌گنجد؟
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
     * امضای «ایموجی + شناسهٔ کوتاه کانال» (CP-CHANNEL-HANDLE-SIGNATURE).
     *
     * خروجی همیشه plain است: «{emoji} @{handle}» (با فاصلهٔ امن بین ایموجی
     * و @handle) در خط آخر متن، بدون هیچ
     * URL، anchor یا Markdown (parse_mode = null). اگر شناسه معتبر نباشد
     * (خالی/همه‌عددی/بیش از 64 کاراکتر/کاراکتر نامعتبر) امضا ساخته نمی‌شود —
     * هیچ مقدار تخمینی تولید نمی‌شود.
     *
     * @param string $platform  bale|telegram|eitaa|soroush|rubika (خروجی مستقل از پلتفرم)
     * @param string|null $handle شناسهٔ کوتاه کانال (با یا بدون «@»)
     * @param string|null $emoji ایموجی دلخواه (null/خالی = پیش‌فرض 🔰)
     *
     * @return array{
     *   text: string,             # «\n\n{emoji} @{handle}» یا ''
     *   parse_mode: string|null,  # همیشه null
     *   type: string,             # TYPE_HANDLE
     *   fallback: bool,           # همیشه false
     *   button: array|null        # همیشه null
     * }
     */
    public static function renderHandle(string $platform, ?string $handle, ?string $emoji = null): array
    {
        $handle = trim((string) $handle);
        if ($handle !== '' && str_starts_with($handle, '@')) {
            $handle = substr($handle, 1);
        }

        if (
            $handle === ''
            || preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $handle) !== 1
            || ctype_digit($handle)
        ) {
            return self::empty(self::TYPE_HANDLE);
        }

        $emoji = trim((string) $emoji);
        if ($emoji === '') {
            $emoji = self::HANDLE_SIGNATURE_EMOJI;
        }

        return [
            // فاصلهٔ امن بین ایموجی و @handle (CP-SIGNATURE-EMOJI-SPACE):
            // «🔰 @handle» — بدون فاصله، در بعضی کلاینت‌ها @handle به‌عنوان
            // mentions render نمی‌شود/به‌هم می‌چسبد.
            'text' => "\n\n" . $emoji . ' @' . $handle,
            'parse_mode' => null,
            'type' => self::TYPE_HANDLE,
            'fallback' => false,
            'button' => null,
        ];
    }

    /**
     * خط لینک با فرمت مناسب پلتفرم (با escape).
     */
    private static function linkLine(string $url, string $platform): string
    {
        // فقط تلگرام HTML دارد؛ ایتا در مستندات رسمی parse_mode ندارد و متن
        // verbatim رندر می‌شود (ارسال HTML → نمایش تگ خام؛ باگ RCA) → URL ساده.
        if (SignatureCapability::supportsInlineHtml($platform)) {
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
        // escape فقط برای فرمت HTML (تلگرام) — ایتا plain است (متن verbatim) و
        // escape در آن علامت‌های & و مشابه را خام نمایش می‌دهد.
        return SignatureCapability::supportsInlineHtml($platform)
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
