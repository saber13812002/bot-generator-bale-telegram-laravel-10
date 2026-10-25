<?php

namespace Tests\Feature;

use App\Services\TelegramChannelPosterPublisher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * عیب‌یابی ارسال پیام آزمایشی روبیکا در Channel Poster (Mission Rubika TestSend).
 *
 * یافتهٔ اصلی: پاسخ واقعی روبیکا گاهی 2xx + JSON بدون فیلد message_id
 * برمی‌گردانید (مستندات رسمی فقط message_id را تعریف می‌کند). قبل از اصلاح،
 * این پاسخ باعث «نتیجهٔ چهارفیلدی null» در لاگ و پیام کاذب «ربات ادمین
 * نیست» می‌شد. پس از اصلاح:
 *  - extractMessageId هم‌معناهای غیرمستند (id / chat_id) را می‌پذیرد
 *  - شکست‌ها با لاگ امن (بدون توکن/URL) و correlation_id ثبت می‌شوند
 *
 * همهٔ موارد MOCK_TESTED با Http::fake — هیچ درخواست زنده‌ای ارسال نمی‌شود.
 */
class ChannelPosterRubikaTestSendTest extends TestCase
{
    private const TOKEN = 'rubika-testsend-token-0123456789';

    private const CHANNEL_ID = '9876543210';

    private function publisher(): TelegramChannelPosterPublisher
    {
        return new TelegramChannelPosterPublisher(new \Telegram('123:channel-poster-token'), 'rubika');
    }

    // ─────────────────────────────────────────────────────────────────────
    // sendTestMessage — RUBIKA
    // ─────────────────────────────────────────────────────────────────────

    public function test_rubika_test_send_success_with_documented_message_id(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['message_id' => '9001'], 200),
        ]);
        Log::shouldReceive('warning')->never();

        $ok = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertTrue($ok);

        Http::assertSent(fn (Request $r) =>
            $r->url() === 'https://botapi.rubika.ir/v3/' . self::TOKEN . '/sendMessage'
            && $r->data()['chat_id'] === self::CHANNEL_ID
        );
    }

    public function test_rubika_test_send_accepts_undocumented_id_alias(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['id' => '9002'], 200),
        ]);
        Log::shouldReceive('warning')->never();

        $this->assertTrue(
            $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN)
        );
    }

    public function test_rubika_test_send_accepts_undocumented_chat_id_alias(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['chat_id' => 'abc-chat-01'], 200),
        ]);
        Log::shouldReceive('warning')->never();

        $this->assertTrue(
            $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN)
        );
    }

    public function test_rubika_test_send_missing_token_fails_safely(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(
            fn ($message, $ctx) => $message === '[ChannelPoster] Test send failed'
                && ($ctx['platform'] ?? null) === 'rubika'
                && ($ctx['operation'] ?? null) === 'test_message'
                && isset($ctx['correlation_id'])
                && str_contains($ctx['error'] ?? '', 'missing rubika bot token')
        );

        $this->assertFalse(
            $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', '')
        );
    }

    public function test_rubika_test_send_api_error_shape_is_diagnosed_never_logs_secret(): void
    {
        $secretBody = ['code' => 403, 'error' => 'access denied for chat'];

        Http::fake([
            'botapi.rubika.ir/*' => Http::response($secretBody, 200),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $ctx) {
            if ($message !== '[ChannelPoster] Test send failed (rubika no message_id)') {
                return false;
            }

            $dump = json_encode($ctx, JSON_UNESCAPED_UNICODE);

            return ($ctx['platform'] ?? null) === 'rubika'
                && ($ctx['operation'] ?? null) === 'test_message'
                && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) ($ctx['correlation_id'] ?? '')) === 1
                && ($ctx['http_status'] ?? null) === 200
                && ($ctx['api_error'] ?? null) === null
                && is_array($ctx['response_shape'] ?? null)
                && ($ctx['response_shape']['has_message_id'] ?? null) === false
                && array_key_exists('code', $ctx['response_shape']['shape'] ?? [])
                && in_array('code', $ctx['response_shape']['error_keys'] ?? [], true)
                // هیچ دادهٔ محرمانه‌ای در لاگ نباشد:
                && ! str_contains($dump, self::TOKEN)
                && ! str_contains($dump, self::CHANNEL_ID)
                && ! str_contains($dump, 'access denied');
        });

        $this->assertFalse(
            $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN)
        );
    }

    public function test_rubika_test_send_http_403_fails_with_safe_exception_log(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response('{"error":"invalid"}', 403),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $ctx) {
            if ($message !== '[ChannelPoster] Test send failed (rubika exception)') {
                return false;
            }

            $dump = json_encode($ctx, JSON_UNESCAPED_UNICODE);

            return ($ctx['platform'] ?? null) === 'rubika'
                && ($ctx['operation'] ?? null) === 'test_message'
                && isset($ctx['correlation_id'])
                && ($ctx['http_status'] ?? null) === null
                && ($ctx['exception_class'] ?? null) === \RuntimeException::class
                && str_contains($ctx['error'] ?? '', 'HTTP 403')
                && ! str_contains($dump, self::TOKEN);
        });

        $this->assertFalse(
            $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN)
        );
    }

    public function test_rubika_test_send_timeout_fails_with_safe_exception_log(): void
    {
        Http::fake(fn () => throw new ConnectionException('connection timed out'));

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $ctx) {
            if ($message !== '[ChannelPoster] Test send failed (rubika exception)') {
                return false;
            }

            // RubikaBotClient::call() خطای اتصال را در RuntimeException «rubika: connection error ...»
            // پیچیده می‌کند؛ کلاس خام ConnectionException هرگز به publisher نمی‌رسد.
            return ($ctx['exception_class'] ?? null) === \RuntimeException::class
                && ($ctx['operation'] ?? null) === 'test_message'
                && str_contains($ctx['error'] ?? '', 'connection error')
                && str_contains($ctx['error'] ?? '', 'timed out');
        });

        $this->assertFalse(
            $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN)
        );
    }

    public function test_rubika_test_send_non_json_response_fails_safely(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response('<html>gateway</html>', 200),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $ctx) {
            return $message === '[ChannelPoster] Test send failed (rubika exception)'
                && ($ctx['exception_class'] ?? null) === \RuntimeException::class
                && str_contains($ctx['error'] ?? '', 'non-JSON');
        });

        $this->assertFalse(
            $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // publish() — RUBIKA (همان منطق مشترک: امضا/پشتیبان از extractMessageId)
    // ─────────────────────────────────────────────────────────────────────

    public function test_rubika_publish_text_success_with_alias_message_id(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['id' => '9010'], 200),
        ]);
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->never();

        $result = $this->publisher()->publish(
            self::CHANNEL_ID,
            'text',
            'سلام',
            null,
            'rubika',
            self::TOKEN
        );

        $this->assertTrue($result['success']);
        $this->assertSame('9010', $result['message_id']);
    }

    public function test_rubika_publish_text_failure_returns_error_with_diagnostic_log(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['code' => 403, 'error' => 'denied'], 200),
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->once()->withArgs(function ($message, $ctx) {
            if ($message !== '[ChannelPoster][publish] publish-failed (rubika no message_id)') {
                return false;
            }

            $dump = json_encode($ctx, JSON_UNESCAPED_UNICODE);

            return ($ctx['operation'] ?? null) === 'publish'
                && isset($ctx['correlation_id'])
                && is_array($ctx['response_shape'] ?? null)
                && ($ctx['response_shape']['has_message_id'] ?? null) === false
                && ! str_contains($dump, self::TOKEN)
                && ! str_contains($dump, self::CHANNEL_ID);
        });

        $result = $this->publisher()->publish(
            self::CHANNEL_ID,
            'text',
            'سلام',
            null,
            'rubika',
            self::TOKEN
        );

        $this->assertFalse($result['success']);
        $this->assertNull($result['message_id']);
        $this->assertStringContainsString('no message_id', $result['error'] ?? '');
    }

    public function test_rubika_publish_text_exception_returns_safe_error(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response('boom', 500),
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->once()->withArgs(function ($message, $ctx) {
            if ($message !== '[ChannelPoster][publish] publish-failed (rubika text exception)') {
                return false;
            }

            return ($ctx['exception_class'] ?? null) === \RuntimeException::class
                && str_contains($ctx['error'] ?? '', 'HTTP 500')
                && ! str_contains($ctx['error'] ?? '', self::TOKEN);
        });

        $result = $this->publisher()->publish(
            self::CHANNEL_ID,
            'text',
            'سلام',
            null,
            'rubika',
            self::TOKEN
        );

        $this->assertFalse($result['success']);
        $this->assertNull($result['message_id']);
        $this->assertNotNull($result['error']);
        $this->assertStringNotContainsString(self::TOKEN, $result['error']);
    }
}
