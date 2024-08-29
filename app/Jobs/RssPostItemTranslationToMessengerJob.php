<?php

namespace App\Jobs;

use App\Builders\BotBuilder;
use App\Helpers\BotHelper;
use App\Helpers\RssHelper;
use App\Helpers\WebPageMediaFindSave;
use App\Models\RssChannel;
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

        $rssChannelOrigin = $rssChannel->RssChannelOrigin;
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
            $post = $postTranslation->post ?? null;
            $botBuilder = new BotBuilder(new Telegram($rssChannel->token, $rssChannelOrigin->slug));
            if ($post) {
                $postImageUrl = $post->image_url;
//                $postImageUrl = "https://www.navaar.ir/content/books/8a9152dd-ef83-40d8-9ea4-b615824c93ad/pic.jpg?w=370&h=370&t=AAAAAHJDqBc=&mode=stretch";
                if ($postImageUrl) {

//                    dd($rssChannel->token, $rssChannelOrigin->slug);
                    $data = $botBuilder
                        ->setChatId($rssChannel->target_id)
                        ->setCaption('image')
                        ->setTitle('image')
                        ->setImageUrl($postImageUrl)
                        ->sendPhoto();

                    if (strpos($postImageUrl, 'navaar.ir') !== false) {
                        $postLink = $post->link;

// Extract the audiobook ID
                        preg_match('/https:\/\/www\.navaar\.ir\/audiobook\/(\d+)/', $postLink, $matches);

                        if (isset($matches[1])) {
                            $mediaId = $matches[1];

                            preg_match('/https:\/\/www\.navaar\.ir\/content\/books\/([0-9a-fA-F\-]{36})/', $postImageUrl, $matches2);

                            if (isset($matches2[1])) {
                                $audioBookId = $matches2[1];
                                $audioUrl = "https://www.navaar.ir/content/books/{$audioBookId}/sample.ogg";

                                $data = $botBuilder
                                    ->setChatId($rssChannel->target_id)
                                    ->setCaption('audio')
                                    ->setTitle('audio')
                                    ->setAudioUrl($audioUrl)
                                    ->sendAudio();
                            }
                        }
                    }
                }

                $postLink = $post->link;
                if ($postLink) {

                    if (str_contains($postLink, 'songsara.net')) {
                        try {
                            $mp3Url = WebPageMediaFindSave::fetchAndSaveMp3Url($postLink);

                            $data = $botBuilder
                                ->setChatId($rssChannel->target_id)
                                ->setCaption('audio')
                                ->setTitle('audio')
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
        Log::error($message, $context);
    }

    protected function updateRssPostItemTranslationQueues(): void
    {
        Log::info($this->rssPostItemTranslationQueue);

        $this->rssPostItemTranslationQueue->status = "sent";

        $this->rssPostItemTranslationQueue->save();
    }

}
