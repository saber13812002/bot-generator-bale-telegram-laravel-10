<?php

namespace App\Services;

class ListBotMenuParser
{
    /**
     * Pattern to detect URL at end of line (http, https, t.me, @username).
     */
    private const LINK_PATTERN = '/\s*(https?:\/\/[^\s]+|t\.me\/[^\s]+|@[a-zA-Z0-9_]+)\s*$/u';

    /**
     * Parse multiline menu text into a tree structure.
     * Lines: (N dashes) + space + title + [optional: ': ' + link or space + link]
     * Level = number of leading '-' (1 = root title, 2 = first level items, 3 = nested).
     *
     * @param string $text Raw input from admin
     * @return array{title: string, link?: string, children?: array} Root node; empty title and no children if parse failed
     */
    public function parse(string $text): array
    {
        $lines = $this->splitLines($text);
        if (empty($lines)) {
            return ['title' => '', 'children' => []];
        }

        $items = [];
        foreach ($lines as $line) {
            $parsed = $this->parseLine($line);
            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        if (empty($items)) {
            return ['title' => '', 'children' => []];
        }

        return $this->buildTree($items);
    }

    /**
     * @return array<array{level: int, title: string, link?: string}>
     */
    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        $level = 0;
        $i = 0;
        $len = strlen($line);
        while ($i < $len && $line[$i] === '-') {
            $level++;
            $i++;
        }

        $rest = trim(substr($line, $i));
        if ($rest === '') {
            return null;
        }

        $title = $rest;
        $link = null;

        if (preg_match(self::LINK_PATTERN, $rest, $m)) {
            $link = $this->normalizeLink(trim($m[1]));
            $title = trim(preg_replace(self::LINK_PATTERN, '', $rest));
        } else {
            $colonPos = strpos($rest, ':');
            if ($colonPos !== false) {
                $before = trim(substr($rest, 0, $colonPos));
                $after = trim(substr($rest, $colonPos + 1));
                if ($after !== '' && $this->looksLikeLink($after)) {
                    $title = $before;
                    $link = $this->normalizeLink($after);
                }
            }
        }

        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $result = ['level' => $level, 'title' => $title];
        if ($link !== null) {
            $result['link'] = $link;
        }
        return $result;
    }

    private function looksLikeLink(string $s): bool
    {
        return preg_match('/^(https?:\/\/|t\.me\/|@)/u', trim($s)) === 1
            || filter_var(trim($s), FILTER_VALIDATE_URL) !== false;
    }

    private function normalizeLink(string $link): string
    {
        $link = trim($link);
        if (str_starts_with($link, '@')) {
            return 'https://t.me/' . ltrim($link, '@');
        }
        if (preg_match('/^t\.me\//i', $link)) {
            return 'https://' . $link;
        }
        if (!str_starts_with($link, 'http://') && !str_starts_with($link, 'https://')) {
            return 'https://' . $link;
        }
        return $link;
    }

    /**
     * @return string[]
     */
    private function splitLines(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        return array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));
    }

    /**
     * Build tree from flat list of { level, title, link? }.
     * Level 1 = root title (no link), level 2 = top-level buttons, level 3 = children of last level-2, etc.
     *
     * @param array<array{level: int, title: string, link?: string}> $items
     * @return array{title: string, link?: string, children?: array}
     */
    private function buildTree(array $items): array
    {
        $root = ['title' => 'فهرست', 'children' => []];
        $stack = [&$root];
        $stackLevel = 0;

        foreach ($items as $item) {
            $level = $item['level'];
            $node = ['title' => $item['title']];
            if (!empty($item['link'])) {
                $node['link'] = $item['link'];
            }

            if ($level <= 0) {
                continue;
            }

            if ($level === 1) {
                $root['title'] = $item['title'];
                continue;
            }

            while (count($stack) > 1 && $level <= $stackLevel) {
                array_pop($stack);
                $stackLevel--;
            }

            $parent = &$stack[count($stack) - 1];
            if (!isset($parent['children'])) {
                $parent['children'] = [];
            }
            $parent['children'][] = $node;

            if ($level > $stackLevel) {
                $lastIndex = count($parent['children']) - 1;
                $parent['children'][$lastIndex]['children'] = [];
                $stack[] = &$parent['children'][$lastIndex];
                $stackLevel = $level;
            }
        }

        return $root;
    }

    /**
     * Validate that a URL is safe for use in inline keyboard (http/https only).
     */
    public function validateLink(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        return (bool) preg_match('/^https?:\/\/.+/i', $url);
    }
}
