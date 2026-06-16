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

    public static function parseModeForPlatform(?string $platformSlug): ?string
    {
        return self::needsParseMode($platformSlug) ? 'html' : null;
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
