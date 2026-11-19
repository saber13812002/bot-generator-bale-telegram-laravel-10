<?php

namespace Tests\Feature;

use App\Helpers\BotHelper;
use App\Interfaces\Services\ChannelPosterPublisher;
use App\Services\ChannelPoster\SignatureRenderer;
use App\Services\TelegramChannelPosterPublisher;
use App\Services\TelegramChannelPosterPublisherFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;
use Telegram;

/**
 * T6 — Hardening مستقل Eitaa (addendum B) — acceptance scenario 9.
 *
 * قرارداد: هیچ‌وقت HTML خام به API ایتایار نرود (متن verbatim رندر می‌شود؛
 * مستندات رسمی docs/features/OFFICIAL/EitaaYar_API_Documentation.pdf).
 *
 *  - رندر: خروجی render/renderMulti برای ایتا در همهٔ typeها بدون «<» + parse_mode null
 *  - مسیر متنی: publish ایتا حتی اگر بالادستی parse_mode بفرستد،
 *    BotHelper::sendMessageEitaaSupport را با parse_mode = null صدا می‌زند
 *    (hardening در خودِ publish — اینجا تست می‌شود)
 *  - مسیر رسانه: photo/video با امضای multi-line plain → sendFile با
 *    caption دقیقا‌ همان plain (بدون «<») و بدون فیلد parse_mode
 *  - URL ایتا = URL سادهٔ خودکار-لینک‌دارشونده (رفتار فعلی linkLine)
 *
 * نکته: Mockery alias mock روی کلاسی که در همان process load شده است نمی‌نشیند
 * (و restore هم ندارد)؛ BotHelper یک static helper است. بنابراین رفتار
 * publisher روی seam تست‌پذیرِ publish() — publishEitaaText() — با subclass
 * (anonymous class) تست می‌شود: همان روشی که در production صدا زده می‌شود.
 * (PHP 8.5: nested class declaration حذف شده — از anonymous class استفاده می‌شود.)
 */
class ChannelPosterEitaaHardeningTest extends TestCase
{
    private const EITAA_URL = 'https://sharabebeheshti.ir/shb1?random_id=abc123&id=63';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFiles = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir(storage_path('app/channel-poster-media-test'));

        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test double کلاینت vendor: بدون تماس شبکه در ساخت (getData() که در
     * constructor vendor صدا زده می‌شود، را نمی‌خواند).
     */
    private function makeCaptureBot(): Telegram
    {
        return new class extends Telegram {
            public function __construct()
            {
                // عمداً parent::__construct() صدا نمی‌شود (getData() = تماس شبکه).
            }
        };
    }

    /**
     * Publisher واقعی با capture روی seam متنی ایتا (publish → publishEitaaText).
     *
     * @return TelegramChannelPosterPublisher&object{captured: array}
     */
    private function makeCapturingEitaaPublisher(Telegram $bot)
    {
        return new class($bot, 'bale', null) extends TelegramChannelPosterPublisher {
            public array $captured = [];

            public function publishEitaaText(string $text, string $botToken, string $channelChatId, ?string $parseMode): mixed
            {
                $this->captured[] = [
                    'text' => $text,
                    'token' => $botToken,
                    'chat' => $channelChatId,
                    'parse_mode' => $parseMode,
                ];

                return ['ok' => true, 'result' => ['message_id' => 555]];
            }
        };
    }

    private function makeTempFile(string $name, string $contents): string
    {
        $dir = storage_path('app/channel-poster-media-test');
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            $this->fail('could not create temp media directory');
        }

        $path = $dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function realPublisher(): ChannelPosterPublisher
    {
        return app(TelegramChannelPosterPublisherFactory::class)->make('123:origin-token', 'bale');
    }

    private function multipartData(Request $request): array
    {
        return collect($request->data())
            ->mapWithKeys(fn (array $entry) => [$entry['name'] => $entry['contents']])
            ->all();
    }

    // ─────────────────────────── render: never HTML for eitaa ───────────────────────────

    public function test_render_eitaa_has_no_html_and_no_parse_mode_for_every_signature_type(): void
    {
        $cases = [
            // [type, label]
            ['inline', 'مشاهده صفحه'],
            ['inline', null],
            ['composite', 'برچسب مرکب'],
            ['pure_url', 'دکمهٔ محصول'],
            ['plain', 'متن خالص'],
            ['raw', null],
        ];

        foreach ($cases as [$type, $label]) {
            $out = SignatureRenderer::render('eitaa', self::EITAA_URL, $type, $label);

            $this->assertStringNotContainsString('<', $out['text'], "eitaa $type: no HTML");
            $this->assertStringNotContainsString('>', $out['text'], "eitaa $type: no HTML");
            $this->assertNull($out['parse_mode'], "eitaa $type: parse_mode must be null");
            $this->assertNull($out['button'], "eitaa $type: no button markup");
        }

        // قرارداد pure_url: متن دلخواه کاربر verbatim (بدون escape/بازنویسی) +
        // url خالص در خط دوم — خروجی رندر، نه برچسب کاربر، منبع HTML نیست.
        $out = SignatureRenderer::render('eitaa', self::EITAA_URL, 'pure_url', 'دکمهٔ محصول <تست>&');
        $this->assertSame("\n\nدکمهٔ محصول <تست>&\n" . self::EITAA_URL, $out['text']);
        $this->assertNull($out['parse_mode']);
    }

    public function test_render_multi_eitaa_has_no_html_and_plain_auto_linkable_urls(): void
    {
        $links = [
            ['url' => self::EITAA_URL, 'label' => 'کانال خودمان'],
            ['url' => 'https://eitaa.com/sharabebeheshti', 'label' => null],
            ['url' => 'https://t.me/some_channel', 'label' => 'کانال تلگرام'],
        ];

        foreach (['single_line', 'multi_line'] as $layout) {
            $out = SignatureRenderer::renderMulti('eitaa', $links, $layout);

            $this->assertStringNotContainsString('<', $out['text'], "multi $layout: no HTML");
            $this->assertStringNotContainsString('>', $out['text'], "multi $layout: no HTML");
            $this->assertNull($out['parse_mode'], "multi $layout: parse_mode must be null");

            // URL سادهٔ خودکار-لینک‌دارشونده (رفتار فعلی linkLine ایتا):
            foreach ($links as $l) {
                $this->assertStringContainsString($l['url'], $out['text'], "multi $layout: bare url");
            }

            // قرارداد دوخطه (addendum D) در چیدمان multi_line:
            // خط برچسب + لینک در خط بعد — برای هر ورودی دقیقاً یک‌بار.
            if ($layout === 'multi_line') {
                $this->assertSame(
                    "\n\nکانال خودمان:\n" . self::EITAA_URL
                    . "\nhttps://eitaa.com/sharabebeheshti"
                    . "\nکانال تلگرام:\nhttps://t.me/some_channel",
                    $out['text']
                );
            }
        }
    }

    public function test_render_eitaa_plain_url_is_bare_auto_linkable(): void
    {
        // رفتار فعلی linkLine: ایتا = url خالص (کلاینت URL را خودکار لینک‌دار می‌کند).
        $out = SignatureRenderer::render('eitaa', self::EITAA_URL, 'raw');
        $this->assertSame("\n\n" . self::EITAA_URL, $out['text']);
        $this->assertNull($out['parse_mode']);

        $inline = SignatureRenderer::render('eitaa', self::EITAA_URL, 'inline', 'برچسب');
        $this->assertSame("\n\nبرچسب:\n" . self::EITAA_URL, $inline['text']);
    }

    // ─────────────────────────── text path: publisher forces parse_mode null ───────────────────────────

    public function test_publish_eitaa_forces_parse_mode_null_even_when_caller_passes_html(): void
    {
        $publisher = $this->makeCapturingEitaaPublisher($this->makeCaptureBot());

        $result = $publisher->publish(
            '-100111222',
            'text',
            "متن پست\n\nکانال خودمان:\n" . self::EITAA_URL,
            null,
            'eitaa',
            'eitaa-dest-token',
            null,
            null,
            null,
            'html' // بالادستی html فرستاده — publisher باید آن را برای ایتا خنثی کند
        );

        $this->assertTrue($result['success']);
        $this->assertSame('555', $result['message_id']);
        $this->assertCount(1, $publisher->captured);

        $call = $publisher->captured[0];
        $this->assertNull($call['parse_mode'], 'publishEitaaText must be called with parse_mode = null (T6 hardening)');
        $this->assertSame('eitaa-dest-token', $call['token']);
        $this->assertSame('-100111222', $call['chat']);
        $this->assertStringNotContainsString('<', $call['text']);
    }

    public function test_publish_eitaa_sends_plain_multi_signature_text_via_bot_helper(): void
    {
        // مسیر واقعی: publish → publishEitaaText → BotHelper::sendMessageEitaaSupport →
        // buildEitaaApiFields (خالص). متن امضای multi-line plain باید verbatim برود
        // و در فیلدهای API هیچ parse_mode نباشد.
        $signature = SignatureRenderer::renderMulti('eitaa', [
            ['url' => self::EITAA_URL, 'label' => 'کانال خودمان'],
            ['url' => 'https://eitaa.com/sharabebeheshti', 'label' => null],
        ], 'multi_line');

        $postText = 'متن پست' . $signature['text'];

        $publisher = $this->makeCapturingEitaaPublisher($this->makeCaptureBot());
        $result = $publisher->publish(
            '@mychannel',
            'text',
            $postText,
            null,
            'eitaa',
            'token-x',
            null,
            null,
            null,
            'html'
        );

        $this->assertTrue($result['success']);
        $this->assertCount(1, $publisher->captured);
        $this->assertSame($postText, $publisher->captured[0]['text']);
        $this->assertNull($publisher->captured[0]['parse_mode']);

        // فیلدهای API (خالص): بدون parse_mode + متن بدون «<»
        $fields = BotHelper::buildEitaaApiFields($publisher->captured[0]['chat'], $publisher->captured[0]['text'], false);
        $this->assertSame(['chat_id' => '@mychannel', 'text' => $postText], $fields);
        $this->assertArrayNotHasKey('parse_mode', $fields);
        $this->assertStringNotContainsString('<', $fields['text']);
    }

    // ─────────────────────────── media path: sendFile caption verbatim plain ───────────────────────────

    public function test_publish_eitaa_photo_caption_is_exactly_plain_multi_signature(): void
    {
        $signature = SignatureRenderer::renderMulti('eitaa', [
            ['url' => self::EITAA_URL, 'label' => 'کانال خودمان'],
            ['url' => 'https://eitaa.com/sharabebeheshti', 'label' => null],
        ], 'multi_line');

        $fullCaption = "کپشن عکس" . $signature['text'];

        $localPath = $this->makeTempFile('t6-eitaa-photo.jpg', 't6-eitaa-photo-bytes');
        $publisher = $this->realPublisher();

        Http::fake([
            'eitaayar.ir*' => Http::response(['ok' => true, 'result' => ['message_id' => 555]]),
        ]);

        $result = $publisher->publish(
            '-100999000',
            'photo',
            $fullCaption,
            'bale-origin-file-id',
            'eitaa',
            'eitaa-dest-token',
            $localPath,
            null,
            'image/jpeg',
            'html'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('555', $result['message_id']);
        Http::assertSentCount(1);

        Http::assertSent(function (Request $request) use ($fullCaption) {
            $data = $request->isMultipart() ? $this->multipartData($request) : $request->data();

            return $request->url() === 'https://eitaayar.ir/api/eitaa-dest-token/sendFile'
                && ($data['chat_id'] ?? null) === '-100999000'
                && ($data['caption'] ?? null) === $fullCaption
                && !isset($data['parse_mode'])
                && strpos((string) ($data['caption'] ?? ''), '<') === false;
        });
    }

    public function test_publish_eitaa_video_caption_is_exactly_plain_multi_signature(): void
    {
        $signature = SignatureRenderer::renderMulti('eitaa', [
            ['url' => self::EITAA_URL, 'label' => 'کانال خودمان'],
            ['url' => 'https://eitaa.com/sharabebeheshti', 'label' => null],
        ], 'multi_line');

        $fullCaption = "کپشن ویدیو" . $signature['text'];

        $localPath = $this->makeTempFile('t6-eitaa-video.mp4', 't6-eitaa-video-bytes');
        $publisher = $this->realPublisher();

        Http::fake([
            'eitaayar.ir*' => Http::response(['ok' => true, 'result' => ['message_id' => 666]]),
        ]);

        $result = $publisher->publish(
            '-100999001',
            'video',
            $fullCaption,
            'bale-origin-file-id',
            'eitaa',
            'eitaa-dest-token',
            $localPath,
            'clip.mp4',
            'video/mp4',
            'html'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('666', $result['message_id']);
        Http::assertSentCount(1);

        Http::assertSent(function (Request $request) use ($fullCaption) {
            $data = $request->isMultipart() ? $this->multipartData($request) : $request->data();

            return $request->url() === 'https://eitaayar.ir/api/eitaa-dest-token/sendFile'
                && ($data['chat_id'] ?? null) === '-100999001'
                && ($data['caption'] ?? null) === $fullCaption
                && !isset($data['parse_mode'])
                && strpos((string) ($data['caption'] ?? ''), '<') === false;
        });
    }
}
