<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiJob;
use App\Models\AiJob;
use App\Models\Book;
use App\Models\BookDraft;
use App\Models\BookPage;
use App\Models\BookPageScan;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Services\Ai\AiFeatureGate;
use App\Services\BookPixel\BookPixelOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * WS-F — OCR MVP in Book Pixel.
 *
 * Contract:
 *  - Webhook scan photo (state=waiting_scan) + gate open  → dispatch ocr job.
 *  - Gate closed                                          → normal flow intact, no job.
 *  - Executor: platform getFile → download → VisionAdapter → persist ocr_text.
 *  - Provider 500/404, file-get failure, empty vision text → retryable (no fake output).
 *  - Time policy (Asia/Tehran 09–14): regular deferred until 14:00 flush; Pro 24h.
 *  - Idempotency: duplicate trigger → one job.
 *
 * NOTE: vendor Telegram class reads php://input in its constructor which is
 * empty under PHPUnit; the controller compensates via $bot->setData($update).
 */
class BookPixelOcrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        AiFeatureGate::clearHealthCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function flags(array $on = []): void
    {
        Config::set('ai.flags', [
            'ai_global_enabled'           => true,
            'ai_llm_enabled'              => in_array('llm', $on, true),
            'ai_ocr_enabled'              => in_array('ocr', $on, true),
            'ai_asr_enabled'              => in_array('asr', $on, true),
            'ai_rag_enabled'              => in_array('rag', $on, true),
            'ai_tts_enabled'              => in_array('tts', $on, true),
            'ai_growth_companion_enabled' => in_array('growth_companion', $on, true),
        ]);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | 1–2. Webhook integration (gate open/closed)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_webhook_scan_photo_dispatches_ocr_job_when_gate_open(): void
    {
        // 15:00 Tehran = 11:30 UTC — خارج پنجره deferred
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));
        $this->flags(['ocr']);

        $fixture = $this->makeScanFixture('telegram');
        $this->setWaitingScanState($fixture['botUser'], $fixture['book']->id, 5);

        $this->postJson(
            '/api/webhook-book-pixel?origin=telegram&token=' . $fixture['bot']->telegram_bot_token
                . '&bot_mother_id=1&bot_id=' . $fixture['bot']->id,
            $this->photoUpdate('SCAN_FILE_A', (int) $fixture['botUser']->chat_id)
        )->assertStatus(200);

        // اسکن صفحه ایجاد شده
        $scan = BookPageScan::where('file_id', 'SCAN_FILE_A')->first();
        $this->assertNotNull($scan, 'scan باید در webhook ایجاد شود');
        $this->assertSame('pending_approval', $scan->status);
        $this->assertNotNull($scan->book_page_id);

        // draft ذخیره شده
        $this->assertDatabaseHas('book_drafts', [
            'file_id' => 'SCAN_FILE_A',
            'type'    => 'scan',
        ]);

        // state پاک شده
        $this->assertSame(0, BotUserState::where('bot_user_id', $fixture['botUser']->id)->count());

        // ocr job ساخته و در صف رفته
        $job = AiJob::where('feature', 'ocr')->first();
        $this->assertNotNull($job, 'ocr job باید ساخته شود');
        $this->assertSame(AiJob::STATE_QUEUED, $job->state);
        $this->assertSame($scan->id, $job->payload['scan_id']);
        $this->assertSame('telegram', $job->payload['origin']);
        $this->assertSame($fixture['bot']->id, $job->payload['bot_id']);
        $this->assertSame($fixture['bot']->id, $job->bot_id);

        Queue::assertPushed(ProcessAiJob::class, fn (ProcessAiJob $j) => $j->jobId === $job->job_id);
    }

    public function test_webhook_scan_photo_flow_intact_when_ocr_gate_closed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));
        $this->flags(); // همه flagها خاموش

        $fixture = $this->makeScanFixture('telegram');
        $this->setWaitingScanState($fixture['botUser'], $fixture['book']->id, 5);

        $this->postJson(
            '/api/webhook-book-pixel?origin=telegram&token=' . $fixture['bot']->telegram_bot_token
                . '&bot_mother_id=1&bot_id=' . $fixture['bot']->id,
            $this->photoUpdate('SCAN_FILE_B', (int) $fixture['botUser']->chat_id)
        )->assertStatus(200);

        // جریان عادی اسکن دست‌نخورده می‌ماند
        $scan = BookPageScan::where('file_id', 'SCAN_FILE_B')->first();
        $this->assertNotNull($scan, 'scan بدون gate هم باید ایجاد شود');
        $this->assertSame(0, BotUserState::where('bot_user_id', $fixture['botUser']->id)->count());

        // ولی هیچ ocr job ساخته نمی‌شود
        $this->assertSame(0, AiJob::where('feature', 'ocr')->count());
        Queue::assertNotPushed(ProcessAiJob::class);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | 3–7, 11. Executor (ProcessAiJob → BookPixelOcrService::execute)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_executor_persists_ocr_text_on_success(): void
    {
        $this->flags(['ocr']);
        $fixture = $this->makeScanFixture('telegram');
        $job = $this->makeOcrJob($fixture['scan']);

        $this->fakePlatformAndVision(200, "بسم الله الرحمن الرحیم\nسطر دوم");

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_COMPLETED, $job->state);
        $this->assertSame('scan:' . $fixture['scan']->id, $job->result_reference);
        $this->assertNull($job->error_code);

        $scan = $fixture['scan']->refresh();
        $this->assertSame("بسم الله الرحمن الرحیم\nسطر دوم", $scan->ocr_text);
        $this->assertNull($scan->ocr_error);
        $this->assertNotNull($scan->ocr_at);
    }

    public function test_executor_retries_on_provider_500(): void
    {
        $this->flags(['ocr']);
        $fixture = $this->makeScanFixture('telegram');
        $job = $this->makeOcrJob($fixture['scan']);

        $this->fakePlatformAndVision(500, '', true, 'Internal Server Error');

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('provider_error', $job->error_code);
        $this->assertNotNull($job->due_at);
        $this->assertTrue($job->due_at->isFuture());

        $scan = $fixture['scan']->refresh();
        $this->assertNull($scan->ocr_text, 'در خطا متن جعلی ذخیره نشود');
        $this->assertSame('provider_error', $scan->ocr_error);
    }

    public function test_executor_classifies_404_as_model_not_found(): void
    {
        $this->flags(['ocr']);
        $fixture = $this->makeScanFixture('telegram');
        $job = $this->makeOcrJob($fixture['scan']);

        $this->fakePlatformAndVision(404, '', true, 'Not Found');

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('model_not_found', $job->error_code);
        $this->assertNull($fixture['scan']->refresh()->ocr_text);
    }

    public function test_executor_retries_when_file_get_fails(): void
    {
        $this->flags(['ocr']);
        $fixture = $this->makeScanFixture('telegram');
        $job = $this->makeOcrJob($fixture['scan']);

        $this->fakePlatformAndVision(200, 'متن', false);

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('file_get_failed', $job->error_code);

        $scan = $fixture['scan']->refresh();
        $this->assertNull($scan->ocr_text);
        $this->assertSame('file_get_failed', $scan->ocr_error);
    }

    public function test_executor_persists_error_when_download_fails(): void
    {
        $this->flags(['ocr']);
        $fixture = $this->makeScanFixture('telegram');
        $job = $this->makeOcrJob($fixture['scan']);

        Http::fake([
            '*api.telegram.org/bot*/getFile*'    => Http::response(['ok' => true, 'result' => ['file_path' => '/files/scan.jpg']], 200),
            '*api.telegram.org/file/bot*'        => Http::response('err', 404),
            '*ai.ismc.ir/api/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'X']]]], 200),
            '*'                                  => Http::response(['ok' => false], 400),
        ]);

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('file_download_failed', $job->error_code);
        $this->assertNull($fixture['scan']->refresh()->ocr_text);
    }

    public function test_empty_vision_content_is_empty_result_not_fake_text(): void
    {
        $this->flags(['ocr']);
        $fixture = $this->makeScanFixture('telegram');
        $job = $this->makeOcrJob($fixture['scan']);

        $this->fakePlatformAndVision(200, '   ');

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('empty_result', $job->error_code);

        $scan = $fixture['scan']->refresh();
        $this->assertNull($scan->ocr_text, 'خروجی خالی Vision نباید به‌عنوان متن ذخیره شود');
        $this->assertSame('empty_result', $scan->ocr_error);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | 8–10. Trigger service (gate + time policy + idempotency)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_pro_user_in_deferred_window_is_immediate(): void
    {
        // 10:00 Tehran = 06:30 UTC — داخل پنجره 09–14
        Carbon::setTestNow(Carbon::parse('2026-11-14 06:30:00', 'UTC'));
        $this->flags(['ocr']);

        $fixture = $this->makeScanFixture('telegram');

        $result = app(BookPixelOcrService::class)->triggerForScan($fixture['scan'], 'telegram', true);

        $this->assertTrue($result['dispatched']);
        $this->assertNotNull($result['job']);
        $this->assertSame(AiJob::STATE_QUEUED, $result['job']->state);
        $this->assertSame(AiJob::PRIORITY_PRO, $result['job']->priority);
        $this->assertNull($result['job']->due_at);
        Queue::assertPushed(ProcessAiJob::class, fn (ProcessAiJob $j) => $j->jobId === $result['job']->job_id);
    }

    public function test_regular_user_in_deferred_window_is_deferred_until_14_tehran(): void
    {
        // 10:00 Tehran = 06:30 UTC — داخل پنجره 09–14
        Carbon::setTestNow(Carbon::parse('2026-11-14 06:30:00', 'UTC'));
        $this->flags(['ocr']);

        $fixture = $this->makeScanFixture('telegram');

        $result = app(BookPixelOcrService::class)->triggerForScan($fixture['scan'], 'telegram', false);

        $this->assertTrue($result['dispatched']);
        $this->assertSame('deferred', $result['reason']);
        $this->assertSame(AiJob::STATE_DEFERRED, $result['job']->state);
        $this->assertSame(AiJob::PRIORITY_DEFERRED, $result['job']->priority);
        // flush: 14:00 Tehran = 10:30 UTC
        $this->assertSame('2026-11-14 10:30:00', $result['job']->due_at->format('Y-m-d H:i:s'));
        Queue::assertNotPushed(ProcessAiJob::class);
    }

    public function test_duplicate_trigger_creates_only_one_job(): void
    {
        // 15:00 Tehran = 11:30 UTC — خارج پنجره
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));
        $this->flags(['ocr']);

        $fixture = $this->makeScanFixture('telegram');
        $service = app(BookPixelOcrService::class);

        $first  = $service->triggerForScan($fixture['scan'], 'telegram', false);
        $second = $service->triggerForScan($fixture['scan'], 'telegram', false);

        $this->assertTrue($first['dispatched']);
        $this->assertTrue($second['dispatched']);
        $this->assertSame($first['job']->id, $second['job']->id, 'trigger تکراری نباید job دوم بسازد');
        $this->assertSame(1, AiJob::where('feature', 'ocr')->count());
        Queue::assertPushed(ProcessAiJob::class, 1);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | Fixtures / fakes
    |──────────────────────────────────────────────────────────────────────
    */

    /**
     * Bot + BotUsers + Book + BookPage + BookPageScan آماده.
     *
     * @return array{bot: Bot, botUser: BotUsers, book: Book, page: BookPage, scan: BookPageScan}
     */
    private function makeScanFixture(string $origin = 'telegram'): array
    {
        $token = $origin === 'bale' ? '65432:BALE_TOKEN' : '12345:OCR_TOKEN';
        $name  = 'ocr-' . $origin . '-' . uniqid();

        $bot = Bot::create([
            'telegram_bot_name' => $origin === 'telegram' ? $name : null,
            'bale_bot_name'     => $origin === 'bale' ? $name : null,
            'telegram_bot_token' => $origin === 'telegram' ? $token : null,
            'bale_bot_token'    => $origin === 'bale' ? $token : null,
        ]);

        $botUser = BotUsers::create([
            'chat_id' => 900000000 + random_int(1, 999999),
            'bot_id'  => $bot->id,
            'origin'  => $origin,
            'status'  => 'active',
        ]);

        $book = Book::create(['name' => 'کتاب OCR ' . uniqid(), 'bot_id' => $bot->id]);
        $page = BookPage::create(['book_id' => $book->id, 'page_number' => 1]);

        $scan = BookPageScan::create([
            'book_page_id'   => $page->id,
            'user_id'        => $botUser->id,
            'bot_id'         => $bot->id,
            'page_number'    => 1,
            'file_id'        => 'OCR_FILE_' . uniqid(),
            'status'         => 'pending_approval',
            'points_awarded' => 0,
        ]);

        return ['bot' => $bot, 'botUser' => $botUser, 'book' => $book, 'page' => $page, 'scan' => $scan];
    }

    private function setWaitingScanState(BotUsers $botUser, int $bookId, int $pageNumber): void
    {
        BotUserState::create([
            'bot_user_id'    => $botUser->id,
            'bot_mother_id'  => 1,
            'state'          => 'waiting_scan',
            'data'           => ['book_id' => $bookId, 'page_number' => $pageNumber],
            'expires_at'     => now()->addHours(2),
        ]);
    }

    private function makeOcrJob(BookPageScan $scan): AiJob
    {
        return AiJob::create([
            'job_id'          => 'ocr-job-' . uniqid(),
            'idempotency_key' => 'ocr-key-' . uniqid(),
            'bot_id'          => $scan->bot_id,
            'user_id'         => $scan->user_id,
            'feature'         => 'ocr',
            'priority'        => AiJob::PRIORITY_REGULAR,
            'state'           => AiJob::STATE_QUEUED,
            'provider'        => 'litellm',
            'model'           => 'qwen38',
            'payload'         => [
                'scan_id' => $scan->id,
                'file_id' => $scan->file_id,
                'origin'  => 'telegram',
                'bot_id'  => $scan->bot_id,
            ],
        ]);
    }

    /**
     * Fake برای API پلتفرم (getFile + download) و Vision.
     *
     * @param int    $visionStatus 200|404|500
     * @param string $visionContent محتوای استخراج‌شده (content)
     * @param bool   $fileOk       ok بودن getFile
     */
    private function fakePlatformAndVision(int $visionStatus, string $visionContent, bool $fileOk = true, string $visionBody = ''): void
    {
        $vision = $visionStatus === 200
            ? Http::response([
                'choices' => [['message' => ['content' => $visionContent]]],
                'usage'   => ['total_tokens' => 42],
            ], 200)
            : Http::response($visionBody !== '' ? $visionBody : ['error' => 'fail'], $visionStatus);

        Http::fake([
            '*api.telegram.org/bot*/getFile*'  => $fileOk
                ? Http::response(['ok' => true, 'result' => ['file_path' => '/files/scan.jpg']], 200)
                : Http::response(['ok' => false, 'error_code' => 40001, 'description' => 'Bad Request: file not found'], 400),
            '*api.telegram.org/file/bot*'      => Http::response('JPEGBINARYDATA', 200),
            '*ai.ismc.ir/api/v1/chat/completions' => $vision,
            '*'                                => Http::response(['ok' => false], 400),
        ]);
    }

    private function photoUpdate(string $fileId, int $chatId): array
    {
        return [
            'message' => [
                'message_id'   => 100,
                'chat'         => ['id' => $chatId, 'first_name' => 'Test', 'type' => 'private'],
                'date'         => 1763100000,
                'photo'        => [
                    [
                        'file_id'         => $fileId,
                        'file_unique_id'  => $fileId . '_small',
                        'file_size'       => 100,
                        'width'           => 100,
                        'height'          => 100,
                    ],
                    [
                        'file_id'         => $fileId,
                        'file_unique_id'  => $fileId . '_large',
                        'file_size'       => 1000,
                        'width'           => 1000,
                        'height'          => 1000,
                    ],
                ],
            ],
        ];
    }
}
