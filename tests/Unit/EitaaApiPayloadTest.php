<?php

namespace Tests\Unit;

use App\Helpers\BotHelper;
use App\Helpers\ProductLinkMessageHelper;
use Tests\TestCase;

/**
 * Eitaa (aitaayar) payload contract — per the OFFICIAL API documentation.
 *
 * Source of truth: docs/features/OFFICIAL/EitaaYar_API_Documentation.pdf
 * ("راهنمای رابط برنامهنویسی ایتایار — نسخه آزمایشی", downloaded from
 * https://eitaayar.ir/api).
 *
 * Official facts enforced here:
 *  - Only methods: getMe, sendMessage, sendFile.
 *  - sendMessage params: chat_id(required), text(required), title,
 *    disable_notification, reply_to_message_id, date, pin, viewCountForDelete.
 *    There is NO parse_mode and NO HTML/markdown formatting parameter;
 *    text is rendered verbatim (sending <a href> shows raw tags in the client).
 *  - sendFile: same as sendMessage but `caption` replaces `text` (file required).
 *  - Response: JSON with `ok` boolean (true = success, false = error).
 *
 * The client auto-linkifies bare URLs, so the production format for Eitaa is
 * plain "label: url" text (same approach as soroush/rubika).
 *
 * Bug under test (RCA): Eitaa previously received parse_mode=html + HTML <a>
 * tags (a Telegram-compat assumption) → raw HTML rendered in the client.
 */
class EitaaApiPayloadTest extends TestCase
{
    private const URL = 'https://sharabebeheshti.ir/shb1?random_id=abc123&id=63&utm_source=aita';
    private const LABEL = 'مشاهده صفحه محصول';
    /** URL as it appears inside telegram HTML anchors (htmlspecialchars-escaped). */
    private static function urlHtml(): string
    {
        return htmlspecialchars(self::URL, ENT_QUOTES, 'UTF-8');
    }

    // ─────────────────────────── link text generation ───────────────────────────

    public function test_build_short_link_eitaa_never_emits_html_tags(): void
    {
        $link = ProductLinkMessageHelper::buildShortLink(self::URL, self::LABEL, 'eitaa');

        // Requirement: raw HTML tags must NOT be shown to the Eitaa client.
        $this->assertStringNotContainsString('<', $link);
        $this->assertStringNotContainsString('>', $link);
        $this->assertStringNotContainsString('a href', $link);

        // Requirement: the link renders as a plain, client-auto-linkified URL.
        $this->assertSame(self::LABEL . ': ' . self::URL, $link);
    }

    public function test_build_short_link_eitaa_with_html_special_url_is_still_plain(): void
    {
        $specialUrl = 'https://eitaa.com/ch?ref=x&y=1';

        $link = ProductLinkMessageHelper::buildShortLink($specialUrl, self::LABEL, 'eitaa');

        $this->assertStringNotContainsString('<', $link);
        $this->assertStringNotContainsString('>', $link);
        $this->assertStringContainsString($specialUrl, $link);
    }

    public function test_eitaa_signature_link_is_bare_url_like_soroush_rubika(): void
    {
        $this->assertSame(self::URL, ProductLinkMessageHelper::eitaaSignatureLink(self::URL));
        $this->assertSame(self::URL, ProductLinkMessageHelper::soroushSignatureLink(self::URL));
        $this->assertSame(self::URL, ProductLinkMessageHelper::rubikaSignatureLink(self::URL));

        // telegram must keep its official HTML anchor (unchanged behavior):
        // the href is htmlspecialchars-escaped, the anchor text is not.
        $this->assertSame(
            '<a href="' . self::urlHtml() . '">' . self::URL . '</a>',
            ProductLinkMessageHelper::telegramSignatureLink(self::URL)
        );
    }

    public function test_signature_link_dispatch_eitaa_never_emits_html(): void
    {
        $link = ProductLinkMessageHelper::signatureLink(self::URL, 'eitaa');

        $this->assertStringNotContainsString('<', $link);
        $this->assertStringNotContainsString('>', $link);
        $this->assertSame(self::URL, $link);
    }

    // ─────────────────────────── parse_mode contract ───────────────────────────

    public function test_eitaa_needs_no_parse_mode(): void
    {
        $this->assertFalse(ProductLinkMessageHelper::needsParseMode('eitaa'));
        $this->assertNull(ProductLinkMessageHelper::parseModeForPlatform('eitaa'));
    }

    public function test_telegram_keeps_html_parse_mode(): void
    {
        $this->assertTrue(ProductLinkMessageHelper::needsParseMode('telegram'));
        $this->assertSame('html', ProductLinkMessageHelper::parseModeForPlatform('telegram'));
    }

    public function test_other_platforms_keep_no_parse_mode(): void
    {
        foreach (['bale', 'soroush', 'rubika'] as $platform) {
            $this->assertFalse(ProductLinkMessageHelper::needsParseMode($platform), $platform);
            $this->assertNull(ProductLinkMessageHelper::parseModeForPlatform($platform), $platform);
        }
        $this->assertFalse(ProductLinkMessageHelper::needsParseMode(null));
        $this->assertNull(ProductLinkMessageHelper::parseModeForPlatform(null));
    }

    // ─────────────────────────── API POST fields (official doc) ───────────────────────────

    public function test_build_eitaa_api_fields_send_message_uses_only_official_params(): void
    {
        $fields = BotHelper::buildEitaaApiFields('@sharabebeheshti', 'متن پیام', false);

        // Official sendMessage params only: chat_id + text. NO parse_mode.
        $this->assertSame([
            'chat_id' => '@sharabebeheshti',
            'text' => 'متن پیام',
        ], $fields);
    }

    public function test_build_eitaa_api_fields_send_file_uses_caption_plus_text_compat(): void
    {
        $caption = "عنوان\nhttps://sharabebeheshti.ir/shb1?random_id=abc123&id=63";

        $fields = BotHelper::buildEitaaApiFields('@sharabebeheshti', $caption, true);

        // Official doc: sendFile uses `caption` instead of `text`.
        // User decision: send BOTH caption (official) and text (production
        // compat, proven working) with the same value.
        $this->assertSame('@sharabebeheshti', $fields['chat_id']);
        $this->assertSame($caption, $fields['caption']);
        $this->assertSame($caption, $fields['text']);
        $this->assertArrayNotHasKey('parse_mode', $fields);
        $this->assertArrayNotHasKey('file', $fields); // file is attached in call_eitaa_api
    }

    public function test_build_eitaa_api_fields_numeric_chat_id(): void
    {
        $fields = BotHelper::buildEitaaApiFields(123456789, 'متن', false);

        $this->assertSame(['chat_id' => 123456789, 'text' => 'متن'], $fields);
    }

    // ─────────────────────────── production caption path (Sharabe Beheshti) ───────────────────────────

    public function test_sharabe_product_block_for_eitaa_contains_no_html(): void
    {
        $block = ProductLinkMessageHelper::buildSharabeProductBlock(self::URL, 'eitaa');

        $this->assertStringNotContainsString('<', $block);
        $this->assertStringNotContainsString('>', $block);
        $this->assertStringContainsString(self::URL, $block);
        $this->assertStringContainsString(ProductLinkMessageHelper::buildSharabeBeheshtiIntro(), $block);
    }

    public function test_channel_promo_block_for_eitaa_renders_plain_urls_only(): void
    {
        $block = ProductLinkMessageHelper::buildChannelPromoBlock('eitaa');

        $this->assertStringNotContainsString('<', $block);
        $this->assertStringNotContainsString('>', $block);
        // All configured channel URLs must appear verbatim (client auto-linkifies them).
        foreach (['https://eitaa.com/sharabebeheshti', 'https://ble.ir/sharabebeheshti', 'https://t.me/sharabebeheshti_ir'] as $channelUrl) {
            $this->assertStringContainsString($channelUrl, $block);
        }
    }

    public function test_full_message_eitaa_never_contains_html_even_with_icon(): void
    {
        // withIcon used to hardcode an HTML anchor — it must be plain now.
        $withIcon = ProductLinkMessageHelper::buildFullMessage(self::URL, 'عنوان تست', withIcon: true, platformSlug: 'eitaa');
        $plain = ProductLinkMessageHelper::buildFullMessage(self::URL, 'عنوان تست', withIcon: false, platformSlug: 'eitaa');

        foreach ([$withIcon, $plain] as $message) {
            $this->assertStringNotContainsString('<', $message);
            $this->assertStringNotContainsString('>', $message);
            $this->assertStringNotContainsString('xf-eitaa', $message);
            $this->assertStringContainsString(self::URL, $message);
        }
    }

    public function test_plain_url_message_is_plain(): void
    {
        $message = ProductLinkMessageHelper::buildPlainUrlMessage(self::URL, 'عنوان تست');

        $this->assertStringNotContainsString('<', $message);
        $this->assertStringNotContainsString('>', $message);
        $this->assertStringContainsString(self::URL, $message);
    }

    // ─────────────────────────── regression: other platforms unchanged ───────────────────────────

    public function test_telegram_short_link_keeps_html_anchor(): void
    {
        // href is htmlspecialchars-escaped; the label is the raw label.
        $this->assertSame(
            '<a href="' . self::urlHtml() . '">' . self::LABEL . '</a>',
            ProductLinkMessageHelper::buildShortLink(self::URL, self::LABEL, 'telegram')
        );
    }

    public function test_telegram_short_link_escapes_html_injection(): void
    {
        // Both href and label are escaped — an injected <b> stays literal text.
        $this->assertSame(
            '<a href="https://x/">' . htmlspecialchars('<b>bad</b>', ENT_QUOTES, 'UTF-8') . '</a>',
            ProductLinkMessageHelper::buildShortLink('https://x/', '<b>bad</b>', 'telegram')
        );
    }

    public function test_bale_short_link_keeps_markdown_link(): void
    {
        $this->assertSame(
            '[' . self::LABEL . '](' . self::URL . ')',
            ProductLinkMessageHelper::buildShortLink(self::URL, self::LABEL, 'bale')
        );
    }

    public function test_soroush_and_rubika_short_links_keep_plain_label_plus_url(): void
    {
        $expected = self::LABEL . ': ' . self::URL;
        $this->assertSame($expected, ProductLinkMessageHelper::buildShortLink(self::URL, self::LABEL, 'soroush'));
        $this->assertSame($expected, ProductLinkMessageHelper::buildShortLink(self::URL, self::LABEL, 'rubika'));
        $this->assertSame($expected, ProductLinkMessageHelper::buildShortLink(self::URL, self::LABEL, null));
    }

    public function test_sharabe_product_block_telegram_keeps_html(): void
    {
        $block = ProductLinkMessageHelper::buildSharabeProductBlock(self::URL, 'telegram');

        $this->assertStringContainsString('<a href="' . self::urlHtml() . '">' . self::LABEL . '</a>', $block);
    }

    public function test_sharabe_product_block_bale_keeps_markdown(): void
    {
        $block = ProductLinkMessageHelper::buildSharabeProductBlock(self::URL, 'bale');

        $this->assertStringContainsString('[' . self::LABEL . '](' . self::URL . ')', $block);
    }
}
