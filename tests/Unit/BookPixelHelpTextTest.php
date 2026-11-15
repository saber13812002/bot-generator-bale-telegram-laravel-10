<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * AI-PRODUCTION-VERIFICATION-07 / Task B — Book Pixel help text (TDD, RED first).
 *
 * Root cause: the Book Pixel strings in lang/fa/bot.php and lang/en/bot.php were
 * written in PHP single-quoted strings, so `\n` stayed a literal two-character
 * sequence (backslash + n) instead of a real newline (0x0A). The bot therefore
 * printed raw "\n\n" text to the user.
 *
 * Contract (deterministic — asserts the translated text itself):
 *  - book_pixel_help / book_pixel_welcome / book_pixel_score_message contain
 *    REAL newlines (0x0A), NOT the literal two-char sequence `\n`.
 *  - book_pixel_help lists all 9 required commands in both fa and en.
 *
 * These fail RED on the single-quoted lang files and go GREEN after converting
 * them to double-quoted strings (matching the healthy bots, e.g. BookLibraryReader).
 */
class BookPixelHelpTextTest extends TestCase
{
    private const FA_COMMANDS = [
        '/start - شروع',
        '/help - راهنما',
        '/cover - ارسال عکس جلد',
        '/search <query> - جستجوی کتاب',
        '/drafts - لیست پیش‌نویس‌ها',
        '/cancel - لغو فرآیند',
        '/skip - رد کردن فیلد اختیاری',
        '/score - نمایش امتیاز',
        '/stats - آمار (فقط در گروه)',
    ];

    private const EN_COMMANDS = [
        '/start - Start',
        '/help - Help',
        '/cover - Send cover image',
        '/search <query> - Search books',
        '/drafts - List drafts',
        '/cancel - Cancel process',
        '/skip - Skip optional field',
        '/score - Show score',
        '/stats - Statistics (group only)',
    ];

    private function fa(string $key): string
    {
        return (string) trans('bot.' . $key, [], 'fa');
    }

    private function en(string $key): string
    {
        return (string) trans('bot.' . $key, [], 'en');
    }

    private function assertRealNewlinesNoRaw(string $text, string $label): void
    {
        $this->assertNotSame('', $text, $label . ': translation is empty/missing');

        // REAL newline (0x0A) must be present.
        $this->assertTrue(
            str_contains($text, "\n"),
            $label . ': expected a real newline (0x0A) in the text'
        );

        // The literal two-character sequence backslash + n must NOT be present.
        $this->assertFalse(
            str_contains($text, '\\n'),
            $label . ': contains a RAW `\n` (literal backslash-n) instead of a real newline'
        );
    }

    private function assertHelp(string $text, array $commands, string $label): void
    {
        $this->assertRealNewlinesNoRaw($text, $label);

        // A multi-line help needs more than one newline.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($text, "\n"),
            $label . ': expected multiple real newlines for a multi-line help text'
        );

        foreach ($commands as $command) {
            $this->assertStringContainsString(
                $command,
                $text,
                $label . ': missing required command line "' . $command . '"'
            );
        }
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | /help — fa
    |──────────────────────────────────────────────────────────────────────
    */
    public function test_fa_help_has_real_newlines_not_raw_backslash_n(): void
    {
        $this->assertRealNewlinesNoRaw($this->fa('book_pixel_help'), 'fa book_pixel_help');
    }

    public function test_fa_help_lists_all_required_commands(): void
    {
        $this->assertHelp($this->fa('book_pixel_help'), self::FA_COMMANDS, 'fa book_pixel_help');
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | /help — en (fallback locale, explicit)
    |──────────────────────────────────────────────────────────────────────
    */
    public function test_en_help_has_real_newlines_not_raw_backslash_n(): void
    {
        $this->assertRealNewlinesNoRaw($this->en('book_pixel_help'), 'en book_pixel_help');
    }

    public function test_en_help_lists_all_required_commands(): void
    {
        $this->assertHelp($this->en('book_pixel_help'), self::EN_COMMANDS, 'en book_pixel_help');
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | Same latent bug: welcome + score message (fa + en)
    |──────────────────────────────────────────────────────────────────────
    */
    public function test_fa_welcome_has_real_newlines_not_raw_backslash_n(): void
    {
        $this->assertRealNewlinesNoRaw($this->fa('book_pixel_welcome'), 'fa book_pixel_welcome');
    }

    public function test_en_welcome_has_real_newlines_not_raw_backslash_n(): void
    {
        $this->assertRealNewlinesNoRaw($this->en('book_pixel_welcome'), 'en book_pixel_welcome');
    }

    public function test_fa_score_message_has_real_newlines_and_placeholders(): void
    {
        $text = $this->fa('book_pixel_score_message');
        $this->assertRealNewlinesNoRaw($text, 'fa book_pixel_score_message');
        foreach (['{points}', '{scans}', '{voices}'] as $placeholder) {
            $this->assertStringContainsString($placeholder, $text, 'fa score message lost ' . $placeholder);
        }
    }

    public function test_en_score_message_has_real_newlines_and_placeholders(): void
    {
        $text = $this->en('book_pixel_score_message');
        $this->assertRealNewlinesNoRaw($text, 'en book_pixel_score_message');
        foreach (['{points}', '{scans}', '{voices}'] as $placeholder) {
            $this->assertStringContainsString($placeholder, $text, 'en score message lost ' . $placeholder);
        }
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | Sanity — default-locale lookup must resolve (regression guard)
    |──────────────────────────────────────────────────────────────────────
    */
    public function test_default_locale_help_resolves_to_fa_string(): void
    {
        // app default locale is 'fa'; trans() without an explicit locale must
        // return the (fixed) Persian help, never the "key missing" fallback.
        $this->assertStringContainsString('راهنمای ربات یک پیکسل کتاب', trans('bot.book_pixel_help'));
    }
}
