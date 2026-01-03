<?php

namespace App\Helpers;

class RssHelper
{

    /**
     * @param $rssPostItemTranslation
     * @param bool $withCommand
     * @return string
     */
    public static function createMessage($rssPostItemTranslation, bool $withCommand = false): string
    {

        $message = "
: #" . $rssPostItemTranslation->post->rssItem->title . "

: " . $rssPostItemTranslation->title . "

: " . self::trimIfNeeded($rssPostItemTranslation->content, 2500) . "

";

//        dd($rssPostItemTranslation->post);
        if ($rssPostItemTranslation->post && $rssPostItemTranslation->post->rssItem->locale != "fa") {
            $message .= ": " . $rssPostItemTranslation->post->title . "

: " . self::trimIfNeeded($rssPostItemTranslation->post->description, 2500);
        }
        $message .= "

📌
: " . self::stringifyTags($rssPostItemTranslation->post->rssItem->tags) . "
👇👇👇
: " . $rssPostItemTranslation->post->link

                . self::createCommands($rssPostItemTranslation->id, withCommand: $withCommand);



        return $message;
    }


    /**
     * @param $rssPostItemTranslationId
     * @param bool $withCommand
     * @return string
     */
    private static function createCommands($rssPostItemTranslationId, bool $withCommand = false): string
    {
        $cmd = "/publish:rocket:test:_id" . $rssPostItemTranslationId;

        return !$withCommand ? "" : "

: " . BotHelper::generateTextLink($cmd, $cmd, 'bale');
    }

    private static function stringifyTags($tags): string
    {
        $stringTags = "";
        foreach ($tags as $tag) {
            // Access the "fa" attribute of the tag
            $faValue = $tag->name;

            // Do something with the $faValue
            // For example, you can echo it or store it in an array
            $stringTags .= "#" . $faValue . " ";
        }
        return $stringTags;
    }

    private static function trimIfNeeded($content, $length = 1000)
    {
        if (strlen($content) > $length) {
            return substr($content, 0, $length) . '...';
        }
        return $content;
    }
}
