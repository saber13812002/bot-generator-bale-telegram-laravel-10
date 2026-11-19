<?php

namespace Tests\Unit;

use App\Helpers\QuranHelper;
use PHPUnit\Framework\TestCase;

class QuranAudioHelpersTest extends TestCase
{
    public function test_splitTextAtPunctuation_short_text_single_chunk()
    {
        $this->assertSame(['سلام علیکم'], QuranHelper::splitTextAtPunctuation('سلام علیکم', 1024));
    }

    public function test_splitTextAtPunctuation_empty_text()
    {
        $this->assertSame([], QuranHelper::splitTextAtPunctuation('', 100));
        $this->assertSame([], QuranHelper::splitTextAtPunctuation('   ', 100));
    }

    public function test_splitTextAtPunctuation_non_positive_max_len_single_chunk()
    {
        $this->assertSame(['abc def'], QuranHelper::splitTextAtPunctuation('abc def', 0));
        $this->assertSame(['abc def'], QuranHelper::splitTextAtPunctuation('abc def', -5));
    }

    public function test_splitTextAtPunctuation_breaks_at_punctuation_within_budget()
    {
        $text = 'word1 word2 word3 word4 word5 word6 word7 word8';
        $chunks = QuranHelper::splitTextAtPunctuation($text, 12);

        // همه تکه‌ها حداکثر ۱۲ کاراکتر
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(12, mb_strlen($chunk));
        }
        // محتوا حفظ شده (ترتیبی، بدون حذف کلمه)
        $this->assertSame($text, trim(implode(' ', $chunks)));
        // چون متن از ۱۲ بلندتر است، حتماً چند تکه می‌شود
        $this->assertGreaterThan(1, count($chunks));
    }

    public function test_splitTextAtPunctuation_hard_cut_when_no_breaker()
    {
        $text = str_repeat('x', 50);
        $chunks = QuranHelper::splitTextAtPunctuation($text, 10);

        $this->assertCount(5, $chunks);
        foreach ($chunks as $chunk) {
            $this->assertSame(10, mb_strlen($chunk));
        }
        $this->assertSame($text, implode('', $chunks));
    }

    public function test_splitTextAtPunctuation_persian_punctuation()
    {
        $text = 'این یک جمله است. این جمله دوم است؟ این جمله سوم است؛';
        $chunks = QuranHelper::splitTextAtPunctuation($text, 25);

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(25, mb_strlen($chunk));
        }
        $this->assertSame(trim(str_replace(' ', '', $text)), trim(str_replace(' ', '', implode('', $chunks))));
    }

    public function test_buildInlineKeyboard_empty()
    {
        $this->assertSame('', QuranHelper::buildInlineKeyboard([]));
    }

    public function test_buildInlineKeyboard_assoc_buttons_two_rows()
    {
        $json = QuranHelper::buildInlineKeyboard([
            ['text' => 'Next', 'callback_data' => '/sure2ayah2'],
            ['text' => 'Prev', 'callback_data' => '/sure2ayah1'],
            ['text' => 'Next Surah', 'callback_data' => '/sure3ayah1'],
            ['text' => 'Prev Surah', 'callback_data' => '/sure1ayah1'],
        ]);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('inline_keyboard', $decoded);
        $this->assertCount(2, $decoded['inline_keyboard']);
        $this->assertSame([
            'text' => 'Next',
            'callback_data' => '/sure2ayah2',
        ], $decoded['inline_keyboard'][0][0]);
        $this->assertCount(2, $decoded['inline_keyboard'][0]);
        $this->assertSame(
            '/sure3ayah1',
            $decoded['inline_keyboard'][1][0]['callback_data']
        );
    }

    public function test_buildInlineKeyboard_flat_pairs_and_skips_empty_callback()
    {
        $json = QuranHelper::buildInlineKeyboard([
            ['Next', '/sure2ayah2'],
            ['No Command', ''],
            ['Prev', '/sure2ayah1'],
        ]);

        $decoded = json_decode($json, true);
        // دکمه‌ی بدون callback_data حذف می‌شود → ردیف اول فقط یک دکمه دارد
        $this->assertCount(1, $decoded['inline_keyboard'][0]);
        $this->assertSame('Next', $decoded['inline_keyboard'][0][0]['text']);
        $this->assertSame('/sure2ayah1', $decoded['inline_keyboard'][1][0]['callback_data']);
    }

    public function test_buildInlineKeyboard_odd_count_last_row_single_button()
    {
        $json = QuranHelper::buildInlineKeyboard([
            ['A', '/a'],
            ['B', '/b'],
            ['C', '/c'],
        ]);
        $decoded = json_decode($json, true);
        $this->assertCount(2, $decoded['inline_keyboard']);
        $this->assertCount(1, $decoded['inline_keyboard'][1]);
    }
}
