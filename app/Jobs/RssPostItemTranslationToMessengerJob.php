<?php

namespace App\Jobs;

use App\Builders\BotBuilder;
use App\Helpers\BotHelper;
use App\Helpers\RssHelper;
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
            $response = BotHelper::sendMessageEitaaSupport($message, $rssChannel->token, $rssChannel->target_id, $rssChannelOrigin->slug);
//            dd($response);
//            if ($response && $response["ok"] != true) {
            Log::error(json_encode($response));
//            }
            $post = $postTranslation->post ?? null;
            if ($post) {
                $postImageUrl = $post->image_url;
                if ($postImageUrl) {

                    $botBuilder = new BotBuilder(new Telegram($rssChannel->token, $rssChannelOrigin->slug));

                    $data = $botBuilder
                        ->setChatId($rssChannel->target_id)
                        ->setCaption('image')
                        ->setTitle('image')
                        ->setImageUrl($postImageUrl)
                        ->sendPhoto();
                }
            }
            $this->updateRssPostItemTranslationQueues();
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }

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
