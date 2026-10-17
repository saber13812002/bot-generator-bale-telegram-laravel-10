<?php

namespace App\Services;

use App\Helpers\TokenHelper;
use App\Models\ChannelPosterDestination;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * آپلود فایل رسانه‌ای (از دیسک) به پلتفرم‌های بله/تلگرام/ایتا
 *
 * file_idِ یک پلتفرم برای پلتفرم دیگری اعتبار ندارد؛ بنابراین فایل اصلی از
 * دیسک (که هنگام ثبت در صف دانلود شده) با multipart-formdata آپلود می‌شود.
 *
 * همه‌ی درخواسته‌ها از Laravel Http Client می‌گذرند تا:
 *   1) واقعی بودن آپلود multipart (شامل باینری فایل و caption) قابل تست
 *      خودکار باشد (Http::fake).
 *   2) لاگ‌ها و خطاها هرگز شامل توکن یا URL کامل نباشند (فقط operation_id،
 *      پلتفرم، نوع رسانه، حجم فایل، وضعیت و خطای امن).
 *
 * ایتا: مطابق سند رسمی ایتایار — endpoint `sendFile` با فیلدهای
 * `chat_id`، `file` و `caption` (نه `text`) و بررسی `ok === true`.
 * ناسازگاری سند: فهرست متدها `sendDocument` را نام برده ولی توضیحات تفصیلی
 * `sendFile` است؛ پارامتر کانال هم در متن `id_chat` و در نمونه‌ها `chat_id`
 * است. با شواهد موجود (نمونه‌های سند) از `sendFile` + `chat_id` استفاده می‌شود.
 */
class ChannelPosterMediaSender
{
    /**
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function send(
        string $token,
        string $platform,
        string $channelChatId,
        string $contentType,
        string $localPath,
        ?string $caption = null,
        ?string $fileName = null,
        ?string $mimeType = null
    ): array {
        $opId = substr(bin2hex(random_bytes(4)), 0, 8);
        $size = is_file($localPath) ? filesize($localPath) : 0;

        // نام/مایم اصلی فایل (اگر از سمت مبدأ رسیده) — برای حفظ هویت فایل در مقصد
        $attachName = $this->attachName($localPath, $fileName);
        $mime = (is_string($mimeType) && str_contains($mimeType, '/'))
            ? strtolower(trim($mimeType))
            : $this->mimeFor($contentType, $localPath);

        $this->log($opId, 'upload-start', [
            'platform'  => $platform,
            'media'     => $contentType,
            'bytes'     => $size,
            'mime'      => $mime,
            'caption'   => $caption !== null && $caption !== '',
        ]);

        if ($token === '' || $channelChatId === '') {
            return $this->fail($opId, 'missing token or chat id', $platform, $contentType, $size);
        }

        if (!is_file($localPath)) {
            return $this->fail($opId, 'media file not found on disk', $platform, $contentType, $size);
        }

        try {
            $response = $this->doUpload($token, $platform, $channelChatId, $contentType, $localPath, (string) $caption, $attachName, $mime);

            $result = $this->parseResponse($response, $platform, $contentType, $size);
            $this->log($opId, $result['success'] ? 'upload-ok' : 'upload-failed', [
                'platform' => $platform,
                'error'    => $result['error'],
            ]);

            return $result;
        } catch (\Throwable $e) {
            return $this->fail($opId, TokenHelper::redactTokens($e->getMessage()), $platform, $contentType, $size);
        }
    }

    /**
     * نام امن فایل برای آپلود: نام اصلی (اگر معتبر باشد) وگرنه نام روی دیسک.
     */
    private function attachName(string $localPath, ?string $fileName): string
    {
        if (is_string($fileName) && $fileName !== '') {
            $base = basename(str_replace('\\', '/', $fileName));
            if ($base !== '' && $base !== '.' && $base !== '..' && mb_strlen($base) <= 255) {
                return $base;
            }
        }

        return basename($localPath);
    }

    /**
     * اجرای واقعی درخواست multipart.
     */
    private function doUpload(
        string $token,
        string $platform,
        string $channelChatId,
        string $contentType,
        string $localPath,
        string $caption,
        string $attachName,
        string $mime
    ): Response {
        $contents = (string) file_get_contents($localPath);

        if ($platform === ChannelPosterDestination::PLATFORM_EITAA) {
            // سند رسمی ایتایار: POST /api/{TOKEN}/sendFile — multipart با
            // chat_id + file + caption (موفقیت فقط با ok === true).
            $base = 'https://eitaayar.ir/api/' . $token;

            return Http::asMultipart()
                ->withHeaders(['Accept' => 'application/json'])
                ->timeout(300)
                ->attach('file', $contents, $attachName, [
                    'Content-Type' => $mime,
                ])
                ->post($base . '/sendFile', [
                    'chat_id' => $channelChatId,
                    'caption' => $caption,
                ]);
        }

        if ($platform === ChannelPosterDestination::PLATFORM_BALE) {
            $field = $this->mediaField($contentType);
            $base = 'https://tapi.bale.ai/bot' . $token;

            return $this->sendViaMultipart($base, $field, $token, $channelChatId, $contentType, $contents, $caption, $attachName, $mime);
        }

        if ($platform === ChannelPosterDestination::PLATFORM_TELEGRAM) {
            $field = $this->mediaField($contentType);
            $base = 'https://api.telegram.org/bot' . $token;

            return $this->sendViaMultipart($base, $field, $token, $channelChatId, $contentType, $contents, $caption, $attachName, $mime);
        }

        throw new \InvalidArgumentException('unsupported platform: ' . $platform);
    }

    /**
     * آپلود multipart بله/تلگرام (sendPhoto/sendVideo/…).
     */
    private function sendViaMultipart(
        string $base,
        string $field,
        string $token,
        string $channelChatId,
        string $contentType,
        string $contents,
        string $caption,
        string $attachName,
        string $mime
    ): Response {
        $endpoint = match ($field) {
            'photo'    => 'sendPhoto',
            'video'    => 'sendVideo',
            'voice'    => 'sendVoice',
            'audio'    => 'sendAudio',
            default    => 'sendDocument',
        };

        $form = ['chat_id' => $channelChatId];
        if ($caption !== '') {
            $form['caption'] = $caption;
        }

        return Http::asMultipart()
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(300)
            ->attach($field, $contents, $attachName, [
                'Content-Type' => $mime,
            ])
            ->post($base . '/' . $endpoint, $form);
    }

    /**
     * تبدیل پاسخ API به نتیجه‌ی یکنواخت (موفقیت فقط با ok === true).
     *
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    private function parseResponse(Response $response, string $platform, string $contentType, int $size): array
    {
        if ($response->status() >= 400) {
            return [
                'success'    => false,
                'message_id' => null,
                'error'      => 'HTTP ' . $response->status(),
            ];
        }

        $data = $response->json();

        if (!is_array($data)) {
            return [
                'success'    => false,
                'message_id' => null,
                'error'      => 'invalid JSON response (HTTP ' . $response->status() . ')',
            ];
        }

        if (($data['ok'] ?? null) !== true) {
            $desc = $data['description'] ?? $data['error'] ?? $data['result'] ?? null;

            return [
                'success'    => false,
                'message_id' => null,
                'error'      => 'API ok=false' . (is_scalar($desc) && trim((string) $desc) !== '' ? ': ' . mb_substr((string) $desc, 0, 200) : ''),
            ];
        }

        $messageId = $data['result']['message_id'] ?? null;

        return [
            'success'    => true,
            'message_id' => $messageId !== null ? (string) $messageId : null,
            'error'      => null,
        ];
    }

    private function mediaField(string $contentType): string
    {
        return match ($contentType) {
            'photo'   => 'photo',
            'video'   => 'video',
            'voice'   => 'voice',
            'audio'   => 'audio',
            default   => 'document',
        };
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    private function fail(string $opId, string $error, string $platform, string $contentType, int $size): array
    {
        $this->log($opId, 'upload-failed', [
            'platform' => $platform,
            'error'    => $error,
        ]);

        return ['success' => false, 'message_id' => null, 'error' => $error];
    }

    /**
     * لاگ امن: فقط operation_id، پلتفرم، نوع رسانه، حجم و خطا — بدون توکن/URL.
     */
    private function log(string $opId, string $event, array $extra): void
    {
        try {
            Log::info('[ChannelPoster][media] ' . $event, ['op' => $opId] + $extra);
        } catch (\Throwable $e) {
            // هیچ خطای لاگ نباید انتشار را مختل کند
        }
    }

    private function mimeFor(string $contentType, string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            'mp4'  => 'video/mp4',
            'mov'  => 'video/quicktime',
            'webm' => 'video/webm',
            'ogg'  => 'audio/ogg',
            'mp3'  => 'audio/mpeg',
            'm4a'  => 'audio/mp4',
            'wav'  => 'audio/wav',
            'flac' => 'audio/flac',
            'pdf'  => 'application/pdf',
            'zip'  => 'application/zip',
        ];

        if (isset($map[$ext])) {
            return $map[$ext];
        }

        return match ($contentType) {
            'photo' => 'image/jpeg',
            'video' => 'video/mp4',
            'voice' => 'audio/ogg',
            'audio' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }
}
