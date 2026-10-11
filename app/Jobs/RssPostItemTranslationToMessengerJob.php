<?php

namespace App\Jobs;

use App\Builders\BotBuilder;
use App\Helpers\BotHelper;
use App\Helpers\ProductLinkMessageHelper;
use App\Helpers\RssHelper;
use App\Helpers\WebPageMediaFindSave;
use App\Http\Controllers\NahjController;
use App\Http\Controllers\SharabeBeheshtiMp3Controller;
use App\Models\Nahj;
use App\Models\RssChannel;
use App\Models\RssItem;
use App\Models\RssPostItem;
use App\Models\RssPostItemTranslationQueue;
use App\Services\BotHealthRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Telegram;

//mohem startpoint
class RssPostItemTranslationToMessengerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** حداکثر زمان اجرای جاب (ثانیه)؛ جلوگیری از انتظار طولانی برای tapi.bale.ai و غیره */
    public int $timeout = 45;

    /** مدت throttle برای لاگ خطاهای مکرر هر کانال (ثانیه) — جلوگیری از اسپم لاگ با توکن‌های مردده */
    protected const LOG_THROTTLE_SECONDS = 1800;

    protected RssPostItemTranslationQueue $rssPostItemTranslationQueue;

    public function __construct(RssPostItemTranslationQueue $rssPostItemTranslationQueue)
    {
        $this->rssPostItemTranslationQueue = $rssPostItemTranslationQueue;
    }

    public function handle(): void
    {
        $queue = $this->rssPostItemTranslationQueue;
        $rssChannel = RssChannel::find($queue->rss_channel_id);

        if (!$rssChannel) {
            $this->logError("RSS Channel not found for ID: {$queue->rss_channel_id}");
            $this->markFailed('rss_channel_not_found');
            return;
        }

        $rssChannelOrigin = optional($rssChannel)->RssChannelOrigin;
        if (!$rssChannelOrigin || !$rssChannelOrigin->slug) {
            $this->logError("RSS Channel Origin or slug not found for ID: {$rssChannel->id}");
            $this->markFailed('rss_channel_origin_not_found');
            return;
        }

        $postTranslation = $queue->postTranslation;
        $mediumSlug = $rssChannelOrigin->slug ?? 'messenger';
        $message = RssHelper::createMessage($postTranslation, true, $mediumSlug);

        try {
            $parseMode = ProductLinkMessageHelper::parseModeForPlatform($mediumSlug);
            $response = BotHelper::sendMessageEitaaSupport($message, $rssChannel->token, $rssChannel->target_id, $mediumSlug, $parseMode);

            // بررسی نتیجه ارسال پیام اصلی: اگر API «ok: false» برگرداند (مثلا 403 permission_denied
            // با توکن مردده Bale)، رکورد صف را error می‌کنیم و از ادامه‌ی فراخوانی‌های بیهوده
            // (عکس/صوت) با همان توکن مردده خودداری می‌کنیم تا اسپم لاگ و API شکل نگیرد.
            if ($this->isSendFailure($response)) {
                $reason = $this->sendFailureReason($response);
                $this->throttledLog('RssPostItemTranslationToMessengerJob: send message failed (message not delivered)', [
                    'queue_id' => $queue->id,
                    'rss_channel_id' => $queue->rss_channel_id,
                    'reason' => $reason,
                ]);
                $this->recordHealth($rssChannel, $mediumSlug, 'fail', 'base message send failed: ' . $reason);
                $this->markFailed($reason);
                return;
            }

            $this->recordHealth($rssChannel, $mediumSlug, 'ok', null);

            /** @var RssPostItem|null $rssPostItem */
            $rssPostItem = optional($postTranslation)->post;
            $botBuilder = new BotBuilder(new Telegram($rssChannel->token, $rssChannelOrigin->slug));
            if ($rssPostItem) {
                /** @var RssItem|null $rssItem */
                $rssItem = optional($rssPostItem)->rssItem;
                $postImageUrl = $rssPostItem->image_url;

                $title = $rssPostItem->title; // Assuming this is where the title comes from
                $rssItemTitle = $rssItem->title; // Assuming this is where the title comes from
                $hashtags = '#' . implode(' #', explode(' ', $title));
                $rssItemHashtags = '#' . implode(' #', explode(' ', $rssItemTitle));
                $url = $rssPostItem->link; // Assuming this is where the title comes from
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
                                $audioUrl = "https://www.navaar.ir/content/books/{$audioBookId}/sample.mp3";

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

                            $shareUrl = SharabeBeheshtiMp3Controller::buildSharabeBeheshtiShareUrlById((int)$id, $mediumSlug);
                            $effectiveUrl = $shareUrl ?: $postLink;
                            $productBlock = ProductLinkMessageHelper::buildSharabeProductBlock($effectiveUrl, $mediumSlug);
                            $captionPrefix = SharabeBeheshtiMp3Controller::getCaptionByCheckEvenOrOdd($id, $mediumSlug);

                            $captionSharabMedia = $hashtags . ' ' . $rssItemHashtags . '
' . $productBlock . '
' . ' - #' . $rssChannelOrigin->slug;

                            $caption = $captionPrefix . '
' . $captionSharabMedia;

                            Log::info('SharabeBeheshti message built', [
                                'id' => $id,
                                'medium_slug' => $mediumSlug,
                                'share_url' => $shareUrl,
                                'post_link' => $postLink,
                                'caption_prefix' => $captionPrefix,
                                'caption_media' => $captionSharabMedia,
                            ]);

                            Log::info("mp3Url:" . $mp3Url);
                            if (ProductLinkMessageHelper::needsParseMode($mediumSlug)) {
                                $botBuilder->setParseMode('html');
                            }
                            $data = $botBuilder
                                ->setChatId($rssChannel->target_id)
                                ->setCaption($caption)
                                ->setTitle($title)
                                ->setAudioUrl($mp3Url)
                                ->sendAudio();

                            Log::info('SharabeBeheshti audio send response', ['data' => $data]);

                            $platform = in_array($mediumSlug, ['bale', 'telegram', 'eitaa', 'gap'], true)
                                ? $mediumSlug
                                : 'messenger';
                            $ok = BotHealthRecorder::isMessengerOk($data);
                            BotHealthRecorder::record([
                                'bot_id' => BotHealthRecorder::findBotIdByToken($rssChannel->token ?? null),
                                'feature_key' => 'sharabe_beheshti',
                                'platform' => $platform,
                                'event_type' => 'channel_post',
                                'status' => $ok ? 'ok' : 'fail',
                                'message' => $ok ? null : 'audio send response not ok',
                                'meta' => [
                                    'rss_channel_id' => $rssChannel->id,
                                    'mp3_id' => $id ?? null,
                                ],
                            ]);

                        } catch (\Exception $e) {
                            \Log::error('Error in sending audio: ' . $e->getMessage());
                            $platform = in_array($mediumSlug, ['bale', 'telegram', 'eitaa', 'gap'], true)
                                ? $mediumSlug
                                : 'messenger';
                            BotHealthRecorder::record([
                                'bot_id' => BotHealthRecorder::findBotIdByToken($rssChannel->token ?? null),
                                'feature_key' => 'sharabe_beheshti',
                                'platform' => $platform,
                                'event_type' => 'channel_post',
                                'status' => 'fail',
                                'message' => $e->getMessage(),
                                'meta' => [
                                    'rss_channel_id' => $rssChannel->id ?? null,
                                ],
                            ]);
                        }
                    }
                }

            }
            $this->markSent();
        } catch (\Throwable $exception) {
            $this->throttledLog('RssPostItemTranslationToMessengerJob exception', [
                'queue_id' => $queue->id,
                'rss_channel_id' => $queue->rss_channel_id,
                'error' => $exception->getMessage(),
            ]);
            $this->recordHealth($rssChannel, $mediumSlug, 'fail', $exception->getMessage());
            $this->markFailed(Str::limit($exception->getMessage(), 200));
        }
    }

    /**
     * آیا پاسخ API نشان‌دهنده‌ی ارسال ناموفق است؟ (مثلا ok: false با 403 permission_denied)
     *
     * @param mixed $response
     */
    protected function isSendFailure($response): bool
    {
        $respArr = $this->normalizeResponse($response);

        return is_array($respArr) && array_key_exists('ok', $respArr) && empty($respArr['ok']);
    }

    /**
     * دلیل کوتاه خطا برای ثبت در صف و لاگ
     *
     * @param mixed $response
     */
    protected function sendFailureReason($response): string
    {
        $respArr = $this->normalizeResponse($response);

        if (!is_array($respArr)) {
            return 'unknown response';
        }

        $code = $respArr['code'] ?? null;
        $description = $respArr['description'] ?? ($respArr['message'] ?? 'unknown');

        return 'code=' . ($code ?? '?') . ' ' . Str::limit((string) $description, 120);
    }

    /**
     * پاسخ API را به آرایه ساده تبدیل می‌کند (آرایه یا آبجکت)
     *
     * @param mixed $response
     */
    protected function normalizeResponse($response): ?array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response)) {
            $decoded = json_decode((string) json_encode($response), true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * لاگ با throttle: خطاهای مکرر هر کانال (مثلا توکن مردده که هر ۱۵ دقیقه تکرار می‌شود)
     * در هر ۳۰ دقیقه فقط یک‌بار در لاگ ثبت می‌شوند.
     */
    protected function throttledLog(string $message, array $context): void
    {
        $key = 'rss_msg_job_log_throttle_' . ($context['rss_channel_id'] ?? 'unknown');

        if (Cache::has($key)) {
            return;
        }

        Cache::put($key, 1, self::LOG_THROTTLE_SECONDS);
        Log::warning($message, $context);
    }

    /**
     * ثبت رکورد صف: 'sent' برای موفقیت، 'error' برای شکست (ارزش‌های موجود در enum دیتابیس)
     */
    protected function markSent(): void
    {
        $this->rssPostItemTranslationQueue->status = 'sent';
        $this->rssPostItemTranslationQueue->save();
    }

    /**
     * @param string $reason
     */
    protected function markFailed(string $reason): void
    {
        try {
            $this->rssPostItemTranslationQueue->status = 'error';
            $this->rssPostItemTranslationQueue->save();
        } catch (\Throwable $e) {
            Log::warning('RssPostItemTranslationToMessengerJob: could not mark queue row as error', [
                'queue_id' => $this->rssPostItemTranslationQueue->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ثبت رویداد سلامت کانال در BotHealthEvent (مشاهده‌پذیری برای watchdog)
     */
    protected function recordHealth(RssChannel $rssChannel, string $mediumSlug, string $status, ?string $message): void
    {
        try {
            $platform = in_array($mediumSlug, ['bale', 'telegram', 'eitaa', 'gap'], true)
                ? $mediumSlug
                : 'messenger';

            BotHealthRecorder::record([
                'bot_id' => BotHealthRecorder::findBotIdByToken($rssChannel->token ?? null),
                'feature_key' => 'rss_channel_post',
                'platform' => $platform,
                'event_type' => 'channel_post',
                'status' => $status,
                'message' => $message ? Str::limit($message, 200) : null,
                'meta' => [
                    'rss_channel_id' => $rssChannel->id,
                ],
            ]);
        } catch (\Throwable $e) {
            // ثبت سلامت نباید اجرای جاب را خراب کند
        }
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

}
