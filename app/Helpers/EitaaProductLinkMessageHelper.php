<?php

namespace App\Helpers;

class EitaaProductLinkMessageHelper
{
    public static function buildSeoIntroText(): string
    {
        return "🔗 به صفحه محصول در سایت شراب بهشتی لینک می‌دهیم.\n"
            . "با ورود از این لینک می‌توانید به صفحه سر بزنید و با یک صلوات، ماندن ۵ دقیقه در صفحه و گوش دادن به فایل‌های صوتی، به بهبود رتبه سایت کمک کنید.";
    }

    public static function buildHtmlLink(string $url, string $label, bool $withIcon = false): string
    {
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        if ($withIcon) {
            return '<a title="' . $escapedLabel . '" href="' . $escapedUrl . '" target="_blank" rel="noopener">'
                . '<i class="xf xf-eitaa fs-3"></i> '
                . $escapedLabel
                . '</a>';
        }

        return '<a href="' . $escapedUrl . '">' . $escapedLabel . '</a>';
    }

    public static function buildFullMessage(string $shareUrl, ?string $title = null, bool $withIcon = false): string
    {
        $linkLabel = 'مشاهده صفحه محصول';
        $parts = [self::buildSeoIntroText(), self::buildHtmlLink($shareUrl, $linkLabel, $withIcon)];

        if ($title !== null && $title !== '') {
            $parts[] = $title;
        }

        return implode("\n\n", $parts);
    }

    public static function buildPlainUrlMessage(string $shareUrl, ?string $title = null): string
    {
        $parts = [self::buildSeoIntroText(), $shareUrl];

        if ($title !== null && $title !== '') {
            $parts[] = $title;
        }

        return implode("\n\n", $parts);
    }
}
