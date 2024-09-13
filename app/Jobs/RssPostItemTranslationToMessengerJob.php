<?php

namespace App\Jobs;

use App\Builders\BotBuilder;
use App\Helpers\BotHelper;
use App\Helpers\RssHelper;
use App\Helpers\WebPageMediaFindSave;
use App\Http\Controllers\NahjController;
use App\Http\Controllers\SharabeBeheshtiMp3Controller;
use App\Models\Nahj;
use App\Models\RssChannel;
use App\Models\RssItem;
use App\Models\RssPostItem;
use App\Models\RssPostItemTranslationQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram;

class RssPostItemTranslationToMessengerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected RssPostItemTranslationQueue $rssPostItemTranslationQueue;

    public function __construct(RssPostItemTranslationQueue $rssPostItemTranslationQueue)
    {
        $this->rssPostItemTranslationQueue = $rssPostItemTranslationQueue;
    }

    public function handle(): void
    {
//        dd(json_encode($this->rssPostItemTranslationQueue->postTranslation));
//        if ($this->rssPostItemTranslationQueue->rss_channel_id == 2) {
        $rssChannel = RssChannel::find($this->rssPostItemTranslationQueue->rss_channel_id);

        if (!$rssChannel) {
            $this->logError("RSS Channel not found for ID: {$this->rssPostItemTranslationQueue->rss_channel_id}");
            return;
        }

        $rssChannelOrigin = optional($rssChannel)->RssChannelOrigin;
        if (!$rssChannelOrigin || !$rssChannelOrigin->slug) {
            $this->logError("RSS Channel Origin or slug not found for ID: {$rssChannel->id}");
            return;
        }

        $postTranslation = $this->rssPostItemTranslationQueue->postTranslation;
        $message = RssHelper::createMessage($postTranslation, true);
//        dd($message, $rssChannel->token, $rssChannel->target_id, $rssChannelOrigin->slug);

        try {
//            dd($message, $rssChannel->token, $rssChannel->target_id, $rssChannelOrigin->slug);
            $response = BotHelper::sendMessageEitaaSupport($message, $rssChannel->token, $rssChannel->target_id, $rssChannelOrigin->slug);
//            dd($response);
//            if ($response && $response["ok"] != true) {
            Log::info(json_encode($response));
//            }
            /** @var RssPostItem|null $rssPostItem */
            $rssPostItem = optional($postTranslation)->post;
            $botBuilder = new BotBuilder(new Telegram($rssChannel->token, $rssChannelOrigin->slug));
            if ($rssPostItem) {
                /** @var RssItem|null $rssItem */
                $rssItem = optional($rssPostItem)->rssItem;
                $postImageUrl = $rssPostItem->image_url;

                $title = $rssPostItem->title; // Assuming this is where your title comes from
                $rssItemTitle = $rssItem->title; // Assuming this is where your title comes from
                $hashtags = '#' . implode(' #', explode(' ', $title));
                $rssItemHashtags = '#' . implode(' #', explode(' ', $rssItemTitle));
                $url = $rssPostItem->link; // Assuming this is where your title comes from
                $captionMedia = $hashtags . ' ' . $rssItemHashtags . '
' . $url . '
' . ' - #' . $rssChannelOrigin->slug;


//                $postImageUrl = "https://www.navaar.ir/content/books/8a9152dd-ef83-40d8-9ea4-b615824c93ad/pic.jpg?w=370&h=370&t=AAAAAHJDqBc=&mode=stretch";
                if ($postImageUrl) {

//                    dd($rssChannel->token, $rssChannelOrigin->slug);
                    $data = $botBuilder
                        ->setChatId($rssChannel->target_id)
                        ->setCaption($captionMedia) // Dynamic caption
                        ->setTitle(' - #' . $rssChannelOrigin->slug) // Dynamic title
                        ->setImageUrl($postImageUrl)
                        ->sendPhoto();

                    if (strpos($postImageUrl, 'navaar.ir') !== false) {
                        $postLink = $rssPostItem->link;

                        preg_match('/https:\/\/www\.navaar\.ir\/audiobook\/(\d+)/', $postLink, $matches);

                        if (isset($matches[1])) {
                            $mediaId = $matches[1];

                            preg_match('/https:\/\/www\.navaar\.ir\/content\/books\/([0-9a-fA-F\-]{36})/', $postImageUrl, $matches2);

                            if (isset($matches2[1])) {
                                $audioBookId = $matches2[1];
                                $audioUrl = "https://www.navaar.ir/content/books/{$audioBookId}/sample.ogg";

                                $data = $botBuilder
                                    ->setChatId($rssChannel->target_id)
                                    ->setCaption($captionMedia) // Dynamic caption
                                    ->setTitle(' - #' . $rssChannelOrigin->slug) // Dynamic title
                                    ->setAudioUrl($audioUrl)
                                    ->sendAudio();
                            }
                        }
                    }
                }

                $postLink = $rssPostItem->link;
                if ($postLink) {

                    if (str_contains($postLink, 'songsara.net')) {
                        try {
                            $mp3Url = WebPageMediaFindSave::fetchAndSaveMp3Url($postLink);

                            $data = $botBuilder
                                ->setChatId($rssChannel->target_id)
                                ->setCaption($captionMedia) // Dynamic caption
                                ->setTitle(' - #' . $rssChannelOrigin->slug) // Dynamic title
                                ->setAudioUrl($mp3Url)
                                ->sendAudio();

                        } catch (\Exception $e) {
                            \Log::error('Error in sending audio: ' . $e->getMessage());
                        }
                    }
                    if (str_contains($postLink, 'balaghah.net')) {
                        try {
                            $mp3Url = NahjController::getMp3UrlAndTitleAndId($postLink);

                            $data = $botBuilder
                                ->setChatId($rssChannel->target_id)
                                ->setCaption($captionMedia) // Dynamic caption
                                ->setTitle(' - #' . $rssChannelOrigin->slug) // Dynamic title
                                ->setAudioUrl($mp3Url)
                                ->sendAudio();

                        } catch (\Exception $e) {
                            \Log::error('Error in sending audio: ' . $e->getMessage());
                        }
                    }
                    Log::info("postlink:" . $postLink);
                    if (str_contains($postLink, 'sharabebeheshti.ir')) {
                        try {
                            [$mp3Url, $title, $id] = SharabeBeheshtiMp3Controller::getMp3UrlAndTitleAndId($postLink);

                            $caption = SharabeBeheshtiMp3Controller::getCaptionByCheckEvenOrOdd($id) . '
' . $captionMedia;

                            Log::info("mp3Url:" . $mp3Url);
                            $data = $botBuilder
                                ->setChatId($rssChannel->target_id)
                                ->setCaption($caption)
                                ->setTitle($title)
                                ->setAudioUrl($mp3Url)
                                ->sendAudio();

                        } catch (\Exception $e) {
                            \Log::error('Error in sending audio: ' . $e->getMessage());
                        }
                    }
                }

            }
            $this->updateRssPostItemTranslationQueues();
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
//        }
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::error('Error message', ['context' => $context]);
    }

    protected function updateRssPostItemTranslationQueues(): void
    {
        Log::info($this->rssPostItemTranslationQueue);

        $this->rssPostItemTranslationQueue->status = "sent";

        $this->rssPostItemTranslationQueue->save();
    }

}
