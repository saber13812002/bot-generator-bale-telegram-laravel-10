<?php

namespace Tests\Unit;

use App\Services\ChannelPoster\SignatureCapability;
use App\Services\ChannelPoster\SignatureRenderer;
use Tests\TestCase;

/**
 * Unit tests for the platform-aware signature renderer.
 *
 * Contract (architecture directive): the DB stores SEMANTIC signature data
 * (label, url, type, enabled); this class renders the final platform-specific
 * output at publish time. Fallbacks must be predictable and safe:
 *  - no verified inline format (soroush/rubika)  -> plain "label:\nurl"
 *  - unsafe Markdown label on bale               -> plain fallback
 *  - HTML platforms (telegram/eitaa)             -> htmlspecialchars (ENT_QUOTES)
 *  - unsupported URL button                      -> fallback to inline (never drop the link)
 */
class SignatureRendererTest extends TestCase
{
    private string $url = 'https://t.me/ch';

    // ─────────────────────────── inline (labeled) ───────────────────────────

    public function test_telegram_inline_with_label_uses_html_anchor(): void
    {
        $res = SignatureRenderer::render('telegram', $this->url, SignatureRenderer::TYPE_INLINE, 'کانال ما');

        $this->assertSame(
            "\n\nکانال ما:\n<a href=\"https://t.me/ch\">https://t.me/ch</a>",
            $res['text']
        );
        $this->assertSame('html', $res['parse_mode']);
        $this->assertSame(SignatureRenderer::TYPE_INLINE, $res['type']);
        $this->assertFalse($res['fallback']);
        $this->assertNull($res['button']);
    }

    public function test_eitaa_inline_uses_same_html_format_as_telegram(): void
    {
        $res = SignatureRenderer::render('eitaa', $this->url, SignatureRenderer::TYPE_INLINE, 'کانال ما');

        $this->assertSame(
            "\n\nکانال ما:\n<a href=\"https://t.me/ch\">https://t.me/ch</a>",
            $res['text']
        );
        $this->assertSame('html', $res['parse_mode']);
    }

    public function test_telegram_escapes_url_and_label_for_html_injection(): void
    {
        $url = "https://example.com/a?b=1&c='x'";
        $label = "A & B 'X' \"Y\"";

        $res = SignatureRenderer::render('telegram', $url, SignatureRenderer::TYPE_INLINE, $label);

        // structural expectation: label line + <a href> anchor, both HTML-escaped
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $this->assertSame(
            "\n\n{$escapedLabel}:\n<a href=\"{$escapedUrl}\">{$escapedUrl}</a>",
            $res['text']
        );

        // explicit escaped-output verification (amp / single-quote / double-quote entities)
        $amp = chr(38); // literal ampersand of the expected escaped output
        $this->assertStringContainsString(
            'A ' . $amp . 'amp; B ' . $amp . '#039;X' . $amp . '#039; ' . $amp . 'quot;Y' . $amp . 'quot;',
            $res['text']
        );
        $this->assertStringContainsString(
            'href="https://example.com/a?b=1' . $amp . 'amp;c=' . $amp . '#039;x' . $amp . '#039;"',
            $res['text']
        );

        $this->assertSame('html', $res['parse_mode']);
    }

    public function test_telegram_mixed_persian_english_label_passes_through_htmlsafe(): void
    {
        $res = SignatureRenderer::render('telegram', $this->url, SignatureRenderer::TYPE_INLINE, 'کانال ما channel 2');

        $this->assertSame(
            "\n\nکانال ما channel 2:\n<a href=\"https://t.me/ch\">https://t.me/ch</a>",
            $res['text']
        );
    }

    public function test_bale_inline_with_safe_label_uses_markdown_link(): void
    {
        $res = SignatureRenderer::render('bale', 'https://ble.ir/ch', SignatureRenderer::TYPE_INLINE, 'کانال ما');

        $this->assertSame("\n\nکانال ما:\n[https://ble.ir/ch](https://ble.ir/ch)", $res['text']);
        $this->assertNull($res['parse_mode']);
        $this->assertFalse($res['fallback']);
        $this->assertSame(SignatureRenderer::TYPE_INLINE, $res['type']);
    }

    public function test_bale_inline_with_unsafe_markdown_label_falls_back_to_plain(): void
    {
        $url = 'https://ble.ir/ch';
        $label = 'خبر [آپدیت]';

        foreach (['[', ']', '(', ')', '#', '*', '_', '~', '`'] as $char) {
            $res = SignatureRenderer::render('bale', $url, SignatureRenderer::TYPE_INLINE, "تست $char");

            $this->assertTrue($res['fallback'], "label with \"$char\" must fall back to plain format");
            $this->assertSame("\n\nتست $char:\n$url", $res['text']);
            $this->assertNull($res['parse_mode']);
        }
    }

    public function test_soroush_inline_falls_back_to_plain_label_plus_url(): void
    {
        $res = SignatureRenderer::render('soroush', $this->url, SignatureRenderer::TYPE_INLINE, 'کانال ما');

        $this->assertSame("\n\nکانال ما:\n$this->url", $res['text']);
        $this->assertNull($res['parse_mode']);
        $this->assertTrue($res['fallback']);
        $this->assertSame(SignatureRenderer::TYPE_INLINE, $res['type']);
    }

    public function test_rubika_inline_falls_back_to_plain_label_plus_url(): void
    {
        $res = SignatureRenderer::render('rubika', $this->url, SignatureRenderer::TYPE_INLINE, 'کانال ما');

        $this->assertSame("\n\nکانال ما:\n$this->url", $res['text']);
        $this->assertNull($res['parse_mode']);
        $this->assertTrue($res['fallback']);
    }

    // ─────────────────────────── inline (no label) ───────────────────────────

    public function test_inline_without_label_renders_bare_link_per_platform(): void
    {
        $tg = SignatureRenderer::render('telegram', $this->url, SignatureRenderer::TYPE_INLINE, null);
        $this->assertSame("\n\n<a href=\"https://t.me/ch\">https://t.me/ch</a>", $tg['text']);
        $this->assertSame('html', $tg['parse_mode']);
        $this->assertFalse($tg['fallback']);

        $bale = SignatureRenderer::render('bale', $this->url, SignatureRenderer::TYPE_INLINE, null);
        $this->assertSame("\n\n[https://t.me/ch](https://t.me/ch)", $bale['text']);
        $this->assertNull($bale['parse_mode']);
        $this->assertFalse($bale['fallback']);

        $soroush = SignatureRenderer::render('soroush', $this->url, SignatureRenderer::TYPE_INLINE, null);
        $this->assertSame("\n\n$this->url", $soroush['text']);
        $this->assertNull($soroush['parse_mode']);
        $this->assertFalse($soroush['fallback']);
    }

    // ─────────────────────────── raw / plain / none ───────────────────────────

    public function test_raw_type_renders_bare_url_without_parse_mode(): void
    {
        $res = SignatureRenderer::render('bale', $this->url, SignatureRenderer::TYPE_RAW, null);

        $this->assertSame("\n\n$this->url", $res['text']);
        $this->assertNull($res['parse_mode']);
        $this->assertSame(SignatureRenderer::TYPE_RAW, $res['type']);
        $this->assertFalse($res['fallback']);
    }

    public function test_plain_type_renders_label_only_without_url(): void
    {
        $res = SignatureRenderer::render('bale', $this->url, SignatureRenderer::TYPE_PLAIN, 'فقط متن');

        $this->assertSame("\n\nفقط متن", $res['text']);
        $this->assertNull($res['parse_mode']);
        $this->assertSame(SignatureRenderer::TYPE_PLAIN, $res['type']);
    }

    public function test_plain_type_without_label_renders_empty_text(): void
    {
        $res = SignatureRenderer::render('bale', $this->url, SignatureRenderer::TYPE_PLAIN, null);

        $this->assertSame('', $res['text']);
        $this->assertSame(SignatureRenderer::TYPE_PLAIN, $res['type']);
    }

    public function test_none_type_renders_empty_result(): void
    {
        $res = SignatureRenderer::render('telegram', $this->url, SignatureRenderer::TYPE_NONE, 'label');

        $this->assertSame(['text' => '', 'parse_mode' => null, 'type' => 'none', 'fallback' => false, 'button' => null], $res);
    }

    public function test_empty_url_renders_empty_result_regardless_of_type(): void
    {
        $res = SignatureRenderer::render('telegram', '', SignatureRenderer::TYPE_INLINE, 'label');

        $this->assertSame(['text' => '', 'parse_mode' => null, 'type' => 'inline', 'fallback' => false, 'button' => null], $res);
    }

    public function test_whitespace_only_label_is_treated_as_no_label(): void
    {
        $res = SignatureRenderer::render('soroush', $this->url, SignatureRenderer::TYPE_INLINE, '   ');

        $this->assertSame("\n\n$this->url", $res['text']);
        $this->assertFalse($res['fallback']);
    }

    // ─────────────────────────── composite ───────────────────────────

    public function test_composite_with_label_renders_label_plus_link_and_types_as_composite(): void
    {
        $res = SignatureRenderer::render('telegram', $this->url, SignatureRenderer::TYPE_COMPOSITE, 'کانال ما');

        $this->assertSame(
            "\n\nکانال ما:\n<a href=\"https://t.me/ch\">https://t.me/ch</a>",
            $res['text']
        );
        $this->assertSame(SignatureRenderer::TYPE_COMPOSITE, $res['type']);
        $this->assertSame('html', $res['parse_mode']);
    }

    // ─────────────────────────── url button ───────────────────────────

    public function test_button_on_telegram_renders_label_text_and_url_button(): void
    {
        $res = SignatureRenderer::render('telegram', $this->url, SignatureRenderer::TYPE_BUTTON, 'برو');

        $this->assertSame("\n\nبرو", $res['text']);
        $this->assertSame('html', $res['parse_mode']);
        $this->assertSame(SignatureRenderer::TYPE_BUTTON, $res['type']);
        $this->assertFalse($res['fallback']);
        $this->assertSame(
            ['inline_keyboard' => [[['text' => 'برو', 'url' => $this->url]]]],
            $res['button']
        );
    }

    public function test_button_on_telegram_without_label_uses_url_as_button_text(): void
    {
        $res = SignatureRenderer::render('telegram', $this->url, SignatureRenderer::TYPE_BUTTON, null);

        $this->assertSame('', $res['text']);
        $this->assertNull($res['parse_mode']);
        $this->assertSame(
            ['inline_keyboard' => [[['text' => $this->url, 'url' => $this->url]]]],
            $res['button']
        );
    }

    public function test_button_on_bale_is_supported_with_raw_label(): void
    {
        $res = SignatureRenderer::render('bale', $this->url, SignatureRenderer::TYPE_BUTTON, 'برو');

        $this->assertSame("\n\nبرو", $res['text']);
        $this->assertNull($res['parse_mode']);
        $this->assertSame(
            ['inline_keyboard' => [[['text' => 'برو', 'url' => $this->url]]]],
            $res['button']
        );
        $this->assertFalse($res['fallback']);
    }

    public function test_button_escapes_html_label_on_telegram(): void
    {
        $res = SignatureRenderer::render('telegram', $this->url, SignatureRenderer::TYPE_BUTTON, 'A & B');

        // label in the caption text is HTML-escaped (A & B) to survive parse_mode=html
        $amp = chr(38);
        $this->assertSame("\n\nA " . $amp . "amp; B", $res['text']);
        // button text stays RAW (Telegram renders it literally, no parse_mode applied to buttons)
        $this->assertSame('A & B', $res['button']['inline_keyboard'][0][0]['text']);
    }

    /** @dataProvider unsupportedButtonPlatforms */
    public function test_button_on_unsupported_platform_falls_back_to_inline(string $platform): void
    {
        $res = SignatureRenderer::render($platform, $this->url, SignatureRenderer::TYPE_BUTTON, 'برو');

        // link must never be dropped: rendered in the platform's own inline format
        $expected = $platform === 'eitaa'
            ? "\n\nبرو:\n<a href=\"$this->url\">$this->url</a>"
            : "\n\nبرو:\n$this->url";

        $this->assertSame($expected, $res['text']);
        $this->assertSame(SignatureRenderer::TYPE_INLINE, $res['type']);
        $this->assertTrue($res['fallback']);
        $this->assertNull($res['button']);
        $this->assertSame($platform === 'eitaa' ? 'html' : null, $res['parse_mode']);
    }

    /** @return array<string, array{0: string}> */
    public static function unsupportedButtonPlatforms(): array
    {
        return [
            'soroush' => ['soroush'],
            'rubika' => ['rubika'],
            'eitaa' => ['eitaa'],
        ];
    }

    // ─────────────────────────── caption limit ───────────────────────────

    public function test_caption_max_is_1024_and_fits_caption_is_inclusive(): void
    {
        $this->assertSame(1024, SignatureRenderer::CAPTION_MAX);
        $this->assertTrue(SignatureRenderer::fitsCaption(1024, 0));
        $this->assertTrue(SignatureRenderer::fitsCaption(1020, 4));
        $this->assertFalse(SignatureRenderer::fitsCaption(1021, 4));
        $this->assertFalse(SignatureRenderer::fitsCaption(0, 1025));
    }

    // ─────────────────────────── legacy format ───────────────────────────

    public function test_render_legacy_matches_old_build_signature_format(): void
    {
        $this->assertSame(
            'در بله:' . "\n" . 'https://ble.ir/ch',
            SignatureRenderer::renderLegacy('bale', 'https://ble.ir/ch', 'در بله')
        );
    }

    public function test_render_legacy_with_null_url_renders_label_line_only(): void
    {
        $this->assertSame('در بله:' . "\n", SignatureRenderer::renderLegacy('bale', null, 'در بله'));
    }

    // ───────────────────────────── multi-line (smart) ─────────────────────────────

    private const LINKS = [
        ['url' => 'https://ble.ir/ch', 'label' => 'کانال بله'],
        ['url' => 'https://t.me/ch', 'label' => 'کانال تلگرام'],
        ['url' => 'https://soroush.me/ch', 'label' => null],
    ];

    public function test_render_multi_telegram_single_line_uses_html_anchors_and_custom_separator(): void
    {
        $res = SignatureRenderer::renderMulti('telegram', self::LINKS, 'single_line', '–');

        $this->assertSame(
            "\n\n"
            . '<a href="https://ble.ir/ch">کانال بله</a>'
            . '–'
            . '<a href="https://t.me/ch">کانال تلگرام</a>'
            . '–'
            . '<a href="https://soroush.me/ch">https://soroush.me/ch</a>',
            $res['text']
        );
        $this->assertSame('html', $res['parse_mode']);
        $this->assertSame('multi', $res['type']);
        $this->assertFalse($res['fallback']);
        $this->assertNull($res['button']);
    }

    public function test_render_multi_telegram_multi_line_puts_label_on_own_line(): void
    {
        $res = SignatureRenderer::renderMulti('telegram', self::LINKS, 'multi_line');

        $this->assertSame(
            "\n\n"
            . "کانال بله:\n<a href=\"https://ble.ir/ch\">کانال بله</a>"
            . "\n"
            . "کانال تلگرام:\n<a href=\"https://t.me/ch\">کانال تلگرام</a>"
            . "\n"
            . '<a href="https://soroush.me/ch">https://soroush.me/ch</a>',
            $res['text']
        );
    }

    public function test_render_multi_bale_single_line_uses_markdown_links_for_safe_labels(): void
    {
        $res = SignatureRenderer::renderMulti('bale', self::LINKS, 'single_line', ' | ');

        $this->assertSame(
            "\n\n[کانال بله](https://ble.ir/ch) | [کانال تلگرام](https://t.me/ch) | https://soroush.me/ch",
            $res['text']
        );
        $this->assertNull($res['parse_mode']);
        // the label-less third link is plain text on bale, not a fallback
        $this->assertFalse($res['fallback']);
    }

    public function test_render_multi_bale_unsafe_markdown_label_falls_back_to_plain(): void
    {
        $links = [
            ['url' => 'https://ble.ir/ch', 'label' => 'کانال [بله]'],
            ['url' => 'https://t.me/ch', 'label' => 'تلگرام'],
        ];

        $res = SignatureRenderer::renderMulti('bale', $links, 'single_line');

        $this->assertSame("\n\nکانال [بله]: https://ble.ir/ch | [تلگرام](https://t.me/ch)", $res['text']);
        $this->assertTrue($res['fallback']);
    }

    public function test_render_multi_soroush_and_rubika_render_plain_label_plus_url(): void
    {
        foreach (['soroush', 'rubika'] as $platform) {
            $res = SignatureRenderer::renderMulti($platform, self::LINKS, 'single_line', '–');

            $this->assertSame(
                "\n\nکانال بله: https://ble.ir/ch–کانال تلگرام: https://t.me/ch–https://soroush.me/ch",
                $res['text']
            );
            $this->assertNull($res['parse_mode']);
            $this->assertTrue($res['fallback']);
        }
    }

    public function test_render_multi_multi_line_plain_platforms_label_line_then_url(): void
    {
        $res = SignatureRenderer::renderMulti('soroush', self::LINKS, 'multi_line');

        $this->assertSame(
            "\n\nکانال بله: https://ble.ir/ch\nکانال تلگرام: https://t.me/ch\nhttps://soroush.me/ch",
            $res['text']
        );
    }

    public function test_render_multi_escapes_html_injection_for_anchors(): void
    {
        $url = "https://example.com/a?b=1&c='x'";
        $label = "A & B <i>bold</i>";

        $res = SignatureRenderer::renderMulti('telegram', [
            ['url' => $url, 'label' => $label],
        ], 'single_line');

        $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        $this->assertSame(
            "\n\n<a href=\"" . $escapedUrl . "\">" . $escapedLabel . "</a>",
            $res['text']
        );
    }

    public function test_render_multi_truncates_labels_to_64_chars(): void
    {
        $long = str_repeat('ک', 80);
        $res = SignatureRenderer::renderMulti('soroush', [
            ['url' => 'https://ble.ir/ch', 'label' => $long],
        ], 'single_line');

        $this->assertSame("\n\n" . str_repeat('ک', 64) . ': https://ble.ir/ch', $res['text']);
    }

    public function test_render_multi_filters_empty_urls_and_returns_empty_when_none(): void
    {
        $res = SignatureRenderer::renderMulti('telegram', [
            ['url' => '', 'label' => 'x'],
            ['url' => 'https://t.me/ch', 'label' => 'تلگرام'],
        ], 'single_line');

        $this->assertSame("\n\n<a href=\"https://t.me/ch\">تلگرام</a>", $res['text']);

        $empty = SignatureRenderer::renderMulti('telegram', [
            ['url' => '', 'label' => 'x'],
            ['url' => null, 'label' => 'y'],
        ], 'multi_line');

        $this->assertSame('', $empty['text']);
        $this->assertNull($empty['parse_mode']);
        $this->assertSame('multi', $empty['type']);
        $this->assertFalse($empty['fallback']);
    }

    public function test_render_multi_blank_separator_falls_back_to_default_pipe(): void
    {
        $res = SignatureRenderer::renderMulti('soroush', [
            ['url' => 'https://a.ir', 'label' => null],
            ['url' => 'https://b.ir', 'label' => null],
        ], 'single_line', '   ');

        // whitespace-only is still a valid non-empty string → used as-is
        $this->assertSame("\n\nhttps://a.ir   https://b.ir", $res['text']);

        $default = SignatureRenderer::renderMulti('soroush', [
            ['url' => 'https://a.ir', 'label' => null],
            ['url' => 'https://b.ir', 'label' => null],
        ], 'single_line', '');

        $this->assertSame("\n\nhttps://a.ir | https://b.ir", $default['text']);
    }

    public function test_render_multi_unknown_layout_treated_as_single_line(): void
    {
        $res = SignatureRenderer::renderMulti('soroush', [
            ['url' => 'https://a.ir', 'label' => null],
            ['url' => 'https://b.ir', 'label' => null],
        ], 'nonsense');

        $this->assertSame("\n\nhttps://a.ir | https://b.ir", $res['text']);
    }

    // ─────────────────────────── capability matrix sanity ───────────────────────────

    public function test_capability_matrix_flags_url_button_only_for_telegram_and_bale(): void
    {
        $this->assertTrue(SignatureCapability::supportsUrlButton('telegram'));
        $this->assertTrue(SignatureCapability::supportsUrlButton('bale'));
        $this->assertFalse(SignatureCapability::supportsUrlButton('eitaa'));
        $this->assertFalse(SignatureCapability::supportsUrlButton('soroush'));
        $this->assertFalse(SignatureCapability::supportsUrlButton('rubika'));
    }

    public function test_capability_matrix_flags_html_inline_for_telegram_and_eitaa(): void
    {
        $this->assertTrue(SignatureCapability::supportsInlineHtml('telegram'));
        $this->assertTrue(SignatureCapability::supportsInlineHtml('eitaa'));
        $this->assertFalse(SignatureCapability::supportsInlineHtml('bale'));
        $this->assertFalse(SignatureCapability::supportsInlineHtml('soroush'));
        $this->assertFalse(SignatureCapability::supportsInlineHtml('rubika'));
    }

    public function test_capability_matrix_flags_markdown_link_for_bale_only(): void
    {
        $this->assertTrue(SignatureCapability::supportsMarkdownLink('bale'));
        $this->assertFalse(SignatureCapability::supportsMarkdownLink('telegram'));
        $this->assertFalse(SignatureCapability::supportsMarkdownLink('soroush'));
    }
}
