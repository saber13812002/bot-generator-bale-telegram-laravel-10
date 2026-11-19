<?php

namespace App\Services;

use App\Helpers\ProductLinkMessageHelper;
use App\Http\Controllers\SharabeBeheshtiMp3Controller;
use App\Models\SharabeBeheshtiMp3;

class SharabeBeheshtiRssMessageBuilder
{
    /**
     * همان payloadی که RssPostItemTranslationToMessengerJob برای sharabebeheshti می‌سازد.
     *
     * @return array{
     *     post_link: string,
     *     mp3_url: string,
     *     audio_title: string,
     *     share_url: ?string,
     *     caption: string,
     *     caption_prefix: string,
     *     caption_media: string,
     *     product_block: string,
     *     parse_mode: ?string,
     *     mp3_id: int
     * }|null
     */
    public function buildFromMp3Id(int $mp3Id, string $mediumSlug = 'eitaa', string $rssOriginSlug = 'eitaa'): ?array
    {
        $item = SharabeBeheshtiMp3::find($mp3Id);
        if (!$item) {
            return null;
        }

        $postLink = SharabeBeheshtiMp3Controller::buildSharabeBeheshtiShareUrlById($mp3Id, $mediumSlug);
        if (!$postLink) {
            return null;
        }

        return $this->buildFromPostLink($postLink, $mediumSlug, $rssOriginSlug, $item->title ?? 'شراب بهشتی');
    }

    public function buildFromPostLink(
        string $postLink,
        string $mediumSlug = 'eitaa',
        string $rssOriginSlug = 'eitaa',
        ?string $hashtagTitle = null
    ): ?array {
        $parsed = SharabeBeheshtiMp3Controller::getMp3UrlAndTitleAndId($postLink);
        if (!$parsed) {
            return null;
        }

        [$mp3Url, $audioTitle, $id] = $parsed;
        $title = $hashtagTitle ?? strtok($audioTitle, "\n") ?: 'شراب بهشتی';
        $hashtags = '#' . implode(' #', explode(' ', $title));
        $rssItemHashtags = '#شراب #بهشتی';

        $shareUrl = SharabeBeheshtiMp3Controller::buildSharabeBeheshtiShareUrlById((int) $id, $mediumSlug);
        $effectiveUrl = $shareUrl ?: $postLink;
        $productBlock = ProductLinkMessageHelper::buildSharabeProductBlock($effectiveUrl, $mediumSlug);
        $captionPrefix = SharabeBeheshtiMp3Controller::getCaptionByCheckEvenOrOdd($id, $mediumSlug);

        $captionMedia = $hashtags . ' ' . $rssItemHashtags . "\n"
            . $productBlock . "\n"
            . ' - #' . $rssOriginSlug;

        $caption = $captionPrefix . "\n" . $captionMedia;

        return [
            'post_link' => $postLink,
            'mp3_url' => $mp3Url,
            'audio_title' => $audioTitle,
            'share_url' => $shareUrl,
            'caption' => $caption,
            'caption_prefix' => $captionPrefix,
            'caption_media' => $captionMedia,
            'product_block' => $productBlock,
            'parse_mode' => ProductLinkMessageHelper::parseModeForPlatform($mediumSlug),
            'mp3_id' => (int) $id,
        ];
    }
}
