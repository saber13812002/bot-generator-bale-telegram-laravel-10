<?php

namespace Tests\Unit\ContentSync;

use App\Services\ContentSync\TextChunker;
use PHPUnit\Framework\TestCase;

class TextChunkerTest extends TestCase
{
    public function test_short_text_stays_single_chunk(): void
    {
        $this->assertSame(['متن کوتاه'], TextChunker::split('متن کوتاه'));
    }

    public function test_empty_text_returns_single_empty_chunk(): void
    {
        $this->assertSame([''], TextChunker::split(''));
        $this->assertSame([''], TextChunker::split('   '));
    }

    public function test_text_exactly_at_limit_is_one_chunk(): void
    {
        $text = str_repeat('a', 100);
        $this->assertCount(1, TextChunker::split($text, 100));
    }

    public function test_long_text_is_split_into_valid_chunks(): void
    {
        $text = str_repeat('word ', 300); // 1500 chars
        $chunks = TextChunker::split($text, 100);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(100, mb_strlen($chunk));
        }
        // محتوا از بین نمی‌رود: همه کلمات باقی می‌مانند
        $joined = str_replace(["\n", ' '], '', implode('', $chunks));
        $this->assertSame(str_repeat('word', 300), $joined);
    }

    public function test_split_happens_on_line_boundaries_when_possible(): void
    {
        $text = "line one\nline two\nline three";
        $chunks = TextChunker::split($text, 12);

        // هیچ chunk نباید وسط خط بشکند: خطوط هر chunk باید دقیقاً خطوط اصلی باشند
        foreach ($chunks as $chunk) {
            $chunkLines = preg_split('/\R/u', $chunk);
            $this->assertTrue(
                in_array('line one', $chunkLines, true)
                    || in_array('line two', $chunkLines, true)
                    || in_array('line three', $chunkLines, true),
                'chunk وسط یک خط شکسته شده: ' . var_export($chunk, true)
            );
        }
        $this->assertLessThanOrEqual(12, mb_strlen($chunks[0]));
        $this->assertSame('line one', $chunks[0]);
    }

    public function test_single_very_long_line_is_hard_split_without_losing_text(): void
    {
        $line = 'word ' . str_repeat('x', 250) . ' word';
        $chunks = TextChunker::split($line, 100);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(100, mb_strlen($chunk));
        }
        $this->assertSame(str_replace(' ', '', $line), str_replace(' ', '', implode('', $chunks)));
    }

    public function test_multiline_packing_respects_limit(): void
    {
        $lines = [];
        for ($i = 0; $i < 10; $i++) {
            $lines[] = 'line-' . $i . '-' . str_repeat('z', 40);
        }
        $text = implode("\n", $lines);
        $chunks = TextChunker::split($text, 100);

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(100, mb_strlen($chunk));
        }
        // هر خط در یک chunk است (شکست بین خطوط)
        foreach ($lines as $line) {
            $found = false;
            foreach ($chunks as $chunk) {
                if (str_contains($chunk, $line)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Line not found intact: {$line}");
        }
    }

    public function test_persian_text_handles_multibyte_correctly(): void
    {
        $text = str_repeat('متن فارسی خیلی طولانی برای تست ', 300);
        $chunks = TextChunker::split($text, 500);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(500, mb_strlen($chunk));
        }
        $joined = str_replace(["\n", ' '], '', implode('', $chunks));
        $this->assertSame(str_replace(' ', '', $text), $joined);
    }
}
