<?php

namespace Tests\Feature;

use App\Interfaces\Services\ChannelPosterPublisher;
use App\Services\TelegramChannelPosterPublisher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * تحلیل ۵-حالته پاسخ روبیکا در Channel Poster (CP-RUBIKA-RESPONSE-FIX / M2+M6).
 *
 * ریشهٔ مشکل اثبات‌شده (لاگ پروتکشن ۲۰۲۶-۱۰-۲۶): API واقعی روبیکا پاکت
 * دو سطحی {"status": "...", "data": {...}} با HTTP 200 برمی‌گرداند و پیام
 * واقعاً ارسال می‌شود؛ اما parser فقط سطح بالا را می‌خواند و message_id
 * تو‌در‌توی data را نمی‌بیند → پیام کاذب «ربات ادمین نیست».
 *
 * حالا sendTestMessage یک DTO برمی‌گرداند:
 *   ['ok' => bool, 'class' => string, 'message_id' => ?string,
 *    'error' => ?string, 'http_status' => ?int, 'permission_denied' => bool]
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
    // sendTestMessage — RUBIKA (DTO ۵-حالته)
    // ─────────────────────────────────────────────────────────────────────

    public function test_rubika_test_send_success_with_documented_message_id(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['message_id' => '9001'], 200),
        ]);
        Log::shouldReceive('warning')->never();

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertTrue($result['ok']);
        $this->assertSame(ChannelPosterPublisher::TEST_SEND_SUCCESS_WITH_MESSAGE_ID, $result['class']);
        $this->assertSame('9001', $result['message_id']);

        Http::assertSent(fn (Request $r) =>
            $r->url() === 'https://botapi.rubika.ir/v3/' . self::TOKEN . '/sendMessage'
            && $r->data()['chat_id'] === self::CHANNEL_ID
        );
    }

    public function test_rubika_test_send_success_with_live_observed_nested_envelope(): void
    {
        // پاکت واقعی مشاهده‌شده در پروتکشن: {"status":"...","data":{...}} —
        // message_id در سطح data است، نه سطح بالا.
        Http::fake([
            'botapi.rubika.ir/*' => Http::response([
                'status' => 'OK',
                'data' => ['message_id' => '777001'],
            ], 200),
        ]);
        Log::shouldReceive('warning')->never();

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertTrue($result['ok']);
        $this->assertSame(ChannelPosterPublisher::TEST_SEND_SUCCESS_WITH_MESSAGE_ID, $result['class']);
        $this->assertSame('777001', $result['message_id']);
    }

    public function test_rubika_test_send_accepts_undocumented_id_alias(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['id' => '9002'], 200),
        ]);
        Log::shouldReceive('warning')->never();

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertTrue($result['ok']);
        $this->assertSame('9002', $result['message_id']);
    }

    public function test_rubika_test_send_explicit_success_without_message_id_is_not_admin_error(): void
    {
        // HTTP 200 + وضعیت موفقیت صریح بدون message_id = پذیرش موفق؛
        // نباید «ربات ادمین نیست» یا هر شکست دیگری گزارش شود.
        Http::fake([
            'botapi.rubika.ir/*' => Http::response([
                'status' => 'OK',
                'data' => [],
            ], 200),
        ]);

        Log::shouldReceive('info')->once()->withArgs(
            fn ($message, $ctx) => $message === '[ChannelPoster] Test send success without message_id'
                && ($ctx['platform'] ?? null) === 'rubika'
                && ($ctx['operation'] ?? null) === 'test_message'
                && ($ctx['http_status'] ?? null) === 200
                && isset($ctx['correlation_id'])
                && ! str_contains(json_encode($ctx, JSON_UNESCAPED_UNICODE), self::TOKEN)
        );
        Log::shouldReceive('warning')->never();

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertTrue($result['ok']);
        $this->assertSame(ChannelPosterPublisher::TEST_SEND_SUCCESS_WITHOUT_MESSAGE_ID, $result['class']);
        $this->assertNull($result['message_id']);
    }

    public function test_rubika_test_send_chat_id_only_is_unknown_result(): void
    {
        // مستندات رسمی: chat_id شناسهٔ چت است، نه شناسهٔ پیام. بدون مدرک
        // موفقیت صریح، نتیجه «نامشخص» است — نه «موفقیت» و نه «عدم ادمین».
        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['chat_id' => 'abc-chat-01'], 200),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $ctx) {
            if ($message !== '[ChannelPoster] Test send failed (rubika unknown_result)') {
                return false;
            }

            $dump = json_encode($ctx, JSON_UNESCAPED_UNICODE);

            return ($ctx['platform'] ?? null) === 'rubika'
                && ($ctx['operation'] ?? null) === 'test_message'
                && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) ($ctx['correlation_id'] ?? '')) === 1
                && ($ctx['http_status'] ?? null) === 200
                && is_array($ctx['response_shape'] ?? null)
                && ($ctx['response_shape']['has_message_id'] ?? null) === false
                && array_key_exists('chat_id', $ctx['response_shape']['shape'] ?? [])
                && ! str_contains($dump, self::TOKEN);
        });

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertFalse($result['ok']);
        $this->assertSame(ChannelPosterPublisher::TEST_SEND_UNKNOWN_RESULT, $result['class']);
        $this->assertNull($result['message_id']);
        $this->assertFalse($result['permission_denied']);
    }

    public function test_rubika_test_send_http_429_fails_as_api_rejected_with_status(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response('{"error":"too many requests"}', 429),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $ctx) {
            if ($message !== '[ChannelPoster] Test send failed (rubika exception)') {
                return false;
            }

            $dump = json_encode($ctx, JSON_UNESCAPED_UNICODE);

            return ($ctx['platform'] ?? null) === 'rubika'
                && ($ctx['operation'] ?? null) === 'test_message'
                && isset($ctx['correlation_id'])
                && ($ctx['http_status'] ?? null) === 429
                && ($ctx['exception_class'] ?? null) === \RuntimeException::class
                && str_contains($ctx['error'] ?? '', 'HTTP 429')
                && ! str_contains($dump, self::TOKEN);
        });

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertFalse($result['ok']);
        $this->assertSame(ChannelPosterPublisher::TEST_SEND_API_REJECTED, $result['class']);
        $this->assertSame(429, $result['http_status']);
        $this->assertFalse($result['permission_denied']);
        $this->assertStringContainsString('HTTP 429', $result['error']);
    }

    public function test_rubika_test_send_http_403_fails_as_api_rejected_with_permission_denied(): void
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
                && ($ctx['http_status'] ?? null) === 403
                && ($ctx['exception_class'] ?? null) === \RuntimeException::class
                && str_contains($ctx['error'] ?? '', 'HTTP 403')
                && ! str_contains($dump, self::TOKEN);
        });

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertFalse($result['ok']);
        $this->assertSame(ChannelPosterPublisher::TEST_SEND_API_REJECTED, $result['class']);
        $this->assertSame(403, $result['http_status']);
        $this->assertTrue($result['permission_denied']);
        $this->assertStringContainsString('HTTP 403', $result['error']);
    }

    public function test_rubika_test_send_http_401_fails_as_api_rejected_without_permission_denied(): void
    {
        // 401 = توکن نامعتبر، نه «عدم دسترسی»؛ پیام «ادمین نیست» برای آن
        // گمراه‌کننده است.
        Http::fake([
            'botapi.rubika.ir/*' => Http::response('{"error":"invalid token"}', 401),
        ]);

        Log::shouldReceive('warning')->once();

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertFalse($result['ok']);
        $this->assertSame(ChannelPosterPublisher::TEST_SEND_API_REJECTED, $result['class']);
        $this->assertSame(401, $result['http_status']);
        $this->assertFalse($result['permission_denied']);
        $this->assertStringContainsString('HTTP 401', $result['error']);
    }

    public function test_rubika_test_send_missing_token_fails_as_invalid_input(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(
            fn ($message, $ctx) => $message === '[ChannelPoster] Test send failed'
                && ($ctx['platform'] ?? null) === 'rubika'
                && ($ctx['operation'] ?? null) === 'test_message'
                && isset($ctx['correlation_id'])
                && str_contains($ctx['error'] ?? '', 'missing rubika bot token')
        );

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', '');

        $this->assertFalse($result['ok']);
        $this->assertSame(ChannelPosterPublisher::TEST_SEND_INVALID_INPUT, $result['class']);
        $this->assertNull($result['http_status']);
    }

    public function test_rubika_test_send_timeout_fails_as_transport_failure(): void
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
                && ($ctx['http_status'] ?? null) === null
                && str_contains($ctx['error'] ?? '', 'connection error')
                && str_contains($ctx['error'] ?? '', 'timed out');
        });

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertFalse($result['ok']);
        $this->assertSame(ChannelPosterPublisher::TEST_SEND_TRANSPORT_FAILURE, $result['class']);
        $this->assertNull($result['http_status']);
        $this->assertFalse($result['permission_denied']);
        $this->assertStringContainsString('connection error', $result['error']);
    }

    public function test_rubika_test_send_non_json_response_is_transport_failure_not_rejected(): void
    {
        // «non-JSON (HTTP 200)» یعنی پاسخ خوانا نبود — نتیجهٔ نامشخص/انتقالی،
        // نه رد صریح از سوی API.
        Http::fake([
            'botapi.rubika.ir/*' => Http::response('<html>gateway</html>', 200),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $ctx) {
            return $message === '[ChannelPoster] Test send failed (rubika exception)'
                && ($ctx['exception_class'] ?? null) === \RuntimeException::class
                && ($ctx['http_status'] ?? null) === null
                && str_contains($ctx['error'] ?? '', 'non-JSON');
        });

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertFalse($result['ok']);
        $this->assertSame(ChannelPosterPublisher::TEST_SEND_TRANSPORT_FAILURE, $result['class']);
        $this->assertStringContainsString('non-JSON', $result['error']);
    }

    public function test_rubika_test_send_api_error_envelope_is_rejected_with_permission_denied_no_secret(): void
    {
        $secretBody = ['code' => 403, 'error' => 'access denied for chat'];

        Http::fake([
            'botapi.rubika.ir/*' => Http::response($secretBody, 200),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $ctx) {
            if ($message !== '[ChannelPoster] Test send failed (rubika api_rejected)') {
                return false;
            }

            $dump = json_encode($ctx, JSON_UNESCAPED_UNICODE);

            return ($ctx['platform'] ?? null) === 'rubika'
                && ($ctx['operation'] ?? null) === 'test_message'
                && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) ($ctx['correlation_id'] ?? '')) === 1
                && ($ctx['http_status'] ?? null) === 200
                && ($ctx['permission_denied'] ?? null) === true
                && is_array($ctx['response_shape'] ?? null)
                && ($ctx['response_shape']['has_message_id'] ?? null) === false
                && array_key_exists('code', $ctx['response_shape']['shape'] ?? [])
                && in_array('code', $ctx['response_shape']['error_keys'] ?? [], true)
                // هیچ دادهٔ محرمانه‌ای در لاگ نباشد:
                && ! str_contains($dump, self::TOKEN)
                && ! str_contains($dump, self::CHANNEL_ID)
                && ! str_contains($dump, 'access denied');
        });

        $result = $this->publisher()->sendTestMessage(self::CHANNEL_ID, 'test', 'rubika', self::TOKEN);

        $this->assertFalse($result['ok']);
        $this->assertSame(ChannelPosterPublisher::TEST_SEND_API_REJECTED, $result['class']);
        $this->assertSame(200, $result['http_status']);
        $this->assertTrue($result['permission_denied']);
        $this->assertNull($result['message_id']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // publish() — RUBIKA (همان parser مشترک: analyzeResponse)
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

    public function test_rubika_publish_text_success_with_live_observed_nested_envelope(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response([
                'status' => 'OK',
                'data' => ['message_id' => '9011'],
            ], 200),
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
        $this->assertSame('9011', $result['message_id']);
    }

    public function test_rubika_publish_text_success_without_message_id_is_success(): void
    {
        // پذیرش صریح API بدون message_id = موفقیت؛ message_id در لاگ انتشار
        // می‌تواند null باشد (و باعث عدم تکرار خودکار می‌شود).
        Http::fake([
            'botapi.rubika.ir/*' => Http::response([
                'status' => 'OK',
                'data' => [],
            ], 200),
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
        $this->assertNull($result['message_id']);
    }

    public function test_rubika_publish_text_api_error_envelope_returns_rejected_error(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['code' => 403, 'error' => 'denied'], 200),
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->once()->withArgs(function ($message, $ctx) {
            if ($message !== '[ChannelPoster][publish] publish-failed (rubika api_rejected)') {
                return false;
            }

            $dump = json_encode($ctx, JSON_UNESCAPED_UNICODE);

            return ($ctx['operation'] ?? null) === 'publish'
                && isset($ctx['correlation_id'])
                && ($ctx['http_status'] ?? null) === 200
                && is_array($ctx['response_shape'] ?? null)
                && ! str_contains($dump, self::TOKEN)
                && ! str_contains($dump, self::CHANNEL_ID)
                && ! str_contains($dump, 'denied');
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
        $this->assertStringContainsString('API rejected', $result['error'] ?? '');
    }

    public function test_rubika_publish_text_unknown_result_returns_unknown_error(): void
    {
        Http::fake([
            'botapi.rubika.ir/*' => Http::response(['chat_id' => '9876543210'], 200),
        ]);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->once()->withArgs(function ($message, $ctx) {
            if ($message !== '[ChannelPoster][publish] publish-failed (rubika unknown_result)') {
                return false;
            }

            $dump = json_encode($ctx, JSON_UNESCAPED_UNICODE);

            return ($ctx['operation'] ?? null) === 'publish'
                && isset($ctx['correlation_id'])
                && ($ctx['http_status'] ?? null) === 200
                && is_array($ctx['response_shape'] ?? null)
                && ! str_contains($dump, self::TOKEN);
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
        $this->assertStringContainsString('result unknown', $result['error'] ?? '');
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
                && ($ctx['http_status'] ?? null) === 500
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
        $this->assertStringContainsString('HTTP 500', $result['error']);
        $this->assertStringNotContainsString(self::TOKEN, $result['error']);
    }
}
