<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiJob;
use App\Models\AiJob;
use App\Models\Book;
use App\Models\BookPage;
use App\Models\BookPageScan;
use App\Models\BookPageVoice;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Services\Ai\AiFeatureGate;
use App\Services\BookPixel\BookPixelAsrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * WS-G — ASR MVP in Book Pixel.
 *
 * Contract:
 *  - Webhook voice (state scan_id) + gate open  → dispatch asr job.
 *  - Gate closed                                → normal flow intact, no job.
 *  - Executor: platform getFile → download → TranscriptionAdapter → persist asr_text.
 *  - Provider 500 / 404-405 (endpoint_missing), file-get failure, empty text → retryable (no fake output).
 *  - Time policy (Asia/Tehran 09–14): regular deferred until 14:00 flush; Pro 24h.
 *  - Idempotency: duplicate trigger → one job.
 */
class BookPixelAsrTest extends TestCase
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

    public function test_webhook_voice_dispatches_asr_job_when_gate_open(): void
    {
        // 15:00 Tehran = 11:30 UTC — خارج پنجره deferred
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));
        $this->flags(['asr']);

        $fixture = $this->makeVoiceFixture('telegram');
        $this->setVoiceState($fixture['botUser'], $fixture['scan']->id);

        $this->postJson(
            '/api/webhook-book-pixel?origin=telegram&token=' . $fixture['bot']->telegram_bot_token
                . '&bot_mother_id=1&bot_id=' . $fixture['bot']->id,
            $this->voiceUpdate('VOICE_FILE_A', (int) $fixture['botUser']->chat_id)
        )->assertStatus(200);

        // voice record ایجاد شده
        $voice = BookPageVoice::where('file_id', 'VOICE_FILE_A')->first();
        $this->assertNotNull($voice, 'voice record باید در webhook ایجاد شود');
        $this->assertSame($fixture['scan']->id, $voice->book_page_scan_id);

        // state پاک شده
        $this->assertSame(0, BotUserState::where('bot_user_id', $fixture['botUser']->id)->count());

        // asr job ساخته و در صف رفته
        $job = AiJob::where('feature', 'asr')->first();
        $this->assertNotNull($job, 'asr job باید ساخته شود');
        $this->assertSame(AiJob::STATE_QUEUED, $job->state);
        $this->assertSame($voice->id, $job->payload['voice_id']);
        $this->assertSame('telegram', $job->payload['origin']);
        $this->assertSame($fixture['bot']->id, $job->payload['bot_id']);
        $this->assertSame($fixture['bot']->id, $job->bot_id);

        Queue::assertPushed(ProcessAiJob::class, fn (ProcessAiJob $j) => $j->jobId === $job->job_id);
    }

    public function test_webhook_voice_flow_intact_when_asr_gate_closed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 11:30:00', 'UTC'));
        $this->flags(); // همه flagها خاموش

        $fixture = $this->makeVoiceFixture('telegram');
        $this->setVoiceState($fixture['botUser'], $fixture['scan']->id);

        $this->postJson(
            '/api/webhook-book-pixel?origin=telegram&token=' . $fixture['bot']->telegram_bot_token
                . '&bot_mother_id=1&bot_id=' . $fixture['bot']->id,
            $this->voiceUpdate('VOICE_FILE_B', (int) $fixture['botUser']->chat_id)
        )->assertStatus(200);

        // جریان عادی voice دست‌نخورده می‌ماند
        $this->assertNotNull(BookPageVoice::where('file_id', 'VOICE_FILE_B')->first(), 'voice بدون gate هم باید ایجاد شود');
        $this->assertSame(0, BotUserState::where('bot_user_id', $fixture['botUser']->id)->count());

        // ولی هیچ asr job ساخته نمی‌شود
        $this->assertSame(0, AiJob::where('feature', 'asr')->count());
        Queue::assertNotPushed(ProcessAiJob::class);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | 3–7. Executor (ProcessAiJob → BookPixelAsrService::execute)
    |──────────────────────────────────────────────────────────────────────
    */

    public function test_executor_persists_asr_text_on_success(): void
    {
        $this->flags(['asr']);
        $fixture = $this->makeVoiceFixture('telegram');
        $job = $this->makeAsrJob($fixture['voice']);

        $this->fakePlatformAndAsr(200, 'سلام، این صفحه‌ی اول کتاب است.');

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_COMPLETED, $job->state);
        $this->assertSame('voice:' . $fixture['voice']->id, $job->result_reference);
        $this->assertNull($job->error_code);

        $voice = $fixture['voice']->refresh();
        $this->assertSame('سلام، این صفحه‌ی اول کتاب است.', $voice->asr_text);
        $this->assertNull($voice->asr_error);
        $this->assertNotNull($voice->asr_at);
    }

    public function test_executor_retries_on_provider_500(): void
    {
        $this->flags(['asr']);
        $fixture = $this->makeVoiceFixture('telegram');
        $job = $this->makeAsrJob($fixture['voice']);

        $this->fakePlatformAndAsr(500, '', true, 'Internal Server Error');

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('provider_error', $job->error_code);
        $this->assertNotNull($job->due_at);
        $this->assertTrue($job->due_at->isFuture());

        $voice = $fixture['voice']->refresh();
        $this->assertNull($voice->asr_text, 'در خطا متن جعلی ذخیره نشود');
        $this->assertSame('provider_error', $voice->asr_error);
    }

    public function test_executor_classifies_404_as_endpoint_missing(): void
    {
        $this->flags(['asr']);
        $fixture = $this->makeVoiceFixture('telegram');
        $job = $this->makeAsrJob($fixture['voice']);

        $this->fakePlatformAndAsr(404, '', true, 'Not Found');

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('endpoint_missing', $job->error_code);
        $this->assertNull($fixture['voice']->refresh()->asr_text);
    }

    public function test_executor_retries_when_file_get_fails(): void
    {
        $this->flags(['asr']);
        $fixture = $this->makeVoiceFixture('telegram');
        $job = $this->makeAsrJob($fixture['voice']);

        $this->fakePlatformAndAsr(200, 'متن', false);

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('file_get_failed', $job->error_code);

        $voice = $fixture['voice']->refresh();
        $this->assertNull($voice->asr_text);
        $this->assertSame('file_get_failed', $voice->asr_error);
    }

    public function test_executor_persists_error_when_download_fails(): void
    {
        $this->flags(['asr']);
        $fixture = $this->makeVoiceFixture('telegram');
        $job = $this->makeAsrJob($fixture['voice']);

        Http::fake([
            '*api.telegram.org/bot*/getFile*' => Http::response(['ok' => true, 'result' => ['file_path' => '/files/voice.ogg']], 200),
            '*api.telegram.org/file/bot*'     => Http::response('err', 404),
            '*ai.ismc.ir/api/v1/audio/transcriptions' => Http::response(['text' => 'X'], 200),
            '*'                               => Http::response(['ok' => false], 400),
        ]);

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('file_download_failed', $job->error_code);
        $this->assertNull($fixture['voice']->refresh()->asr_text);
    }

    public function test_empty_asr_content_is_empty_result_not_fake_text(): void
    {
        $this->flags(['asr']);
        $fixture = $this->makeVoiceFixture('telegram');
        $job = $this->makeAsrJob($fixture['voice']);

        $this->fakePlatformAndAsr(200, '   ');

        (new ProcessAiJob($job->job_id))->handle();

        $job->refresh();
        $this->assertSame(AiJob::STATE_RETRYING, $job->state);
        $this->assertSame('empty_result', $job->error_code);

        $voice = $fixture['voice']->refresh();
        $this->assertNull($voice->asr_text, 'خروجی خالی ASR نباید به‌عنوان متن ذخیره شود');
        $this->assertSame('empty_result', $voice->asr_error);
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
        $this->flags(['asr']);

        $fixture = $this->makeVoiceFixture('telegram');

        $result = app(BookPixelAsrService::class)->triggerForVoice($fixture['voice'], 'telegram', true);

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
        $this->flags(['asr']);

        $fixture = $this->makeVoiceFixture('telegram');

        $result = app(BookPixelAsrService::class)->triggerForVoice($fixture['voice'], 'telegram', false);

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
        $this->flags(['asr']);

        $fixture = $this->makeVoiceFixture('telegram');
        $service = app(BookPixelAsrService::class);

        $first  = $service->triggerForVoice($fixture['voice'], 'telegram', false);
        $second = $service->triggerForVoice($fixture['voice'], 'telegram', false);

        $this->assertTrue($first['dispatched']);
        $this->assertTrue($second['dispatched']);
        $this->assertSame($first['job']->id, $second['job']->id, 'trigger تکراری نباید job دوم بسازد');
        $this->assertSame(1, AiJob::where('feature', 'asr')->count());
        Queue::assertPushed(ProcessAiJob::class, 1);
    }

    /*
    |──────────────────────────────────────────────────────────────────────
    | Fixtures / fakes
    |──────────────────────────────────────────────────────────────────────
    */

    /**
     * Bot + BotUsers + Book + BookPage + BookPageScan + BookPageVoice آماده.
     *
     * @return array{bot: Bot, botUser: BotUsers, book: Book, page: BookPage, scan: BookPageScan, voice: BookPageVoice}
     */
    private function makeVoiceFixture(string $origin = 'telegram'): array
    {
        $token = $origin === 'bale' ? '65432:BALE_TOKEN' : '12345:ASR_TOKEN';
        $name  = 'asr-' . $origin . '-' . uniqid();

        $bot = Bot::create([
            'telegram_bot_name'  => $origin === 'telegram' ? $name : null,
            'bale_bot_name'      => $origin === 'bale' ? $name : null,
            'telegram_bot_token' => $origin === 'telegram' ? $token : null,
            'bale_bot_token'     => $origin === 'bale' ? $token : null,
        ]);

        $botUser = BotUsers::create([
            'chat_id' => 900000000 + random_int(1, 999999),
            'bot_id'  => $bot->id,
            'origin'  => $origin,
            'status'  => 'active',
        ]);

        $book = Book::create(['name' => 'کتاب ASR ' . uniqid(), 'bot_id' => $bot->id]);
        $page = BookPage::create(['book_id' => $book->id, 'page_number' => 1]);

        $scan = BookPageScan::create([
            'book_page_id'   => $page->id,
            'user_id'        => $botUser->id,
            'bot_id'         => $bot->id,
            'page_number'    => 1,
            'file_id'        => 'ASR_SCAN_' . uniqid(),
            'status'         => 'pending_approval',
            'points_awarded' => 0,
        ]);

        $voice = BookPageVoice::create([
            'book_page_scan_id' => $scan->id,
            'user_id'           => $botUser->id,
            'bot_id'            => $bot->id,
            'file_id'           => 'ASR_VOICE_' . uniqid(),
            'points_awarded'    => 0,
        ]);

        return ['bot' => $bot, 'botUser' => $botUser, 'book' => $book, 'page' => $page, 'scan' => $scan, 'voice' => $voice];
    }

    private function setVoiceState(BotUsers $botUser, int $scanId): void
    {
        BotUserState::create([
            'bot_user_id'   => $botUser->id,
            'bot_mother_id' => 1,
            'state'         => 'sending_voice',
            'data'          => ['scan_id' => $scanId],
            'expires_at'    => now()->addHours(2),
        ]);
    }

    private function makeAsrJob(BookPageVoice $voice): AiJob
    {
        return AiJob::create([
            'job_id'          => 'asr-job-' . uniqid(),
            'idempotency_key' => 'asr-key-' . uniqid(),
            'bot_id'          => $voice->bot_id,
            'user_id'         => $voice->user_id,
            'feature'         => 'asr',
            'priority'        => AiJob::PRIORITY_REGULAR,
            'state'           => AiJob::STATE_QUEUED,
            'provider'        => 'litellm',
            'model'           => 'whisper-1',
            'payload'         => [
                'voice_id' => $voice->id,
                'file_id'  => $voice->file_id,
                'origin'   => 'telegram',
                'bot_id'   => $voice->bot_id,
            ],
        ]);
    }

    /**
     * Fake برای API پلتفرم (getFile + download) و ASR endpoint.
     *
     * @param int    $asrStatus 200|404|500
     * @param string $asrText   متن استخراج‌شده (text)
     * @param bool   $fileOk    ok بودن getFile
     */
    private function fakePlatformAndAsr(int $asrStatus, string $asrText, bool $fileOk = true, string $asrBody = ''): void
    {
        $asr = $asrStatus === 200
            ? Http::response(['text' => $asrText, 'usage' => ['total_tokens' => 12]], 200)
            : Http::response($asrBody !== '' ? $asrBody : ['error' => 'fail'], $asrStatus);

        Http::fake([
            '*api.telegram.org/bot*/getFile*' => $fileOk
                ? Http::response(['ok' => true, 'result' => ['file_path' => '/files/voice.ogg']], 200)
                : Http::response(['ok' => false, 'error_code' => 40001, 'description' => 'Bad Request: file not found'], 400),
            '*api.telegram.org/file/bot*'      => Http::response('OGGVOICEBINARY', 200),
            '*ai.ismc.ir/api/v1/audio/transcriptions' => $asr,
            '*'                                 => Http::response(['ok' => false], 400),
        ]);
    }

    private function voiceUpdate(string $fileId, int $chatId): array
    {
        return [
            'message' => [
                'message_id' => 100,
                'chat'       => ['id' => $chatId, 'first_name' => 'Test', 'type' => 'private'],
                'date'       => 1763100000,
                'voice'      => [
                    'file_id'        => $fileId,
                    'file_unique_id' => $fileId . '_v',
                    'duration'       => 8,
                ],
            ],
        ];
    }
}
