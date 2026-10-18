<?php

namespace App\Jobs;

use App\Helpers\ProductLinkMessageHelper;
use App\Helpers\RssHelper;
use App\Helpers\WebPageMediaFindSave;
use App\Http\Controllers\NahjController;
use App\Http\Controllers\SharabeBeheshtiMp3Controller;
use App\Interfaces\RssChannelMessageSender;
use App\Models\RssChannel;
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

class RssPostItemTranslationToMessengerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** حداکثر زمان اجرای جاب (ثانیه)؛ فقط با worker واقعی معتبر است (در sync نادیده می‌رود) */
    public int $timeout = 45;

    /** مدت throttle برای لاگ خطاهای مکرر هر کانال (ثانیه) — جلوگیری از اسپم لاگ با توکن‌های مردده */
    protected const LOG_THROTTLE_SECONDS = 1800;

    protected RssPostItemTranslationQueue $rssPostItemTranslationQueue;

    protected RssChannelMessageSender $sender;

    /**
     * @param RssPostItemTranslationQueue $rssPostItemTranslationQueue
     * @param RssChannelMessageSender|null $sender تزریق دستی (تست)؛ در غیر این صورت از container
     */
    public function __construct(RssPostItemTranslationQueue $rssPostItemTranslationQueue, ?RssChannelMessageSender $sender = null)
    {
        $this->rssPostItemTranslationQueue = $rssPostItemTranslationQueue;
        $this->sender = $sender ?? app(RssChannelMessageSender::class);
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

        $mediaErrors = [];
        $baseDelivered = false;

        try {
            $parseMode = ProductLinkMessageHelper::parseModeForPlatform($mediumSlug);
            $response = $this->sender->sendBaseMessage($message, $rssChannel->token, $rssChannel->target_id, $mediumSlug, $parseMode);

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

            // پیام اصلی با موفقیت ثبت شد → سرنجه تحویل (message_id + sent_at) ذخیره می‌شود.
            // این سرنجه در ریکاوری (rss:recover) مبنای «ارسال‌شده / ارسال‌نشده / شک‌دار» است.
            $baseDelivered = true;
            $this->recordBaseSuccess($response);
            $this->recordHealth($rssChannel, $mediumSlug, 'ok', null);

            /** @var RssPostItem|null $rssPostItem */
            $rssPostItem = optional($postTranslation)->post;

            if ($rssPostItem) {
                $mediaErrors = $this->sendMedia($rssPostItem, $rssChannel, $rssChannelOrigin->slug, $mediumSlug);
            }

            // پیام اصلی رسیده است → «sent» می‌شود. اگر رسانه شکست خورده باشد،
            // خطای رسانه ثبت می‌شود (media_error) ولی رکورد «خطا» نمی‌شود؛
            // چون resend کل رکورد، متن تکراری در کانال می‌سازد.
            $this->markSent($mediaErrors);

        } catch (\Throwable $exception) {
            $reason = Str::limit($exception->getMessage(), 200);
            $this->throttledLog('RssPostItemTranslationToMessengerJob exception', [
                'queue_id' => $queue->id,
                'rss_channel_id' => $queue->rss_channel_id,
                'error' => $reason,
            ]);
            $this->recordHealth($rssChannel, $mediumSlug, 'fail', $reason);

            if ($baseDelivered) {
                // پیام اصلی قبلاً رسیده → رکورد را error نمی‌کنیم (retry = متن تکراری در کانال)
                $mediaErrors[] = 'exception_after_base: ' . $reason;
                $this->markSent($mediaErrors);
            } else {
                // پیام اصلی رسیده نیست (یا مطمئن نیستیم — خطا قبل از پاسخ API)
                // → «error» تا ریکاوری بتواند امناً resend کند (بدون سرنجه تحویل،
                // ریکاوری آن را «needs review» می‌داند مگر با --include-queue).
                $this->markFailed($reason);
            }
        }
    }

    /**
     * ذخیره سرنجه تحویل پیام اصلی: message_id (در صورت موجود بودن) + sent_at.
     */
    protected function recordBaseSuccess($response): void
    {
        try {
            $queue = $this->rssPostItemTranslationQueue;
            $queue->sent_at = now();

            $respArr = $this->normalizeResponse($response);
            if (is_array($respArr)) {
                $messageId = $respArr['message_id']
                    ?? $respArr['id']
                    ?? $respArr['result']['message_id']
                    ?? null;

                if ($messageId !== null) {
                    $queue->message_id = (string) $messageId;
                }
            }

            $queue->attempts = (int) $queue->attempts + 1;
            $queue->last_error = null;
            $queue->media_error = null;
            $queue->save();
        } catch (\Throwable $e) {
            Log::warning('RssPostItemTranslationToMessengerJob: could not record base success evidence', [
                'queue_id' => $this->rssPostItemTranslationQueue->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ارسال رسانه (عکس + صوت‌های اختصاصی) با بررسی واقعی پاسخ API.
     * قبل از این تغییر، پاسخ sendPhoto/sendAudio بررسی نمی‌شد (false success).
     *
     * @return string[] خطاهای رسانه (خالی = همه موفق یا رسانه‌ای نبود)
     */
    protected function sendMedia(RssPostItem $rssPostItem, RssChannel $rssChannel, string $originSlug, string $mediumSlug): array
    {
        $errors = [];
        $postImageUrl = $rssPostItem->image_url;

        /** @var \App\Models\RssItem|null $rssItem */
        $rssItem = optional($rssPostItem)->rssItem;
        $title = $rssPostItem->title;
        $rssItemTitle = $rssItem->title ?? '';
        $hashtags = '#' . implode(' #', explode(' ', $title));
        $rssItemHashtags = '#' . implode(' #', explode(' ', $rssItemTitle));
        $url = $rssPostItem->link;
        $captionMedia = $hashtags . ' ' . $rssItemHashtags . '
' . $url . '
' . ' - #' . $originSlug;

        $parseMode = ProductLinkMessageHelper::parseModeForPlatform($mediumSlug);
        $parseModeOrNull = ProductLinkMessageHelper::needsParseMode($mediumSlug) ? 'html' : null;

        if ($postImageUrl) {
            $photoResponse = $this->sender->sendPhoto(
                $rssChannel->token,
                $rssChannel->target_id,
                $postImageUrl,
                $captionMedia,
                ' - #' . $originSlug,
                $mediumSlug,
                $parseModeOrNull
            );

            if (!$this->isMessengerSuccess($photoResponse)) {
                $errors[] = 'photo: ' . $this->responseSummary($photoResponse);
            }

            if (strpos($postImageUrl, 'navaar.ir') !== false) {
                $postLink = $rssPostItem->link;
                preg_match('/https:\/\/www\.navaar\.ir\/audiobook\/(\d+)/', $postLink, $matches);

                if (isset($matches[1])) {
                    preg_match('/https:\/\/www\.navaar\.ir\/content\/books\/([0-9a-fA-F\-]{36})/', $postImageUrl, $matches2);

                    if (isset($matches2[1])) {
                        $audioUrl = "https://www.navaar.ir/content/books/{$matches2[1]}/sample.mp3";
                        $audioResponse = $this->sender->sendAudio(
                            $rssChannel->token,
                            $rssChannel->target_id,
                            $audioUrl,
                            $captionMedia,
                            ' - #' . $originSlug,
                            $mediumSlug,
                            $parseModeOrNull
                        );

                        if (!$this->isMessengerSuccess($audioResponse)) {
                            $errors[] = 'audio(navaar): ' . $this->responseSummary($audioResponse);
                        }
                    }
                }
            }
        }

        $postLink = $rssPostItem->link ?? '';
        if ($postLink) {
            if (str_contains($postLink, 'songsara.net')) {
                try {
                    $mp3Url = WebPageMediaFindSave::fetchAndSaveMp3Url($postLink);
                    $audioResponse = $this->sender->sendAudio(
                        $rssChannel->token,
                        $rssChannel->target_id,
                        (string) $mp3Url,
                        $captionMedia,
                        ' - #' . $originSlug,
                        $mediumSlug,
                        $parseModeOrNull
                    );

                    if (!$this->isMessengerSuccess($audioResponse)) {
                        $errors[] = 'audio(songsara): ' . $this->responseSummary($audioResponse);
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'audio(songsara): ' . Str::limit($e->getMessage(), 200);
                    Log::error('Error in sending songsara audio: ' . $e->getMessage());
                }
            }

            if (str_contains($postLink, 'balaghah.net')) {
                try {
                    $mp3Url = NahjController::getMp3UrlAndTitleAndId($postLink);
                    $audioResponse = $this->sender->sendAudio(
                        $rssChannel->token,
                        $rssChannel->target_id,
                        (string) $mp3Url,
                        $captionMedia,
                        ' - #' . $originSlug,
                        $mediumSlug,
                        $parseModeOrNull
                    );

                    if (!$this->isMessengerSuccess($audioResponse)) {
                        $errors[] = 'audio(balaghah): ' . $this->responseSummary($audioResponse);
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'audio(balaghah): ' . Str::limit($e->getMessage(), 200);
                    Log::error('Error in sending balaghah audio: ' . $e->getMessage());
                }
            }

            if (str_contains($postLink, 'sharabebeheshti.ir')) {
                try {
                    [$mp3Url, $shTitle, $shId] = SharabeBeheshtiMp3Controller::getMp3UrlAndTitleAndId($postLink);

                    $shareUrl = SharabeBeheshtiMp3Controller::buildSharabeBeheshtiShareUrlById((int) $shId, $mediumSlug);
                    $effectiveUrl = $shareUrl ?: $postLink;
                    $productBlock = ProductLinkMessageHelper::buildSharabeProductBlock($effectiveUrl, $mediumSlug);
                    $captionPrefix = SharabeBeheshtiMp3Controller::getCaptionByCheckEvenOrOdd($shId, $mediumSlug);

                    $captionSharabMedia = $hashtags . ' ' . $rssItemHashtags . '
' . $productBlock . '
' . ' - #' . $originSlug;

                    $caption = $captionPrefix . '
' . $captionSharabMedia;

                    Log::info('SharabeBeheshti message built', [
                        'id' => $shId,
                        'medium_slug' => $mediumSlug,
                        'share_url' => $shareUrl,
                        'post_link' => $postLink,
                    ]);

                    $audioResponse = $this->sender->sendAudio(
                        $rssChannel->token,
                        $rssChannel->target_id,
                        $mp3Url,
                        $caption,
                        $shTitle,
                        $mediumSlug,
                        $parseModeOrNull
                    );

                    Log::info('SharabeBeheshti audio send response', ['ok' => $this->isMessengerSuccess($audioResponse)]);

                    $platform = in_array($mediumSlug, ['bale', 'telegram', 'eitaa', 'gap'], true) ? $mediumSlug : 'messenger';
                    $ok = $this->isMessengerSuccess($audioResponse);
                    BotHealthRecorder::record([
                        'bot_id' => BotHealthRecorder::findBotIdByToken($rssChannel->token ?? null),
                        'feature_key' => 'sharabe_beheshti',
                        'platform' => $platform,
                        'event_type' => 'channel_post',
                        'status' => $ok ? 'ok' : 'fail',
                        'message' => $ok ? null : 'audio send response not ok',
                        'meta' => [
                            'rss_channel_id' => $rssChannel->id,
                            'mp3_id' => $shId ?? null,
                        ],
                    ]);

                    if (!$ok) {
                        $errors[] = 'audio(sharabe): ' . $this->responseSummary($audioResponse);
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'audio(sharabe): ' . Str::limit($e->getMessage(), 200);
                    Log::error('Error in sending sharabe audio: ' . $e->getMessage());

                    $platform = in_array($mediumSlug, ['bale', 'telegram', 'eitaa', 'gap'], true) ? $mediumSlug : 'messenger';
                    BotHealthRecorder::record([
                        'bot_id' => BotHealthRecorder::findBotIdByToken($rssChannel->token ?? null),
                        'feature_key' => 'sharabe_beheshti',
                        'platform' => $platform,
                        'event_type' => 'channel_post',
                        'status' => 'fail',
                        'message' => Str::limit($e->getMessage(), 200),
                        'meta' => [
                            'rss_channel_id' => $rssChannel->id ?? null,
                        ],
                    ]);
                }
            }
        }

        return $errors;
    }

    /**
     * آیا پاسخ API موفق است؟ (ok: true / پاسخ با message_id / آرایه خالی از error)
     */
    protected function isMessengerSuccess($response): bool
    {
        $respArr = $this->normalizeResponse($response);

        if ($respArr === null) {
            // پاسخ غیرمتعارف (رشته خالی و...) → شک‌دار، ولی اگر base رسیده باشد
            // در media_error ثبت می‌شود؛ اینجا false برمی‌گردانیم تا ردیابی شود.
            return false;
        }

        if (array_key_exists('ok', $respArr)) {
            return (bool) $respArr['ok'];
        }

        // بعضی APIها (Telegram) بدون ok فقط result/message_id برمی‌گردانند
        if (isset($respArr['message_id']) || isset($respArr['result'])) {
            return true;
        }

        return true;
    }

    /**
     * خلاصه کوتاه پاسخ API برای ثبت در media_error (بدون نشت توکن).
     */
    protected function responseSummary($response): string
    {
        $respArr = $this->normalizeResponse($response);

        if (!is_array($respArr)) {
            return 'unknown response: ' . Str::limit((string) $response, 120);
        }

        $code = $respArr['code'] ?? ($respArr['error_code'] ?? null);
        $description = $respArr['description'] ?? ($respArr['message'] ?? ($respArr['error']['message'] ?? 'unsuccessful response'));

        return 'code=' . ($code ?? '?') . ' ' . Str::limit((string) $description, 120);
    }

    /**
     * آیا پاسخ API نشان‌دهنده‌ی ارسال ناموفق است؟ (مثلا ok: false با 403 permission_denied)
     */
    protected function isSendFailure($response): bool
    {
        $respArr = $this->normalizeResponse($response);

        return is_array($respArr) && array_key_exists('ok', $respArr) && empty($respArr['ok']);
    }

    /**
     * دلیل کوتاه خطا برای ثبت در صف و لاگ
     */
    protected function sendFailureReason($response): string
    {
        return $this->responseSummary($response);
    }

    /**
     * پاسخ API را به آرایه ساده تبدیل می‌کند (آرایه یا آبجکت)
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

        if (is_string($response) && $response !== '') {
            $decoded = json_decode($response, true);

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
     * ثبت موفقیت: status='sent' + در صورت وجود خطای رسانه، media_error.
     * (message_id و sent_at قبل از این فراخوانی در recordBaseSuccess ثبت شده‌اند)
     *
     * @param string[] $mediaErrors
     */
    protected function markSent(array $mediaErrors = []): void
    {
        try {
            $queue = $this->rssPostItemTranslationQueue;
            $queue->status = 'sent';
            $queue->media_error = $mediaErrors
                ? Str::limit(implode(' | ', $mediaErrors), 500)
                : null;
            $queue->save();
        } catch (\Throwable $e) {
            Log::warning('RssPostItemTranslationToMessengerJob: could not mark queue row as sent', [
                'queue_id' => $this->rssPostItemTranslationQueue->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ثبت شکست: status='error' + last_error (redact شده) + attempts.
     *
     * @param string $reason
     */
    protected function markFailed(string $reason): void
    {
        try {
            $queue = $this->rssPostItemTranslationQueue;
            $queue->status = 'error';
            $queue->last_error = Str::limit($reason, 500);
            $queue->attempts = (int) $queue->attempts + 1;
            $queue->save();
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
