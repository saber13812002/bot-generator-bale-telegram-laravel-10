<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Services\ChannelPoster\SignatureRenderer;
use App\Services\ChannelPoster\SignatureSettingsService;
use App\Services\ChannelPosterBotServiceImpl;
use App\Services\TelegramChannelPosterPublisher;
use App\Services\TelegramChannelPosterPublisherFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;
use Telegram;

/**
 * T8 — بستهٔ پذیرش ۱۰ سناریویی (addendum F).
 *
 * هر سناریوی پذیرشِ plan (docs/superpowers/plans/2026-11-10-channel-poster-signature-fixes.md)
 * یک تست مستقل با خروجی fix‌شده (assertSame روی رشتهٔ دقیق) است.
 *
 * سناریوهای 1–6 روی لایهٔ service (buildSignatureForDestination / deletePureUrlSignature)
 * با sqlite؛ سناریوهای 7–10 روی لایهٔ publisher (Mockery Telegram / Http::fake /
 * capture-publisher ایتا) — همان الگوهای تست‌های سبز موجود.
 */
class SignatureScenariosTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private const EITAA_URL = 'https://eitaa' . 'link.example/5';
    private const BALE_URL = 'https://balelink.example/3';
    private const SOROUSH_URL = 'https://soroushlink.example/4';
    private const TELEGRAM_URL = 'https://telegramlink.example/2';
    private const RUBIKA_URL = 'https://rubika' . 'link.example/1';

    private ChannelPosterBotServiceImpl $service;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();
        $this->service = new ChannelPosterBotServiceImpl();
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

    // ─────────────────────────────────────────────────────────────────────
    // Scenario 1 — حذف Pure URL، باقی ماندن Handle (T7 integration)
    // ─────────────────────────────────────────────────────────────────────

    public function test_scenario_1_delete_pure_url_keeps_handle_signatures(): void
    {
        $bot = $this->makeBot();
        // مقصد handle (اموجی + شناسه از دادهٔ خودِ مقصد)
        $handle = $this->makeDest($bot->id, 'telegram', 2, [
            'signature_label' => '⭐',
            'signature_type' => 'handle',
            'channel_username' => 'sabernews',
        ]);
        // مقصد pure_url (متن دلخواه + لینک)
        $pure = $this->makeDest($bot->id, 'bale', 3, [
            'signature_label' => 'لینک خبرگزاری ما',
            'signature_type' => 'pure_url',
        ]);

        // (a) deletepurelink روی مقصدی با type=handle ⇒ no-op
        $res = $this->service->deletePureUrlSignature($handle->id);
        $this->assertSame(['changed' => false, 'previous_type' => 'handle'], $res);
        $this->assertSame(
            "\n\n⭐ @sabernews",
            $this->service->buildSignatureForDestination($handle->fresh())['text']
        );

        // (b) deletepurelink روی type=pure_url ⇒ فقط فیلدهای pure پاک می‌شوند
        $res = $this->service->deletePureUrlSignature($pure->id);
        $this->assertSame(['changed' => true, 'previous_type' => 'pure_url'], $res);
        $pureFresh = $pure->fresh();
        $this->assertSame('none', $pureFresh->signature_type);
        $this->assertNull($pureFresh->signature_label);
        // ستون‌های غیرامضا دست‌نخورده
        $this->assertSame(self::BALE_URL, $pureFresh->channel_link);
        $this->assertNull($pureFresh->signature_enabled);
        $this->assertNull($pureFresh->signature_settings);
        $this->assertSame('', $this->service->buildSignatureForDestination($pureFresh)['text']);

        // (c) امضای handle مقصد دیگر با همان دادهٔ قبلی دست‌نخورده است
        $this->assertSame(
            "\n\n⭐ @sabernews",
            $this->service->buildSignatureForDestination($handle->fresh())['text']
        );

        // (d) pure→handle روی همان مقصد: بعد از حذف pure، امضای handle از
        //     دادهٔ خودِ مقصد سالم ساخته می‌شود
        $pureFresh->signature_type = 'handle';
        $pureFresh->channel_username = 'saberblog';
        $pureFresh->save();
        $this->assertSame(
            "\n\n🔰 @saberblog",
            $this->service->buildSignatureForDestination($pureFresh->fresh())['text']
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scenario 2 — Handle با فاصله: «🔰 @saberblog» (همهٔ پلتفرم‌ها)
    // ─────────────────────────────────────────────────────────────────────

    public function test_scenario_2_handle_signature_has_space_on_all_platforms(): void
    {
        foreach (['bale', 'telegram', 'eitaa', 'soroush', 'rubika'] as $platform) {
            $out = SignatureRenderer::renderHandle($platform, 'saberblog');
            $this->assertSame("\n\n🔰 @saberblog", $out['text'], "$platform");
            $this->assertNull($out['parse_mode'], "$platform: parse_mode always null");
            $this->assertSame(SignatureRenderer::TYPE_HANDLE, $out['type'], "$platform");
            $this->assertFalse($out['fallback'], "$platform");
            $this->assertNull($out['button'], "$platform");
        }

        // با «@» پیشوند هم نتیجهٔ یکسان (strip امن)
        $this->assertSame("\n\n🔰 @saberblog", SignatureRenderer::renderHandle('telegram', '@saberblog')['text']);
        // شناسهٔ نامعتبر ⇒ امضای خالی (تخمین ممنوع)
        $this->assertSame('', SignatureRenderer::renderHandle('telegram', '12345')['text']);

        // لایهٔ service: ایموجی پیش‌فرض 🔰 از دادهٔ خودِ مقصد
        $bot = $this->makeBot();
        $dest = $this->makeDest($bot->id, 'telegram', 2, [
            'signature_type' => 'handle',
            'channel_username' => 'saberblog',
        ]);
        $sig = $this->service->buildSignatureForDestination($dest);
        $this->assertSame("\n\n🔰 @saberblog", $sig['text']);
        $this->assertNull($sig['parse_mode']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scenario 3 — عدم نشت Label از Pure URL به امضای هم‌تگ‌ها + preview
    // ─────────────────────────────────────────────────────────────────────

    public function test_scenario_3_pure_url_label_never_leaks_to_tagmates_or_preview(): void
    {
        $bot = $this->makeBot();
        $tg = $this->makeDest($bot->id, 'telegram', 2, [
            'signature_type' => 'inline',
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'all_channels',
                'layout' => 'single_line',
                'separator' => ' | ',
            ]),
        ]);
        $bale = $this->makeDest($bot->id, 'bale', 3, [
            'signature_label' => 'لینک خبرگزاری ما',
            'signature_type' => 'pure_url',
        ]);

        // امضای مقصد تلگرام: label pure_url مقصد بله هرگز نمی‌آید؛
        // label لینک بله = برچسب پلتفرم
        $sig = $this->service->buildSignatureForDestination($tg);
        $this->assertNotEmpty($sig['text']);
        $this->assertStringNotContainsString('لینک خبرگزاری ما', $sig['text']);
        $this->assertStringContainsString('<a href="' . self::BALE_URL . '">در بله</a>', $sig['text']);

        // preview هم نوع‌آگاه است: pure_url ⇒ channel_link (نه label کاربر)
        $preview = SignatureSettingsService::buildPreview(
            [$tg, $bale],
            SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'all_channels',
                'layout' => 'single_line',
                'separator' => ' | ',
            ])
        );
        $this->assertSame(self::TELEGRAM_URL . ' | ' . self::BALE_URL, $preview);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scenario 4 — عدم نشت Emoji از Handle به label لینک‌های دیگر + preview
    // ─────────────────────────────────────────────────────────────────────

    public function test_scenario_4_handle_emoji_never_leaks_to_other_links_or_preview(): void
    {
        $bot = $this->makeBot();
        $tg = $this->makeDest($bot->id, 'telegram', 2, [
            'signature_type' => 'inline',
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'all_channels',
                'layout' => 'single_line',
                'separator' => ' | ',
            ]),
        ]);
        $soroush = $this->makeDest($bot->id, 'soroush', 4, [
            'signature_label' => '⭐',
            'signature_type' => 'handle',
            'channel_username' => 'sabernews',
        ]);

        // امضای مقصد تلگرام: ایموجی handle مقصد سروش هرگز label لینک نمی‌شود
        $sig = $this->service->buildSignatureForDestination($tg);
        $this->assertNotEmpty($sig['text']);
        $this->assertStringNotContainsString('⭐', $sig['text']);
        $this->assertStringContainsString('<a href="' . self::SOROUSH_URL . '">در سروش</a>', $sig['text']);

        // preview: handle ⇒ channel_link (نه ایموجی)
        $preview = SignatureSettingsService::buildPreview(
            [$tg, $soroush],
            SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'all_channels',
                'layout' => 'single_line',
                'separator' => ' | ',
            ])
        );
        $this->assertSame(self::TELEGRAM_URL . ' | ' . self::SOROUSH_URL, $preview);

        // و خودِ مقصد سروسh (legacy handle) هم دقیقاً emoji + handle می‌دهد
        $own = $this->service->buildSignatureForDestination($soroush);
        $this->assertSame("\n\n⭐ @sabernews", $own['text']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scenario 5 — Multi-line در هر ۵ پیام‌رسان (label:/link دوخطه، dedup، order)
    // ─────────────────────────────────────────────────────────────────────

    public function test_scenario_5_multi_line_contract_on_all_five_messengers(): void
    {
        foreach (['eitaa', 'bale', 'soroush', 'telegram', 'rubika'] as $platform) {
            $dest = $this->smartSetup($platform, 'multi_line');
            $sig = $this->service->buildSignatureForDestination($dest);

            $this->assertSame(
                $this->expectedMulti($platform),
                $sig['text'],
                "multi_line on $platform"
            );
            $this->assertSame(
                $platform === 'telegram' ? 'html' : null,
                $sig['parse_mode'],
                "multi_line $platform parse_mode"
            );
        }

        // dedup: لینک تکراری بله (مقصد دوم با همان url) فقط یک‌بار نمایش داده می‌شود
        $dest = $this->smartSetup('soroush', 'multi_line');
        $sig = $this->service->buildSignatureForDestination($dest);
        $this->assertSame(1, substr_count($sig['text'], self::BALE_URL), 'dedup by url');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scenario 6 — Single-line در هر ۵ پیام‌رسان
    // ─────────────────────────────────────────────────────────────────────

    public function test_scenario_6_single_line_contract_on_all_five_messengers(): void
    {
        foreach (['eitaa', 'bale', 'soroush', 'telegram', 'rubika'] as $platform) {
            $dest = $this->smartSetup($platform, 'single_line');
            $sig = $this->service->buildSignatureForDestination($dest);

            $this->assertSame(
                $this->expectedSingle($platform),
                $sig['text'],
                "single_line on $platform"
            );
            $this->assertSame(
                $platform === 'telegram' ? 'html' : null,
                $sig['parse_mode'],
                "single_line $platform parse_mode"
            );
        }

        // dedup در single_line (رندر بله): فقط یک anchor مارک‌داون بله
        $dest = $this->smartSetup('bale', 'single_line');
        $sig = $this->service->buildSignatureForDestination($dest);
        $this->assertSame(
            1,
            substr_count($sig['text'], '[در بله](' . self::BALE_URL . ')'),
            'dedup by url (bale markdown)'
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scenario 7 — HTML در پیام متنی تلگرام (parse_mode=html، escape صحیح)
    // ─────────────────────────────────────────────────────────────────────

    public function test_scenario_7_telegram_text_keeps_html_parse_mode_with_escaped_label(): void
    {
        // رندر: escape برچسب + anchor
        $out = SignatureRenderer::render(
            'telegram',
            self::TELEGRAM_URL,
            SignatureRenderer::TYPE_INLINE,
            'برچسب <b>ناقص</b> & "کوتاه"'
        );
        $this->assertSame(
            "\n\nبرچسب &lt;b&gt;ناقص&lt;/b&gt; &amp; &quot;کوتاه&quot;:\n"
            . '<a href="' . self::TELEGRAM_URL . '">' . self::TELEGRAM_URL . '</a>',
            $out['text']
        );
        $this->assertSame('html', $out['parse_mode']);

        // publisher: sendMessage با parse_mode=html (retry guard سناریوی 10)
        $bot = Mockery::mock(Telegram::class);
        $bot->shouldReceive('token')->andReturn('123:origin-token');
        $contents = [];
        $bot->shouldReceive('sendMessage')->once()->andReturnUsing(function (array $c) use (&$contents) {
            $contents[] = $c;

            return ['ok' => true, 'result' => ['message_id' => 555]];
        });
        $bot->shouldReceive('answerCallbackQuery')->andReturn(['ok' => true]);

        $publisher = new TelegramChannelPosterPublisher($bot, 'bale', null);
        $result = $publisher->publish(
            '-1001234567890',
            'text',
            'متن پست' . $out['text'],
            null,
            'telegram',
            null,
            null,
            null,
            null,
            $out['parse_mode']
        );

        $this->assertTrue($result['success']);
        $this->assertCount(1, $contents);
        $this->assertSame('html', $contents[0]['parse_mode'] ?? null);
        $this->assertStringContainsString('&lt;b&gt;ناقص&lt;/b&gt;', $contents[0]['text']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scenario 8 — HTML در کپشن رسانهٔ تلگرام (multipart با parse_mode)
    // ─────────────────────────────────────────────────────────────────────

    public function test_scenario_8_telegram_media_caption_keeps_html_parse_mode_multipart(): void
    {
        $sig = SignatureRenderer::renderMulti('telegram', [
            ['url' => self::BALE_URL, 'label' => 'در بله'],
            ['url' => self::EITAA_URL, 'label' => 'در ایتا'],
        ], 'multi_line');
        $this->assertSame('html', $sig['parse_mode']);

        $caption = 'کپشن <b>فاتحه</b>' . $sig['text'];
        $localPath = $this->makeTempFile('t8-media-cap.jpg', 't8-media-cap-bytes');

        $publisher = app(TelegramChannelPosterPublisherFactory::class)->make('123:origin-token', 'bale');
        Http::fake([
            'api.telegram.org*' => Http::response(['ok' => true, 'result' => ['message_id' => 777]]),
        ]);

        $result = $publisher->publish(
            '-1001234567890',
            'photo',
            $caption,
            'bale-origin-file-id',
            'telegram',
            '999:tg-dest-token',
            $localPath,
            't8-media-cap.jpg',
            'image/jpeg',
            'html'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('777', $result['message_id']);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($caption) {
            $data = $this->multipartData($request);

            return $request->url() === 'https://api.telegram.org/bot999:tg-dest-token/sendPhoto'
                && ($data['parse_mode'] ?? null) === 'html'
                && ($data['caption'] ?? null) === $caption;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scenario 9 — عدم نمایش HTML خام در ایتا (متن + photo + video)
    // ─────────────────────────────────────────────────────────────────────

    public function test_scenario_9_eitaa_never_receives_raw_html_text_photo_video(): void
    {
        $sig = SignatureRenderer::renderMulti('eitaa', [
            ['url' => self::EITAA_URL, 'label' => 'در ایتا'],
            ['url' => self::BALE_URL, 'label' => 'در بله'],
        ], 'multi_line');
        $this->assertStringNotContainsString('<', $sig['text']);
        $this->assertNull($sig['parse_mode']);

        // (a) text: بالادستی parse_mode=html فرستاده؛ publisher برای ایتا null می‌کند
        $capture = $this->makeCapturingEitaaPublisher();
        $text = 'متن پست' . $sig['text'];
        $result = $capture->publish(
            '-100111222',
            'text',
            $text,
            null,
            'eitaa',
            'eitaa-dest-token',
            null,
            null,
            null,
            'html'
        );
        $this->assertTrue($result['success']);
        $this->assertCount(1, $capture->captured);
        $this->assertNull($capture->captured[0]['parse_mode'], 'eitaa text: parse_mode forced null');
        $this->assertSame($text, $capture->captured[0]['text']);
        $this->assertStringNotContainsString('<', $capture->captured[0]['text']);

        // (b) photo: caption verbatim plain + بدون فیلد parse_mode در multipart
        $photoCaption = 'کپشن عکس' . $sig['text'];
        $localPath = $this->makeTempFile('t8-eitaa-photo.jpg', 't8-eitaa-photo-bytes');
        $publisher = $this->realPublisher();
        Http::fake([
            'eitaayar.ir*' => Http::sequence()
                ->push(['ok' => true, 'result' => ['message_id' => 555]])
                ->push(['ok' => true, 'result' => ['message_id' => 666]]),
        ]);
        $result = $publisher->publish(
            '-100999000',
            'photo',
            $photoCaption,
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
        Http::assertSent(function (Request $request) use ($photoCaption) {
            $data = $request->isMultipart() ? $this->multipartData($request) : $request->data();

            return $request->url() === 'https://eitaayar.ir/api/eitaa-dest-token/sendFile'
                && ($data['caption'] ?? null) === $photoCaption
                && !isset($data['parse_mode'])
                && strpos((string) ($data['caption'] ?? ''), '<') === false;
        });

        // (c) video: همان قرارداد
        $videoCaption = 'کپشن ویدیو' . $sig['text'];
        $videoPath = $this->makeTempFile('t8-eitaa-video.mp4', 't8-eitaa-video-bytes');
        $result = $publisher->publish(
            '-100999001',
            'video',
            $videoCaption,
            'bale-origin-file-id',
            'eitaa',
            'eitaa-dest-token',
            $videoPath,
            'clip.mp4',
            'video/mp4',
            'html'
        );
        $this->assertTrue($result['success']);
        $this->assertSame('666', $result['message_id']);
        Http::assertSent(function (Request $request) use ($videoCaption) {
            $data = $request->isMultipart() ? $this->multipartData($request) : $request->data();

            return $request->url() === 'https://eitaayar.ir/api/eitaa-dest-token/sendFile'
                && ($data['caption'] ?? null) === $videoCaption
                && !isset($data['parse_mode'])
                && strpos((string) ($data['caption'] ?? ''), '<') === false;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scenario 10 — عدم ارسال تکراری در retry + حفظ تنظیمات سایر مقصدها
    // ─────────────────────────────────────────────────────────────────────

    public function test_scenario_10_retry_sends_exactly_once_and_settings_of_other_dests_preserved(): void
    {
        // (a) parse error صریح ⇒ دقیقاً یک retry بدون parse_mode
        $bot = Mockery::mock(Telegram::class);
        $bot->shouldReceive('token')->andReturn('123:origin-token');
        $calls = 0;
        $contents = [];
        $bot->shouldReceive('sendMessage')->times(2)->andReturnUsing(function (array $c) use (&$calls, &$contents) {
            $calls++;
            $contents[] = $c;

            return $calls === 1
                ? ['ok' => false, 'error_code' => 400, 'description' => "Bad Request: can't parse entities"]
                : ['ok' => true, 'result' => ['message_id' => 555]];
        });
        $bot->shouldReceive('answerCallbackQuery')->andReturn(['ok' => true]);
        $publisher = new TelegramChannelPosterPublisher($bot, 'bale', null);
        $result = $publisher->publish(
            '-1001234567890',
            'text',
            'متن <b>امضا</b> ناقص',
            null,
            'telegram',
            null,
            null,
            null,
            null,
            'html'
        );
        $this->assertTrue($result['success'], 'retry بدون parse_mode باید موفق شود');
        $this->assertCount(2, $contents, 'parse error: exactly one retry');
        $this->assertSame('html', $contents[0]['parse_mode'] ?? null);
        $this->assertArrayNotHasKey('parse_mode', $contents[1], 'retry بدون parse_mode');
        $this->assertSame('متن <b>امضا</b> ناقص', $contents[1]['text']);

        // (b) خطای شبکه (timeout) ⇒ هیچ retry
        $net = Mockery::mock(Telegram::class);
        $net->shouldReceive('token')->andReturn('123:origin-token');
        $net->shouldReceive('sendMessage')->once()->andReturn([
            'ok' => false,
            'curl_error_code' => 28,
            'curl_error' => 'Operation timed out after 300001 milliseconds',
        ]);
        $net->shouldReceive('answerCallbackQuery')->andReturn(['ok' => true]);
        $netPublisher = new TelegramChannelPosterPublisher($net, 'bale', null);
        $result = $netPublisher->publish(
            '-1001234567890', 'text', 'متن ساده', null, 'telegram', null, null, null, null, 'html'
        );
        $this->assertFalse($result['success']);
        $this->assertSame(1, $net->mockery_getExpectationCount('sendMessage'), 'network: no retry');

        // (c) خطای API نامشخص (غیر-parse) ⇒ هیچ retry
        $unknown = Mockery::mock(Telegram::class);
        $unknown->shouldReceive('token')->andReturn('123:origin-token');
        $unknown->shouldReceive('sendMessage')->once()->andReturn([
            'ok' => false,
            'error_code' => 400,
            'description' => 'Bad Request: chat not found',
        ]);
        $unknown->shouldReceive('answerCallbackQuery')->andReturn(['ok' => true]);
        $unknownPublisher = new TelegramChannelPosterPublisher($unknown, 'bale', null);
        $result = $unknownPublisher->publish(
            '-1001234567890', 'text', 'متن ساده', null, 'telegram', null, null, null, null, 'html'
        );
        $this->assertFalse($result['success']);
        $this->assertSame(1, $unknown->mockery_getExpectationCount('sendMessage'), 'unknown api error: no retry');

        // (d) deletepurelink روی مقصد A ⇒ تنظیمات مقصد B کامل دست‌نخورده
        $botItem = $this->makeBot();
        $a = $this->makeDest($botItem->id, 'bale', 1, [
            'signature_label' => 'لینک خبرگزاری ما',
            'signature_type' => 'pure_url',
        ]);
        $b = $this->makeDest($botItem->id, 'telegram', 2, [
            'signature_label' => '⭐',
            'signature_type' => 'handle',
            'channel_username' => 'sabernews',
            'signature_enabled' => true,
        ]);
        $before = [
            'signature_type' => $b->signature_type,
            'signature_label' => $b->signature_label,
            'signature_enabled' => $b->signature_enabled,
            'signature_settings' => $b->signature_settings,
            'channel_username' => $b->channel_username,
            'channel_link' => $b->channel_link,
            'channel_chat_id' => $b->channel_chat_id,
        ];

        $this->service->deletePureUrlSignature($a->id);

        $bFresh = $b->fresh();
        foreach ($before as $col => $val) {
            $this->assertSame($val, $bFresh->{$col}, "destination B column $col untouched");
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function makeBot(): Bot
    {
        return Bot::create(['endpoint_id' => 'webhook-channel-poster', 'bale_bot_token' => '1:token']);
    }

    private function makeDest(int $botId, string $platform, int $n, array $attrs = []): ChannelPosterDestination
    {
        return ChannelPosterDestination::create(array_merge([
            'bot_id' => $botId,
            'platform' => $platform,
            'channel_chat_id' => 'sc-' . $platform . $n,
            'channel_link' => 'https://' . $platform . 'link.example/' . $n,
            'is_active' => true,
            'verified_at' => now(),
        ], $attrs));
    }

    /**
     * یک bot جدید + 5 مقصد (هر پلتفرم یک‌بار، به ترتیب درجِ غیرمطابق priority)
     * + یک مقصد بلهٔ تکراری با همان url (برای dedup). فقط مقصد رندرکننده
     * تنظیمات هوشمند دارد. مقصد رندرکننده برگردانده می‌شود.
     */
    private function smartSetup(string $renderPlatform, string $layout): ChannelPosterDestination
    {
        $bot = $this->makeBot();
        $order = ['rubika' => 1, 'telegram' => 2, 'bale' => 3, 'soroush' => 4, 'eitaa' => 5];

        $dests = [];
        foreach ($order as $platform => $n) {
            $attrs = [];
            if ($platform === $renderPlatform) {
                $attrs = [
                    'signature_type' => 'inline',
                    'signature_settings' => SignatureSettingsService::normalize([
                        'enabled' => true,
                        'link_selection_policy' => 'all_channels',
                        'layout' => $layout,
                        'separator' => ' | ',
                    ]),
                ];
            }
            $dests[$platform] = $this->makeDest($bot->id, $platform, $n, $attrs);
        }

        // dedup contract: url بله دو بار (دو مقصد، یک لینک)
        $this->makeDest($bot->id, 'bale', 31, [
            'channel_chat_id' => 'sc-bale-dup',
            'channel_link' => self::BALE_URL,
        ]);

        return $dests[$renderPlatform];
    }

    /**
     * لینک‌ها به ترتیب پیش‌فرض (addendum D): ایتا ← بله ← سروش ← تلگرام ← روبیکا
     * (برچسب‌ها = برچسب‌های پلتفرم؛ هیچ label کاربری در ستاپ سناریو 5/6 نیست).
     */
    private function expectedLinks(): array
    {
        return [
            ['url' => self::EITAA_URL, 'label' => 'در ایتا'],
            ['url' => self::BALE_URL, 'label' => 'در بله'],
            ['url' => self::SOROUSH_URL, 'label' => 'در سروش'],
            ['url' => self::TELEGRAM_URL, 'label' => 'در تلگرام'],
            ['url' => self::RUBIKA_URL, 'label' => 'در روبیکا'],
        ];
    }

    /**
     * قرارداد دوخطهٔ multi_line (addendum D) برای هر پلتفرم:
     *  - telegram: «برچسب:\n<a href>برچسب</a>» (html)
     *  - bale: «برچسب:\n[url](url)»
     *  - بقیه: «برچسب:\nurl» (خودرسان)
     */
    private function expectedMulti(string $platform): string
    {
        $out = [];
        foreach ($this->expectedLinks() as $l) {
            [$url, $line] = [$l['url'], $l['label']];

            if ($platform === 'telegram') {
                $out[] = $line . ":\n<a href=\"" . $url . '">' . $line . '</a>';
            } elseif ($platform === 'bale') {
                $out[] = $line . ":\n[" . $url . '](' . $url . ')';
            } else {
                $out[] = $line . ":\n" . $url;
            }
        }

        return "\n\n" . implode("\n", $out);
    }

    /**
     * single_line برای هر پلتفرم:
     *  - telegram: anchor (html)
     *  - bale: [برچسب](url)
     *  - بقیه: «برچسب: url» — با جداکنندهٔ « | » در یک خط.
     */
    private function expectedSingle(string $platform): string
    {
        $parts = [];
        foreach ($this->expectedLinks() as $l) {
            [$url, $line] = [$l['url'], $l['label']];

            if ($platform === 'telegram') {
                $parts[] = '<a href="' . $url . '">' . $line . '</a>';
            } elseif ($platform === 'bale') {
                $parts[] = '[' . $line . '](' . $url . ')';
            } else {
                $parts[] = $line . ': ' . $url;
            }
        }

        return "\n\n" . implode(' | ', $parts);
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

    private function realPublisher(): TelegramChannelPosterPublisher
    {
        return app(TelegramChannelPosterPublisherFactory::class)->make('123:origin-token', 'bale');
    }

    private function multipartData(Request $request): array
    {
        return collect($request->data())
            ->mapWithKeys(fn (array $entry) => [$entry['name'] => $entry['contents']])
            ->all();
    }

    /**
     * Publisher واقعی با capture روی seam متنی ایتا (publish → publishEitaaText).
     *
     * @return TelegramChannelPosterPublisher&object{captured: array}
     */
    private function makeCapturingEitaaPublisher()
    {
        $bot = new class extends Telegram {
            public function __construct()
            {
                // عمداً parent::__construct() صدا نمی‌شود (getData() = تماس شبکه).
            }
        };

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
}
