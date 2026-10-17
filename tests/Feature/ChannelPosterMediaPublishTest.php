<?php

namespace Tests\Feature;

use App\Http\Controllers\ChannelPosterBotController;
use App\Interfaces\Services\ChannelPosterPublisher;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterPublishLog;
use App\Models\ChannelPosterQueue;
use App\Services\ChannelPosterMediaSender;
use App\Services\ChannelPosterMediaStore;
use App\Services\TelegramChannelPosterPublisher;
use App\Services\TelegramChannelPosterPublisherFactory;
use Telegram;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * تست‌های Feature برای انتشار عکس + کپشن (Media fix)
 *
 * پوشش:
 *  - تلگرام/بله فایل واقعی را با multipart (باینری فایل + caption) دریافت می‌کنند
 *  - ایتایار از sendFile با فیلدهای file + caption استفاده می‌کند
 *  - شکست آپلود → انتشار فقط‌متن با وضعیت «موفق» نمی‌شود (موفقیت کاذب)
 *  - file_id فقط روی پلتفرم مبدأ fallback می‌شود، هرگز متن جایگزین
 *  - انتشار صف: موفقیت = حذف فایل؛ شکست جزئی = ماندن فایل برای retry
 *  - retry: مقصدی که قبلاً موفق شده دوباره منتشر نمی‌شود
 *  - انتشار فوری از وب‌هُک: فایل دانلود‌شده پس از انتشار پاک می‌شود
 *  - enqueue: media_path روی ردیف صف ذخیره می‌شود و فایل حذف نمی‌شود
 *  - پاک‌سازی یتیم‌ها / آمار diagnose / دستور diagnose فقط‌خواندنی
 */
class ChannelPosterMediaPublishTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private FakeChannelPosterPublisher $publisher;

    private string $token = '123:channel-poster-token';

    private string $ownerChatId = '111';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();

        $this->publisher = new FakeChannelPosterPublisher();
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
        @rmdir(storage_path('app/channel-poster-media'));

        Mockery::close();
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1) انتشار واقعی (Real publisher) — آپلود multipart با Http::fake
    // ─────────────────────────────────────────────────────────────────────

    public function test_telegram_receives_real_file_as_multipart_with_caption(): void
    {
        $localPath = $this->makeTempFile('telegram-photo.jpg', 'telegram-photo-bytes');
        $contents = 'telegram-photo-bytes';

        $publisher = $this->realPublisher('123:origin-token');

        Http::fake([
            'api.telegram.org*' => Http::response(['ok' => true, 'result' => ['message_id' => 777]]),
        ]);

        $result = $publisher->publish(
            '-1001234567890',
            'photo',
            'کپشن عکس برای تلگرام',
            'bale-origin-file-id',
            'telegram',
            '999:tg-dest-token',
            $localPath
        );

        $this->assertTrue($result['success']);
        $this->assertSame('777', $result['message_id']);
        $this->assertNull($result['error']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($contents, $localPath) {
            $data = $this->multipartData($request);

            return $request->url() === 'https://api.telegram.org/bot999:tg-dest-token/sendPhoto'
                && $request->isMultipart()
                && $request->hasFile('photo', $contents, basename($localPath))
                && ($data['chat_id'] ?? null) === '-1001234567890'
                && ($data['caption'] ?? null) === 'کپشن عکس برای تلگرام';
        });
    }

    public function test_bale_receives_real_file_as_multipart_with_caption(): void
    {
        $localPath = $this->makeTempFile('bale-photo.jpg', 'bale-photo-bytes');
        $contents = 'bale-photo-bytes';

        $publisher = $this->realPublisher('123:origin-token');

        Http::fake([
            'tapi.bale.ai*' => Http::response(['ok' => true, 'result' => ['message_id' => 321]]),
        ]);

        $result = $publisher->publish(
            'channel-bale-1',
            'photo',
            'کپشن عکس برای بله',
            'bale-origin-file-id',
            'bale',
            '123:origin-token',
            $localPath
        );

        $this->assertTrue($result['success']);
        $this->assertSame('321', $result['message_id']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($contents, $localPath) {
            $data = $this->multipartData($request);

            return $request->url() === 'https://tapi.bale.ai/bot123:origin-token/sendPhoto'
                && $request->isMultipart()
                && $request->hasFile('photo', $contents, basename($localPath))
                && ($data['chat_id'] ?? null) === 'channel-bale-1'
                && ($data['caption'] ?? null) === 'کپشن عکس برای بله';
        });
    }

    public function test_eitaa_uploads_via_sendfile_with_file_and_caption(): void
    {
        $localPath = $this->makeTempFile('eitaa-photo.jpg', 'eitaa-photo-bytes');
        $contents = 'eitaa-photo-bytes';

        $publisher = $this->realPublisher('123:origin-token');

        Http::fake([
            'eitaayar.ir*' => Http::response(['ok' => true, 'result' => ['message_id' => 555]]),
        ]);

        $result = $publisher->publish(
            '-100999000',
            'photo',
            'کپشن عکس برای ایتا',
            'bale-origin-file-id',
            'eitaa',
            'eitaa-dest-token',
            $localPath
        );

        $this->assertTrue($result['success']);
        $this->assertSame('555', $result['message_id']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($contents, $localPath) {
            $data = $this->multipartData($request);

            return $request->url() === 'https://eitaayar.ir/api/eitaa-dest-token/sendFile'
                && $request->isMultipart()
                && $request->hasFile('file', $contents, basename($localPath))
                && ($data['chat_id'] ?? null) === '-100999000'
                && ($data['caption'] ?? null) === 'کپشن عکس برای ایتا';
        });
    }

    public function test_failed_upload_does_not_publish_text_only(): void
    {
        // مبدأ بله → مقصد تلگرام: file_id برای تلگرام اعتبار ندارد؛
        // پس در شکست آپلود هیچ fallback (نه file_id، نه متن) نباید باشد.
        $localPath = $this->makeTempFile('fail-photo.jpg', 'fail-photo-bytes');

        $publisher = $this->realPublisher('123:origin-token');

        Http::fake([
            'api.telegram.org*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400),
            'tapi.bale.ai*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);

        $result = $publisher->publish(
            '-1001234567890',
            'photo',
            'کپشن',
            'bale-origin-file-id',
            'telegram',
            '999:tg-dest-token',
            $localPath
        );

        $this->assertFalse($result['success']);
        $this->assertNull($result['message_id']);
        $this->assertSame('HTTP 400', $result['error']);

        // فقط یک درخواست آپلود — هیچ sendMessage (انتشار فقط‌متن) نشده
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/sendPhoto'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'sendMessage'));
    }

    public function test_failed_upload_on_origin_falls_back_to_file_id_not_text(): void
    {
        // مقصد همان پلتفرم مبدأ (بله → بله): پس از شکست آپلود multipart،
        // fallback با file_id روی همان پلتفرم مجاز است — ولی نه انتشار متن.
        $localPath = $this->makeTempFile('origin-fallback.jpg', 'origin-fallback-bytes');
        $contents = 'origin-fallback-bytes';

        $publisher = $this->realPublisher('123:origin-token');

        Http::fake([
            'tapi.bale.ai*' => Http::sequence()
                ->push(['ok' => false, 'description' => 'bale down'], 400)
                ->push(['ok' => true, 'result' => ['message_id' => 42]]),
        ]);

        $result = $publisher->publish(
            'channel-42',
            'photo',
            'کپشن فالبک',
            'bale-file-id-xyz',
            'bale',
            '123:origin-token',
            $localPath
        );

        $this->assertTrue($result['success']);
        $this->assertSame('42', $result['message_id']);

        Http::assertSentCount(2);
        Http::assertSentInOrder([
            fn (Request $request) => $request->url() === 'https://tapi.bale.ai/bot123:origin-token/sendPhoto'
                && $request->isMultipart()
                && $request->hasFile('photo', $contents, basename($localPath)),
            fn (Request $request) => $request->url() === 'https://tapi.bale.ai/bot123:origin-token/sendPhoto'
                && $request->isForm()
                && $request->data()['photo'] === 'bale-file-id-xyz'
                && $request->data()['chat_id'] === 'channel-42',
        ]);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'sendMessage'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2) انتشار از صف — فایل روی دیسک + پاک‌سازی + idempotent retry
    // ─────────────────────────────────────────────────────────────────────

    public function test_queue_publish_success_deletes_media_file(): void
    {
        $bot = $this->createBot('123:queue-origin-token');
        $this->createDestination($bot->id, 'telegram', 'channel-tg', '999:tg');

        $localPath = $this->makeTempFile('queued-photo.jpg', 'queued-photo-bytes');
        $contents = 'queued-photo-bytes';

        $item = $this->createMediaQueueItem($bot->id, 'tag1', $localPath, 'bale-file-1');

        Http::fake([
            'api.telegram.org*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]]),
        ]);

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertSuccessful();

        $item->refresh();
        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->status);
        $this->assertNull($item->media_path);
        $this->assertFileDoesNotExist($localPath);

        $this->assertDatabaseHas('channel_poster_publish_logs', [
            'queue_id' => $item->id,
            'platform' => 'telegram',
            'success' => true,
            'message_id' => '9',
        ]);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($contents, $localPath) {
            $data = $this->multipartData($request);

            return $request->url() === 'https://api.telegram.org/bot999:tg/sendPhoto'
                && $request->isMultipart()
                && $request->hasFile('photo', $contents, basename($localPath))
                && ($data['chat_id'] ?? null) === 'channel-tg'
                && ($data['caption'] ?? null) === 'کپشن صف';
        });
    }

    public function test_queue_publish_partial_failure_keeps_media_file(): void
    {
        $bot = $this->createBot('123:queue-origin-token');
        $this->createDestination($bot->id, 'telegram', 'channel-tg', '999:tg');
        $this->createDestination($bot->id, 'bale', 'channel-bale', '123:queue-origin-token');

        $localPath = $this->makeTempFile('queued-photo-fail.jpg', 'queued-photo-fail-bytes');

        $item = $this->createMediaQueueItem($bot->id, 'tag1', $localPath, 'bale-file-1');

        Http::fake([
            'api.telegram.org*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]]),
            'tapi.bale.ai*' => Http::response(['ok' => false, 'description' => 'bale unavailable'], 400),
        ]);

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertFailed();

        $item->refresh();
        $this->assertSame(ChannelPosterQueue::STATUS_FAILED, $item->status);
        $this->assertSame($localPath, $item->media_path);
        $this->assertFileExists($localPath);

        $this->assertDatabaseHas('channel_poster_publish_logs', [
            'queue_id' => $item->id,
            'platform' => 'telegram',
            'success' => true,
        ]);
        $this->assertDatabaseHas('channel_poster_publish_logs', [
            'queue_id' => $item->id,
            'platform' => 'bale',
            'success' => false,
        ]);
    }

    public function test_retry_does_not_republish_successful_destination(): void
    {
        $bot = $this->createBot('123:queue-origin-token');
        $this->createDestination($bot->id, 'bale', 'channel-bale', '123:queue-origin-token');
        $this->createDestination($bot->id, 'telegram', 'channel-tg', '999:tg');

        $localPath = $this->makeTempFile('retry-photo.jpg', 'retry-photo-bytes');

        $item = $this->createMediaQueueItem($bot->id, 'tag1', $localPath, 'bale-file-1');

        Http::fake([
            'api.telegram.org*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]]),
            'tapi.bale.ai*' => Http::sequence()
                ->push(['ok' => false, 'description' => 'bale down'], 400) // run 1: multipart
                ->push(['ok' => false, 'description' => 'bale down'], 400) // run 1: file_id fallback
                ->push(['ok' => true, 'result' => ['message_id' => 55]]),  // run 2: multipart
        ]);

        // run 1: بله می‌شکند (آپلود + فالبک file_id)، تلگرام موفق
        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertFailed();
        $this->assertSame(ChannelPosterQueue::STATUS_FAILED, $item->refresh()->status);

        // retry: وضعیت را برمی‌گردانیم (مثل watchdog/دستور retry در عملیات واقعی)
        $item->update(['status' => ChannelPosterQueue::STATUS_PENDING]);

        // run 2: بله تکرار می‌شود و موفق می‌شود؛ تلگرام باید SKIP شود
        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])
            ->assertSuccessful();

        $item->refresh();
        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->status);
        $this->assertNull($item->media_path);
        $this->assertFileDoesNotExist($localPath);

        // تلگرام فقط یک‌بار (run 1) ارسال شده — در run 2 تکرار نشده
        $telegramRequests = Http::recorded(fn (Request $request) => str_contains($request->url(), 'api.telegram.org'));
        $this->assertCount(1, $telegramRequests);

        // بله: 2 درخواست شکست‌خورده در run 1 + 1 موفق در run 2
        $baleRequests = Http::recorded(fn (Request $request) => str_contains($request->url(), 'tapi.bale.ai'));
        $this->assertCount(3, $baleRequests);

        $this->assertSame(1, ChannelPosterPublishLog::where('queue_id', $item->id)
            ->where('platform', 'telegram')->where('success', true)->count());
        $this->assertSame(1, ChannelPosterPublishLog::where('queue_id', $item->id)
            ->where('platform', 'bale')->where('success', true)->count());
        $this->assertSame(2, ChannelPosterPublishLog::where('queue_id', $item->id)
            ->where('platform', 'bale')->count());
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3) انتشار از وب‌هُک (عکس + کپشن) — دانلود فایل + انتشار فوری/صف
    // ─────────────────────────────────────────────────────────────────────

    public function test_immediate_publish_cleans_up_media_file(): void
    {
        $bot = $this->createBot($this->token, ChannelPosterBotController::ENDPOINT_ID);
        $this->createDestination($bot->id, 'telegram', '-1001234567890', '999:tg');

        $mediaPath = $this->makeTempFile('webhook-photo.jpg', 'webhook-photo-bytes');

        $store = Mockery::mock(ChannelPosterMediaStore::class)->makePartial();
        $store->shouldReceive('fetchAndStore')
            ->once()
            ->with($this->token, 'photo-file-id-123', 'bale', 'photo', null)
            ->andReturn($mediaPath);
        $this->app->instance(ChannelPosterMediaStore::class, $store);

        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->publisher)
        );

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privatePhotoUpdate($this->ownerChatId, 'photo-file-id-123', 'کپشن عکس تست')
        )->assertOk();

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->callbackUpdate('cb-now', 'cp:now', $this->ownerChatId)
        )->assertOk();

        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame($mediaPath, $this->publisher->publishes[0]['local_path']);
        $this->assertSame('photo', $this->publisher->publishes[0]['content_type']);
        $this->assertSame('کپشن عکس تست', $this->publisher->publishes[0]['text']);
        $this->assertSame('photo-file-id-123', $this->publisher->publishes[0]['file_id']);
        $this->assertSame('telegram', $this->publisher->publishes[0]['platform']);
        $this->assertSame('999:tg', $this->publisher->publishes[0]['bot_token']);
        $this->assertSame('-1001234567890', $this->publisher->publishes[0]['channel_chat_id']);

        // انتشار فوری = بدون ردیف صف؛ فایل باید واقعاً از دیسک پاک شده باشد
        $this->assertFileDoesNotExist($mediaPath);
        $this->assertSame(0, ChannelPosterQueue::count());
        $this->assertSame(0, BotUserState::count());

        $this->assertDatabaseHas('channel_poster_publish_logs', [
            'bot_id' => $bot->id,
            'queue_id' => null,
            'platform' => 'telegram',
            'success' => true,
        ]);
    }

    public function test_enqueue_stores_media_path_on_queue_item(): void
    {
        $bot = $this->createBot($this->token, ChannelPosterBotController::ENDPOINT_ID);
        $this->createDestination($bot->id, 'telegram', '-1001234567890', '999:tg');

        $mediaPath = $this->makeTempFile('webhook-photo-queue.jpg', 'webhook-photo-queue-bytes');

        $store = Mockery::mock(ChannelPosterMediaStore::class)->makePartial();
        $store->shouldReceive('fetchAndStore')
            ->once()
            ->with($this->token, 'photo-file-id-123', 'bale', 'photo', null)
            ->andReturn($mediaPath);
        $store->shouldReceive('delete')->never();
        $this->app->instance(ChannelPosterMediaStore::class, $store);

        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->publisher)
        );

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privatePhotoUpdate($this->ownerChatId, 'photo-file-id-123', 'کپشن عکس صف')
        )->assertOk();

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->callbackUpdate('cb-queue', 'cp:queue', $this->ownerChatId)
        )->assertOk();

        $this->assertCount(0, $this->publisher->publishes);

        $this->assertDatabaseHas('channel_poster_queue', [
            'bot_id' => $bot->id,
            'content_type' => 'photo',
            'text' => 'کپشن عکس صف',
            'file_id' => 'photo-file-id-123',
            'media_path' => $mediaPath,
            'status' => ChannelPosterQueue::STATUS_PENDING,
        ]);

        // در صف‌بندی فایل باید روی دیسک بماند (برای ارسال در موعد مقرر)
        $this->assertFileExists($mediaPath);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3b) MP3/آهنگ از وب‌ه‌ک (document) — دانلود، انتشار به ۳ پلتفرم،
    //     حفظ نام واقعی + MIME، پاک‌سازی، و جلوگیری از موفقیت کاذب
    // ─────────────────────────────────────────────────────────────────────

    public function test_mp3_document_webhook_publishes_to_bale_telegram_eitaa(): void
    {
        $bot = $this->createBot($this->token, ChannelPosterBotController::ENDPOINT_ID);
        $this->createDestination($bot->id, 'bale', 'channel-bale-1', $this->token);
        $this->createDestination($bot->id, 'telegram', 'channel-tg', '999:tg');
        $this->createDestination($bot->id, 'eitaa', 'channel-eitaa', 'eitaa-token');

        // نام واقعی فایل (podcast.mp3) با نام موقت دانلود متفاوت است تا ثابت
        // شود نام واقعی در آپلود حفظ می‌شود (نه basenameِ فایل موقت).
        $mediaPath = $this->makeTempFile('tmp-podcast-xyz.mp3', 'podcast-bytes');
        $contents = 'podcast-bytes';

        $store = Mockery::mock(ChannelPosterMediaStore::class)->makePartial();
        $store->shouldReceive('fetchAndStore')
            ->once()
            ->with($this->token, 'mp3-file-id-1', 'bale', 'audio', 'podcast.mp3')
            ->andReturn($mediaPath);
        $this->app->instance(ChannelPosterMediaStore::class, $store);

        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->realPublisherWithMockedVendor($this->token))
        );

        Http::fake([
            'tapi.bale.ai*'     => Http::response(['ok' => true, 'result' => ['message_id' => 11]]),
            'api.telegram.org*' => Http::response(['ok' => true, 'result' => ['message_id' => 22]]),
            'eitaayar.ir*'      => Http::response(['ok' => true, 'result' => ['message_id' => 33]]),
        ]);

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateDocumentUpdate($this->ownerChatId, 'mp3-file-id-1', 'podcast.mp3', 'audio/mpeg', 'کپشن پادکست')
        )->assertOk();

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->callbackUpdate('cb-mp3', 'cp:now', $this->ownerChatId)
        )->assertOk();

        Http::assertSentCount(3);

        // بله: sendAudio با نام واقعی فایل
        Http::assertSent(function (Request $request) use ($contents) {
            return $request->url() === 'https://tapi.bale.ai/bot'.$this->token.'/sendAudio'
                && $request->isMultipart()
                && $request->hasFile('audio', $contents, 'podcast.mp3')
                && ($this->multipartData($request)['chat_id'] ?? null) === 'channel-bale-1';
        });

        // تلگرام: sendAudio با نام واقعی فایل
        Http::assertSent(function (Request $request) use ($contents) {
            return $request->url() === 'https://api.telegram.org/bot999:tg/sendAudio'
                && $request->isMultipart()
                && $request->hasFile('audio', $contents, 'podcast.mp3')
                && ($this->multipartData($request)['chat_id'] ?? null) === 'channel-tg';
        });

        // ایتایار: sendFile با فیلد file
        Http::assertSent(function (Request $request) use ($contents) {
            return $request->url() === 'https://eitaayar.ir/api/eitaa-token/sendFile'
                && $request->isMultipart()
                && $request->hasFile('file', $contents, 'podcast.mp3')
                && ($this->multipartData($request)['chat_id'] ?? null) === 'channel-eitaa';
        });

        // انتشار فوری = فایل موقت پاک می‌شود + بدون ردیف صف
        $this->assertFileDoesNotExist($mediaPath);
        $this->assertSame(0, ChannelPosterQueue::count());
        $this->assertSame(0, BotUserState::count());

        $this->assertSame(3, ChannelPosterPublishLog::where('bot_id', $bot->id)->where('success', true)->count());
        $this->assertDatabaseHas('channel_poster_publish_logs', ['bot_id' => $bot->id, 'platform' => 'telegram', 'success' => true]);
        $this->assertDatabaseHas('channel_poster_publish_logs', ['bot_id' => $bot->id, 'platform' => 'bale', 'success' => true]);
        $this->assertDatabaseHas('channel_poster_publish_logs', ['bot_id' => $bot->id, 'platform' => 'eitaa', 'success' => true]);
    }

    public function test_immediate_publish_audio_cleans_up_temp_file(): void
    {
        $bot = $this->createBot($this->token, ChannelPosterBotController::ENDPOINT_ID);
        $this->createDestination($bot->id, 'telegram', 'channel-tg', '999:tg');

        $mediaPath = $this->makeTempFile('webhook-audio.mp3', 'audio-bytes');

        $store = Mockery::mock(ChannelPosterMediaStore::class)->makePartial();
        $store->shouldReceive('fetchAndStore')
            ->once()
            ->with($this->token, 'audio-file-id-7', 'bale', 'audio', 'webhook-audio.mp3')
            ->andReturn($mediaPath);
        $this->app->instance(ChannelPosterMediaStore::class, $store);

        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->realPublisherWithMockedVendor($this->token))
        );

        Http::fake([
            'api.telegram.org*' => Http::response(['ok' => true, 'result' => ['message_id' => 44]]),
        ]);

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateDocumentUpdate($this->ownerChatId, 'audio-file-id-7', 'webhook-audio.mp3', 'audio/mpeg', 'کپشن صدا')
        )->assertOk();

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->callbackUpdate('cb-audio', 'cp:now', $this->ownerChatId)
        )->assertOk();

        // فایل دانلودشده پس از انتشار فوری باید از دیسک حذف شده باشد
        $this->assertFileDoesNotExist($mediaPath);

        $this->assertDatabaseHas('channel_poster_publish_logs', [
            'bot_id' => $bot->id,
            'queue_id' => null,
            'platform' => 'telegram',
            'success' => true,
        ]);
    }

    public function test_audio_download_failure_no_fake_success(): void
    {
        $bot = $this->createBot($this->token, ChannelPosterBotController::ENDPOINT_ID);
        $this->createDestination($bot->id, 'telegram', 'channel-tg', '999:tg');

        // دانلود فایل صوتی ناموفق است (fetchAndStore → null)؛ نباید موفقیت
        // کاذب (انتشار فقط‌متن) رخ دهد.
        $store = Mockery::mock(ChannelPosterMediaStore::class)->makePartial();
        $store->shouldReceive('fetchAndStore')
            ->once()
            ->with($this->token, 'mp3-file-id-9', 'bale', 'audio', 'podcast.mp3')
            ->andReturnNull();
        $this->app->instance(ChannelPosterMediaStore::class, $store);

        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->realPublisherWithMockedVendor($this->token))
        );

        Http::fake([
            'api.telegram.org*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
            'tapi.bale.ai*'     => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateDocumentUpdate($this->ownerChatId, 'mp3-file-id-9', 'podcast.mp3', 'audio/mpeg', 'کپشن')
        )->assertOk();

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->callbackUpdate('cb-dl-fail', 'cp:now', $this->ownerChatId)
        )->assertOk();

        // انتشار باید ناموفق ثبت شود، نه موفقیت کاذب
        $this->assertDatabaseHas('channel_poster_publish_logs', [
            'bot_id' => $bot->id,
            'queue_id' => null,
            'platform' => 'telegram',
            'success' => false,
        ]);
        $this->assertSame(
            0,
            ChannelPosterPublishLog::where('bot_id', $bot->id)->where('success', true)->count(),
            'no fake success allowed when the media file is unavailable'
        );

        // هیچ درخواست رسانه‌ای (یا فقط‌متن از مسیر Laravel Http) ارسال نشده
        Http::assertSentCount(0);
    }

    public function test_unsupported_audio_document_receives_specific_error(): void
    {
        $bot = $this->createBot($this->token, ChannelPosterBotController::ENDPOINT_ID);
        $this->createDestination($bot->id, 'telegram', 'channel-tg', '999:tg');

        // قالب صوتی ناپشتیبانی‌شده (flac): باید پیام خطای مشخص بدهد، نه اینکه
        // بی‌صدا رد شود. دانلود/انتشار/صف‌بندی اصلا انجام نشود.
        $store = Mockery::mock(ChannelPosterMediaStore::class)->makePartial();
        $store->shouldReceive('fetchAndStore')->never();
        $this->app->instance(ChannelPosterMediaStore::class, $store);

        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->publisher)
        );

        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateDocumentUpdate($this->ownerChatId, 'flac-file-id', 'song.flac', 'audio/flac', 'رادیو')
        )->assertOk();

        $this->assertSame(trans('bot.channel_poster_audio_unsupported'), $this->publisher->lastPrivateText());

        $this->assertCount(0, $this->publisher->publishes);
        $this->assertSame(0, ChannelPosterQueue::count());
        $this->assertSame(0, BotUserState::count());
        Http::assertSentCount(0);
    }

    public function test_queue_audio_item_published_to_all_platforms_with_name_and_mime(): void
    {
        $bot = $this->createBot('123:queue-origin-token');
        $this->createDestination($bot->id, 'bale', 'channel-bale', '123:queue-origin-token');
        $this->createDestination($bot->id, 'telegram', 'channel-tg', '999:tg');
        $this->createDestination($bot->id, 'eitaa', 'channel-eitaa', 'eitaa-token');

        $localPath = $this->makeTempFile('song.mp3', 'song-bytes');
        $contents = 'song-bytes';

        $item = $this->createMediaQueueItem(
            $bot->id, 'tag1', $localPath, 'bale-file-1',
            'audio', 'song.mp3', 'audio/mpeg'
        );

        Http::fake([
            'tapi.bale.ai*'    => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
            'api.telegram.org*' => Http::response(['ok' => true, 'result' => ['message_id' => 2]]),
            'eitaayar.ir*'     => Http::response(['ok' => true, 'result' => ['message_id' => 3]]),
        ]);

        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])->assertSuccessful();

        $item->refresh();
        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->status);
        $this->assertNull($item->media_path);
        $this->assertFileDoesNotExist($localPath);

        Http::assertSentCount(3);

        // بله: sendAudio با نام واقعی + MIME
        Http::assertSent(function (Request $request) use ($contents) {
            return $request->url() === 'https://tapi.bale.ai/bot123:queue-origin-token/sendAudio'
                && $request->isMultipart()
                && $request->hasFile('audio', $contents, 'song.mp3');
        });

        // تلگرام: sendAudio
        Http::assertSent(function (Request $request) use ($contents) {
            return $request->url() === 'https://api.telegram.org/bot999:tg/sendAudio'
                && $request->isMultipart()
                && $request->hasFile('audio', $contents, 'song.mp3');
        });

        // ایتایار: sendFile
        Http::assertSent(function (Request $request) use ($contents) {
            return $request->url() === 'https://eitaayar.ir/api/eitaa-token/sendFile'
                && $request->isMultipart()
                && $request->hasFile('file', $contents, 'song.mp3');
        });

        $this->assertSame(3, ChannelPosterPublishLog::where('queue_id', $item->id)->where('success', true)->count());
    }

    public function test_queue_audio_retry_does_not_republish_successful_destination(): void
    {
        $bot = $this->createBot('123:queue-origin-token');
        $this->createDestination($bot->id, 'bale', 'channel-bale', '123:queue-origin-token');
        $this->createDestination($bot->id, 'telegram', 'channel-tg', '999:tg');

        $localPath = $this->makeTempFile('retry-audio.mp3', 'retry-audio-bytes');

        $item = $this->createMediaQueueItem(
            $bot->id, 'tag1', $localPath, 'bale-file-1',
            'audio', 'retry-audio.mp3', 'audio/mpeg'
        );

        Http::fake([
            'api.telegram.org*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]]),
            'tapi.bale.ai*' => Http::sequence()
                ->push(['ok' => false, 'description' => 'bale down'], 400) // run 1: multipart
                ->push(['ok' => false, 'description' => 'bale down'], 400) // run 1: file_id fallback (bale = origin)
                ->push(['ok' => true, 'result' => ['message_id' => 55]]),    // run 2: multipart
        ]);

        // run 1: بله می‌شکند (آپلود + فالبک file_id)، تلگرام موفق
        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])->assertFailed();
        $this->assertSame(ChannelPosterQueue::STATUS_FAILED, $item->refresh()->status);

        // retry: وضعیت را برمی‌گردانیم
        $item->update(['status' => ChannelPosterQueue::STATUS_PENDING]);

        // run 2: بله تکرار و موفق؛ تلگرام باید SKIP شود
        $this->artisan('channel-poster:send-queued', ['--id' => $item->id])->assertSuccessful();

        $item->refresh();
        $this->assertSame(ChannelPosterQueue::STATUS_PUBLISHED, $item->status);
        $this->assertNull($item->media_path);
        $this->assertFileDoesNotExist($localPath);

        // تلگرام فقط یک‌بار (run 1) ارسال شده — در run 2 تکرار نشده
        $telegramRequests = Http::recorded(fn (Request $request) => str_contains($request->url(), 'api.telegram.org'));
        $this->assertCount(1, $telegramRequests);

        // بله: 2 شکست در run 1 + 1 موفق در run 2
        $baleRequests = Http::recorded(fn (Request $request) => str_contains($request->url(), 'tapi.bale.ai'));
        $this->assertCount(3, $baleRequests);

        $this->assertSame(1, ChannelPosterPublishLog::where('queue_id', $item->id)
            ->where('platform', 'telegram')->where('success', true)->count());
        $this->assertSame(1, ChannelPosterPublishLog::where('queue_id', $item->id)
            ->where('platform', 'bale')->where('success', true)->count());
        $this->assertSame(2, ChannelPosterPublishLog::where('queue_id', $item->id)
            ->where('platform', 'bale')->count());
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4) MediaStore: delete / pruneOrphans / diagnose
    // ─────────────────────────────────────────────────────────────────────

    public function test_media_store_delete_is_idempotent(): void
    {
        $store = app(ChannelPosterMediaStore::class);

        $store->delete(null);
        $store->delete('');
        $store->delete(storage_path('app/nonexistent-channel-poster-file.jpg'));

        $path = $this->makeTempFile('idempotent-delete.jpg', 'bytes');
        $this->assertFileExists($path);

        $store->delete($path);
        $this->assertFileDoesNotExist($path);

        $store->delete($path); // باید بی‌خطا باشد
    }

    public function test_prune_orphans_removes_only_unreferenced_files(): void
    {
        $bot = $this->createBot('123:prune-token');

        $keep = $this->makeMediaDirFile('cpm-test-keep.jpg', 'keep-bytes');
        $orphan = $this->makeMediaDirFile('cpm-test-orphan.jpg', 'orphan-bytes');

        ChannelPosterQueue::create([
            'bot_id' => $bot->id,
            'tag' => 'tag1',
            'content_type' => 'photo',
            'text' => 'referenced item',
            'media_path' => $keep,
            'scheduled_at' => now()->addHour(),
            'status' => ChannelPosterQueue::STATUS_PENDING,
            'owner_origin' => 'bale',
        ]);

        $removed = app(ChannelPosterMediaStore::class)->pruneOrphans();

        $this->assertSame(1, $removed);
        $this->assertFileExists($keep);
        $this->assertFileDoesNotExist($orphan);
    }

    public function test_diagnose_reports_media_folder_stats(): void
    {
        $bot = $this->createBot('123:diag-token');

        $keep = $this->makeMediaDirFile('cpm-diag-keep.jpg', 'keep-bytes');
        $orphan = $this->makeMediaDirFile('cpm-diag-orphan.jpg', 'orphan-bytes');

        ChannelPosterQueue::create([
            'bot_id' => $bot->id,
            'tag' => 'tag1',
            'content_type' => 'photo',
            'text' => 'referenced item',
            'media_path' => $keep,
            'scheduled_at' => now()->addHour(),
            'status' => ChannelPosterQueue::STATUS_PENDING,
            'owner_origin' => 'bale',
        ]);

        $stats = app(ChannelPosterMediaStore::class)->diagnose();

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['referenced']);
        $this->assertSame(1, $stats['orphan']);
        $this->assertSame(0, $stats['stale']);
        $this->assertSame(strlen('keep-bytes') + strlen('orphan-bytes'), $stats['bytes']);

        $statusByName = collect($stats['files'])
            ->mapWithKeys(fn (array $file) => [$file['name'] => $file['status']])
            ->all();

        $this->assertSame('referenced', $statusByName['cpm-diag-keep.jpg']);
        $this->assertSame('orphan', $statusByName['cpm-diag-orphan.jpg']);
    }

    public function test_diagnose_command_runs_read_only(): void
    {
        $bot = $this->createBot('123:diag-token');
        $this->createDestination($bot->id, 'telegram', 'channel-tg');
        $this->makeMediaDirFile('cpm-diag-cmd.jpg', 'cmd-bytes');

        $this->artisan('channel-poster:diagnose')
            ->assertSuccessful()
            ->expectsOutputToContain('Media folder')
            ->expectsOutputToContain('read-only');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function realPublisher(string $originToken): ChannelPosterPublisher
    {
        return app(TelegramChannelPosterPublisherFactory::class)->make($originToken, 'bale');
    }

    /**
     * Publisher واقعی (logic واقعی publish + آپلود multipart با Http) ولی
     * کلاینت vendor (Telegram) ماک شده تا answerCallback/sendMessage به شبکه
     * واقعی نزنند (تست‌های وب‌ه‌ک).
     */
    private function realPublisherWithMockedVendor(string $originToken): TelegramChannelPosterPublisher
    {
        $bot = Mockery::mock(Telegram::class);
        $bot->shouldReceive('token')->andReturn($originToken);
        $bot->shouldReceive('sendMessage')->andReturn(['ok' => true, 'result' => ['message_id' => 1]]);
        $bot->shouldReceive('answerCallbackQuery')->andReturn(['ok' => true]);

        return new TelegramChannelPosterPublisher($bot, 'bale', app(ChannelPosterMediaSender::class));
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

    private function makeMediaDirFile(string $name, string $contents): string
    {
        $dir = storage_path('app/channel-poster-media');
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            $this->fail('could not create media directory');
        }

        $path = $dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * تبدیل data() multipart (لیست entry‌ها) به آرایه‌ی name => contents
     *
     * @return array<string, mixed>
     */
    private function multipartData(Request $request): array
    {
        return collect($request->data())
            ->mapWithKeys(fn (array $entry) => [$entry['name'] => $entry['contents']])
            ->all();
    }

    private function createBot(string $token, string $endpoint = 'channel_poster_bot'): Bot
    {
        return Bot::create([
            'endpoint_id' => $endpoint,
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'bale_bot_name' => 'channel_poster_bot',
            'bale_bot_token' => $token,
            'bale_bot_status' => 'Active',
            'bale_owner_chat_id' => (int) $this->ownerChatId,
            'bale_webhook_is_set' => true,
        ]);
    }

    private function createDestination(
        int $botId,
        string $platform,
        string $chatId,
        ?string $botToken = null,
        ?string $tag = 'tag1'
    ): ChannelPosterDestination {
        return ChannelPosterDestination::create([
            'bot_id' => $botId,
            'platform' => $platform,
            'channel_chat_id' => $chatId,
            'channel_title' => 'Test Channel',
            'tag' => $tag,
            'bot_token' => $botToken,
            'is_active' => true,
            'verified_at' => now(),
        ]);
    }

    private function createMediaQueueItem(
        int $botId,
        string $tag,
        string $mediaPath,
        string $fileId,
        string $contentType = 'photo',
        ?string $fileName = null,
        ?string $mimeType = null
    ): ChannelPosterQueue {
        return ChannelPosterQueue::create([
            'bot_id' => $botId,
            'tag' => $tag,
            'content_type' => $contentType,
            'text' => 'کپشن صف',
            'file_id' => $fileId,
            'media_path' => $mediaPath,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'scheduled_at' => now()->subHour(),
            'status' => ChannelPosterQueue::STATUS_PENDING,
            // owner_chat_id خالی = بدون اطلاع‌رسانی به مالک (vendor curl در تست اجرا نشود)
            'owner_chat_id' => null,
            'owner_origin' => 'bale',
        ]);
    }

    private function privatePhotoUpdate(string $chatId, string $fileId, string $caption): array
    {
        return [
            'update_id' => 3,
            'message' => [
                'message_id' => 20,
                'from' => ['id' => (int) $chatId, 'is_bot' => false],
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'date' => time(),
                'photo' => [
                    [
                        'file_id' => 'smallest-photo-id',
                        'file_unique_id' => 'u1',
                        'width' => 90,
                        'height' => 90,
                        'file_size' => 1000,
                    ],
                    [
                        'file_id' => $fileId,
                        'file_unique_id' => 'u2',
                        'width' => 800,
                        'height' => 600,
                        'file_size' => 90000,
                    ],
                ],
                'caption' => $caption,
            ],
        ];
    }

    private function privateDocumentUpdate(string $chatId, string $fileId, ?string $fileName, ?string $mimeType, ?string $caption = null): array
    {
        $doc = ['file_id' => $fileId, 'file_unique_id' => 'du1'];
        if ($fileName !== null) {
            $doc['file_name'] = $fileName;
        }
        if ($mimeType !== null) {
            $doc['mime_type'] = $mimeType;
        }

        $message = [
            'message_id' => 20,
            'from' => ['id' => (int) $chatId, 'is_bot' => false],
            'chat' => ['id' => (int) $chatId, 'type' => 'private'],
            'date' => time(),
            'document' => $doc,
        ];
        if ($caption !== null) {
            $message['caption'] = $caption;
        }

        return ['update_id' => 3, 'message' => $message];
    }

    private function callbackUpdate(string $id, string $data, string $chatId): array
    {
        return [
            'update_id' => 5,
            'callback_query' => [
                'id' => $id,
                'from' => ['id' => (int) $chatId],
                'data' => $data,
                'message' => [
                    'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                ],
            ],
        ];
    }
}
