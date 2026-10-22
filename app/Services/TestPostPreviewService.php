<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\ProductLinkMessageHelper;
use App\Helpers\TokenHelper;
use App\Models\ChannelPosterDestination;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram;

/**
 * ارسال پست تستی / پیش‌نمایش (preview) — از طریق همان متدهایی که در production استفاده می‌شود
 *
 * پیکربندی از .env:
 *   TEST_PREVIEW_PLATFORM  = eitaa|bale|telegram  (پیش‌فرض: eitaa)
 *   TEST_PREVIEW_BOT_TOKEN = توکن ربات
 *   TEST_PREVIEW_CHAT_ID   = chat id کانال/چت تستی
 *   TEST_PREVIEW_MEDIA_DIR = مسیر فایل‌های رسانه‌ای نمونه (پیش‌فرض: storage/app/test-media)
 *
 * مسیرهای ارسال (production paths):
 *   - text  : eitaa → BotHelper::sendMessageEitaaSupport (همان مسیر TelegramChannelPosterPublisher::publish)
 *             telegram/bale → Telegram::sendMessage
 *   - media : ChannelPosterMediaSender::send
 *             (eitaa → sendFile multipart / telegram|bale → sendPhoto|sendVideo|sendAudio|sendVoice)
 *
 * @return array{success: bool, message_id: ?string, error: ?string}
 */
class TestPostPreviewService
{
    public const CONTENT_TYPES = ['text', 'photo', 'voice', 'audio', 'video'];

    /**
     * ارسال پیش‌نمایش/تست پست.
     *
     * @param string $type      text|photo|image|audio|music|voice|video
     * @param string|null $caption متن پست (برای text) یا کپشن رسانه
     * @param string|null $mediaPath مسیر محلی یا URL فایل رسانه (برای انواع رسانه‌ای)
     * @param string|null $platform override: eitaa|bale|telegram
     * @param string|null $token    override: توکن ربات
     * @param string|null $chatId   override: chat id مقصد
     * @param string|null $fileName نام فایل برای آپلود (اختیاری)
     * @param string|null $mimeType mime type (اختیاری)
     * @param string|null $parseMode HTML|Markdown فقط برای text
     */
    public function sendPreview(
        string $type,
        ?string $caption = null,
        ?string $mediaPath = null,
        ?string $platform = null,
        ?string $token = null,
        ?string $chatId = null,
        ?string $fileName = null,
        ?string $mimeType = null,
        ?string $parseMode = null
    ): array {
        $type = $this->normalizeType($type);
        $platform = $platform !== null && $platform !== '' ? $platform : (string) env('TEST_PREVIEW_PLATFORM', 'eitaa');
        $token = $token !== null && $token !== '' ? $token : (string) env('TEST_PREVIEW_BOT_TOKEN', '');
        $chatId = $chatId !== null && $chatId !== '' ? $chatId : (string) env('TEST_PREVIEW_CHAT_ID', '');

        if (!in_array($platform, [
            ChannelPosterDestination::PLATFORM_EITAA,
            ChannelPosterDestination::PLATFORM_BALE,
            ChannelPosterDestination::PLATFORM_TELEGRAM,
        ], true)) {
            return $this->fail('unsupported platform: ' . $platform . ' (use eitaa|bale|telegram)');
        }

        if ($token === '' || $chatId === '') {
            return $this->fail('missing TEST_PREVIEW_BOT_TOKEN / TEST_PREVIEW_CHAT_ID in .env (or pass --token/--chat-id)');
        }

        try {
            if ($type === 'text') {
                return $this->sendText($caption ?? $this->defaultText(), $platform, $token, $chatId, $parseMode);
            }

            $path = $this->resolveMediaFile($type, $mediaPath);
            if (!is_file($path)) {
                return $this->fail('media file not found: ' . $path);
            }

            // مسیر production برای آپلود/ارسال رسانه با کپشن
            return (new ChannelPosterMediaSender())->send(
                $token,
                $platform,
                $chatId,
                $this->mediaContentType($type),
                $path,
                $caption !== null && $caption !== '' ? $caption : null,
                $fileName,
                $mimeType
            );
        } catch (\Throwable $e) {
            return $this->fail(TokenHelper::redactTokens($e->getMessage()));
        }
    }

    /**
     * ارسال متن — دقیقا‌ً همان متدهای production:
     * - eitaa: BotHelper::sendMessageEitaaSupport → call_eitaa_api (/sendMessage)
     * - telegram/bale: کلاینت Telegram → sendMessage
     */
    private function sendText(string $text, string $platform, string $token, string $chatId, ?string $parseMode): array
    {
        if ($platform === ChannelPosterDestination::PLATFORM_EITAA) {
            $result = BotHelper::sendMessageEitaaSupport($text, $token, $chatId, 'eitaa', $parseMode);

            return $this->parseTelegramLike($result);
        }

        $bot = $platform === ChannelPosterDestination::PLATFORM_BALE
            ? new Telegram($token, 'bale')
            : new Telegram($token);

        $content = ['chat_id' => $chatId, 'text' => $text];
        if ($parseMode !== null && $parseMode !== '') {
            $content['parse_mode'] = $parseMode;
        }

        return $this->parseTelegramLike($bot->sendMessage($content));
    }

    /**
     * نرمال‌سازی پاسخ فرمت تلگرامی {ok, result.message_id} (تلگرام/بله/ایتا).
     */
    private function parseTelegramLike(mixed $result): array
    {
        $data = is_string($result)
            ? json_decode($result, true)
            : (is_object($result) ? json_decode(json_encode($result), true) : $result);

        if (!is_array($data)) {
            return [
                'success'    => false,
                'message_id' => null,
                'error'      => 'invalid response: ' . (is_string($result) ? mb_substr($result, 0, 300) : gettype($result)),
            ];
        }

        if (($data['ok'] ?? null) !== true) {
            $desc = $data['description'] ?? $data['error'] ?? ($data['result'] ?? 'unknown');

            return [
                'success'    => false,
                'message_id' => null,
                'error'      => 'API ok=false: ' . (is_scalar($desc) ? mb_substr((string) $desc, 0, 300) : json_encode($desc, JSON_UNESCAPED_UNICODE)),
            ];
        }

        $mid = $data['result']['message_id'] ?? $data['message_id'] ?? null;

        return [
            'success'    => true,
            'message_id' => $mid !== null ? (string) $mid : null,
            'error'      => null,
        ];
    }

    private function mediaContentType(string $type): string
    {
        return match ($type) {
            'photo' => 'photo',
            'video' => 'video',
            'voice' => 'voice',
            'audio' => 'audio',
            default => 'photo',
        };
    }

    private function normalizeType(string $type): string
    {
        $map = [
            'text' => 'text',
            'message' => 'text',
            'image' => 'photo',
            'img' => 'photo',
            'photo' => 'photo',
            'picture' => 'photo',
            'audio' => 'audio',
            'music' => 'audio',
            'song' => 'audio',
            'mp3' => 'audio',
            'voice' => 'voice',
            'video' => 'video',
            'movie' => 'video',
        ];

        return $map[strtolower(trim($type))] ?? 'text';
    }

    /**
     * مشخص‌کردن مسیر فایل رسانه:
     * - اگر --file URL باشد → دانلود در دایرکتوری رسانه‌ها
     * - اگر --file مسیر محلی باشد → همان
     * - در غیر این صورت جستجو در دایرکتوری رسانه‌ها (photo.jpg، video.mp4، audio.mp3، voice.wav، …)
     */
    private function resolveMediaFile(string $type, ?string $mediaPath): string
    {
        if (is_string($mediaPath) && $mediaPath !== '') {
            if (filter_var($mediaPath, FILTER_VALIDATE_URL)) {
                return $this->downloadToMediaDir($mediaPath);
            }

            return $mediaPath;
        }

        $dir = $this->mediaDir();
        $candidates = match ($type) {
            'photo' => ['photo.jpg', 'photo.jpeg', 'photo.png', 'photo.webp'],
            'video' => ['video.mp4', 'video.webm', 'video.mov'],
            'audio' => ['audio.mp3', 'audio.m4a', 'audio.ogg'],
            'voice' => ['voice.wav', 'voice.ogg', 'voice.mp3', 'voice.m4a'],
            default => [],
        };

        foreach ($candidates as $name) {
            $p = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_file($p)) {
                return $p;
            }
        }

        if (is_dir($dir)) {
            $files = glob($dir . DIRECTORY_SEPARATOR . '*');
            if ($files) {
                return $files[0];
            }
        }

        // مسیر ناموجود برمی‌گردد تا خطای تمیز تولید شود
        return $dir . DIRECTORY_SEPARATOR . $type;
    }

    private function mediaDir(): string
    {
        $dir = (string) env('TEST_PREVIEW_MEDIA_DIR', '');

        if ($dir !== '') {
            return str_starts_with($dir, '/') ? $dir : base_path($dir);
        }

        return storage_path('app/test-media');
    }

    /**
     * ساخت فایل‌های رسانه‌ای نمونه برای تست (PNG با GD + WAV با تن ساده).
     *
     * @return string[] فایل‌های ساخته‌شده
     */
    public function makeSampleMedia(): array
    {
        $dir = $this->mediaDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('cannot create media dir: ' . $dir);
        }

        $created = [];

        $photo = $dir . DIRECTORY_SEPARATOR . 'photo.png';
        if (!is_file($photo) && function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(400, 200);
            $bg = imagecolorallocate($img, 30, 64, 120);
            $fg = imagecolorallocate($img, 255, 255, 255);
            imagefill($img, 0, 0, $bg);
            imagestring($img, 5, 120, 92, 'TEST PREVIEW', $fg);
            imagepng($img, $photo);
            imagedestroy($img);
            $created[] = $photo;
        }

        $voice = $dir . DIRECTORY_SEPARATOR . 'voice.wav';
        if (!is_file($voice)) {
            file_put_contents($voice, $this->generateWavTone());
            $created[] = $voice;
        }

        return $created;
    }

    /**
     * تولید WAV ۲ ثانیه‌ای (PCM 8-bit, 8kHz) — بدون نیاز به ffmpeg/GD.
     */
    private function generateWavTone(int $seconds = 2, int $freq = 440): string
    {
        $rate = 8000;
        $samples = $seconds * $rate;
        $data = '';

        for ($i = 0; $i < $samples; $i++) {
            $v = (int) (12000 * sin(2 * M_PI * $freq * $i / $rate));
            $data .= pack('v', $v & 0xFFFF);
        }

        $header = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVEfmt ' . pack('V', 16)
            . pack('v', 1) . pack('v', 1) . pack('V', $rate) . pack('V', $rate)
            . pack('v', 1) . pack('v', 8) . 'data' . pack('V', strlen($data));

        return $header . $data;
    }

    /**
     * دانلود URL رسانه به دایرکتوری test-media.
     */
    private function downloadToMediaDir(string $url): string
    {
        $dir = $this->mediaDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('cannot create media dir: ' . $dir);
        }

        $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?: 'bin', PATHINFO_EXTENSION) ?: 'bin';
        $target = $dir . DIRECTORY_SEPARATOR . 'download_' . time() . '.' . $ext;

        $response = Http::timeout(120)->get($url);
        if (!$response->successful()) {
            throw new \RuntimeException('media download failed: HTTP ' . $response->status());
        }

        file_put_contents($target, $response->body());

        return $target;
    }

    private function defaultText(): string
    {
        return '✅ پیام تستی (preview) — ' . date('Y-m-d H:i:s');
    }

    /**
     * تست اختصاصی امضای لینک‌دار (Linked Signature) — ارسال هر دو روش:
     *   ۱) Inline Link : لینک داخل متن با نگارش مخصوص همان پیام‌رسان
     *   ۲) URL Button  : دکمه لینک‌دار زیر پیام (inline_keyboard) — فقط برای
     *      پلتفرم‌های پشتیبانی‌شده (telegram/eitaa/bale؛ soroush/rubika پشتیبانی ندارند)
     *
     * مسیرهای production:
     *   - eitaa    : BotHelper::sendMessageEitaaSupport (فقط متن + parse_mode)
     *   - telegram : Telegram SDK sendMessage (متن + reply_markup JSON)
     *   - bale     : Telegram SDK sendMessage با type=bale (متن + reply_markup JSON)
     *
     * در صورت شکست ارسال با parse_mode، بازتلاش امن بدون parse_mode انجام می‌شود.
     *
     * @return array{success: bool, message_id: ?string, error: ?string, sent: array{inline: bool, button: bool}, button_supported: bool}
     */
    public function sendSignatureLinkPreview(
        string $label,
        string $url,
        string $platform,
        ?string $token = null,
        ?string $chatId = null,
        string $base = '🔗 تست اختصاصی امضای لینک‌دار'
    ): array {
        $platform = $platform !== '' ? $platform : (string) env('TEST_PREVIEW_PLATFORM', 'eitaa');
        $token = $token !== null && $token !== '' ? $token : (string) env('TEST_PREVIEW_BOT_TOKEN', '');
        $chatId = $chatId !== null && $chatId !== '' ? $chatId : (string) env('TEST_PREVIEW_CHAT_ID', '');

        if (!in_array($platform, [
            ChannelPosterDestination::PLATFORM_EITAA,
            ChannelPosterDestination::PLATFORM_BALE,
            ChannelPosterDestination::PLATFORM_TELEGRAM,
        ], true)) {
            return $this->signatureFail('unsupported platform: ' . $platform . ' (use eitaa|bale|telegram)', false);
        }

        if ($token === '' || $chatId === '') {
            return $this->signatureFail('missing TEST_PREVIEW_BOT_TOKEN / TEST_PREVIEW_CHAT_ID in .env (or pass --token/--chat-id)', false);
        }

        // روش ۱: لینک داخل متن — نگارش مستقل هر پیام‌رسان
        $inline = ProductLinkMessageHelper::signatureLink($url, $platform);
        $parseMode = ProductLinkMessageHelper::parseModeForPlatform($platform);
        $text = $base . "\n\n" . $label . ":\n" . $inline;

        // روش ۲: دکمه URL زیر پیام (در صورت پشتیبانی)
        $buttonMarkup = ProductLinkMessageHelper::signatureButtonMarkup($label, $url, $platform);

        try {
            if ($platform === ChannelPosterDestination::PLATFORM_EITAA) {
                // ایتا: فقط مسیر متن (reply_markup در call_eitaa_api در دسترس نیست)
                $parsed = $this->parseTelegramLike(BotHelper::sendMessageEitaaSupport($text, $token, $chatId, 'eitaa', $parseMode));

                if (! $parsed['success'] && $parseMode !== null) {
                    $parsed = $this->parseTelegramLike(BotHelper::sendMessageEitaaSupport($text, $token, $chatId, 'eitaa', null));
                }

                $sent = ['inline' => $parsed['success'], 'button' => false];

                return [
                    'success'        => $parsed['success'],
                    'message_id'     => $parsed['message_id'],
                    'error'          => $parsed['success'] ? null : ($parsed['error'] ?? 'unknown error'),
                    'sent'           => $sent,
                    'button_supported' => false,
                ];
            }

            $bot = $platform === ChannelPosterDestination::PLATFORM_BALE
                ? new Telegram($token, 'bale')
                : new Telegram($token);

            $sent = ['inline' => false, 'button' => false];
            $parsed = $this->sendWithOptionalButton($bot, $chatId, $text, $parseMode, $buttonMarkup, $sent);

            // بازتلاش امن بدون parse_mode (متن ممکن است HTML نامعتبر داشته باشد)
            if (! $parsed['success'] && $parseMode !== null) {
                $parsed = $this->sendWithOptionalButton($bot, $chatId, $text, null, $buttonMarkup, $sent);
            }

            return [
                'success'        => $parsed['success'],
                'message_id'     => $parsed['message_id'],
                'error'          => $parsed['success'] ? null : ($parsed['error'] ?? 'unknown error'),
                'sent'           => $sent,
                'button_supported' => $buttonMarkup !== null,
            ];
        } catch (\Throwable $e) {
            return $this->signatureFail(TokenHelper::redactTokens($e->getMessage()), $buttonMarkup !== null);
        }
    }

    /**
     * ارسال متن (+ دکمه URL اختیاری) با کلاینت Telegram-style و ثبت وضعیت در $sent.
     */
    private function sendWithOptionalButton(
        Telegram $bot,
        string $chatId,
        string $text,
        ?string $parseMode,
        ?array $buttonMarkup,
        array &$sent
    ): array {
        $content = ['chat_id' => $chatId, 'text' => $text];

        if ($parseMode !== null && $parseMode !== '') {
            $content['parse_mode'] = $parseMode;
        }
        if ($buttonMarkup !== null) {
            $content['reply_markup'] = json_encode($buttonMarkup, JSON_UNESCAPED_UNICODE);
        }

        $parsed = $this->parseTelegramLike($bot->sendMessage($content));

        if ($parsed['success']) {
            $sent['inline'] = true;
            if ($buttonMarkup !== null) {
                $sent['button'] = true;
            }
        }

        return $parsed;
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string, sent: array{inline: bool, button: bool}, button_supported: bool}
     */
    private function signatureFail(string $error, bool $buttonSupported): array
    {
        Log::warning('[TestPreview] signature-link test failed', ['error' => TokenHelper::redactTokens($error)]);

        return [
            'success'        => false,
            'message_id'     => null,
            'error'          => $error,
            'sent'           => ['inline' => false, 'button' => false],
            'button_supported' => $buttonSupported,
        ];
    }

    /**
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    private function fail(string $error): array
    {
        Log::warning('[TestPreview] send failed', ['error' => TokenHelper::redactTokens($error)]);

        return ['success' => false, 'message_id' => null, 'error' => $error];
    }
}
