<?php

namespace App\Services\ContentSync;

/**
 * تقسیم متون طولانی به چند پیام پیاپی (بدون شکستن کلمات و حفظ فاصله‌ها و هشتگ‌ها).
 *
 * سقف پیش‌فرض ۳۵۰۰ کاراکتر (زیر حد ۴۰۹۶ تلگرام و حدود حد ارسال بله/ایتا).
 */
class TextChunker
{
    public const MAX_LENGTH = 3500;

    /**
     * متن را به قطعه‌های قابل ارسال تقسیم می‌کند.
     *
     * @return string[]
     */
    public static function split(string $text, int $maxLength = self::MAX_LENGTH): array
    {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }

        if (mb_strlen($text) <= $maxLength) {
            return [$text];
        }

        $lines = preg_split('/\R/u', $text) ?: [];
        $chunks = [];
        $current = '';

        foreach ($lines as $line) {
            // خطی که خودش از حد بزرگ‌تر است → بشکن‌شکنی اجباری
            if (mb_strlen($line) > $maxLength) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                $pieces = self::hardSplit($line, $maxLength);
                foreach ($pieces as $piece) {
                    $chunks[] = $piece;
                }
                continue;
            }

            $candidate = $current === '' ? $line : $current . "\n" . $line;
            if (mb_strlen($candidate) <= $maxLength) {
                $current = $candidate;
            } else {
                $chunks[] = $current;
                $current = $line;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks === [] ? [''] : $chunks;
    }

    /**
     * شکست اجباری یک خط بسیار طولانی (در مرز کلمات تا حد امکان).
     *
     * @return string[]
     */
    private static function hardSplit(string $line, int $maxLength): array
    {
        $pieces = [];
        $rest = $line;

        while (mb_strlen($rest) > $maxLength) {
            $cut = mb_substr($rest, 0, $maxLength);
            $space = mb_strrpos($cut, ' ');
            if ($space !== false && $space > (int) ($maxLength * 0.5)) {
                $cut = mb_substr($cut, 0, $space);
            }
            $pieces[] = $cut;
            $rest = ltrim(mb_substr($rest, mb_strlen($cut)), ' \t');
        }

        if ($rest !== '') {
            $pieces[] = $rest;
        }

        return $pieces;
    }
}
