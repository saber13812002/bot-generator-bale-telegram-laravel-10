<?php

namespace Tests\Unit;

use App\Services\TelegramChannelPosterPublisher;
use Tests\TestCase;

/**
 * T5 (addendum E) — «retry امن parse_mode»:
 *
 * retry بدون parse_mode فقط وقتی مجاز است که پاسخ API «خطای parse صریح» باشد
 * (فیلد description با pattern parse). timeout/ConnectionException (پاکت
 * curl_error بدون description) و پاسخ نامشخص هرگز retry نمی‌شوند —
 * این guard خالص و قابل تست بدون شبکه است.
 */
class ChannelPosterPublisherParseRetryTest extends TestCase
{
    // ── isParseError: پاکت خطای Telegram/Bot API ─────────────────────────

    public function test_parse_error_envelope_is_recognized(): void
    {
        $this->assertTrue(TelegramChannelPosterPublisher::isParseError([
            'ok'         => false,
            'error_code' => 400,
            'description' => "Bad Request: can't parse entities",
        ]));
        $this->assertTrue(TelegramChannelPosterPublisher::isParseError([
            'ok'         => false,
            'error_code' => 400,
            'description' => "Bad Request: couldn't parse entities",
        ]));
        $this->assertTrue(TelegramChannelPosterPublisher::isParseError([
            'ok'         => false,
            'error_code' => 400,
            'description' => 'Bad Request: message text contains entities but parse mode is missing',
        ]));
    }

    public function test_non_parse_error_envelopes_are_not_retried(): void
    {
        $this->assertFalse(TelegramChannelPosterPublisher::isParseError([
            'ok'         => false,
            'error_code' => 400,
            'description' => 'Bad Request: chat not found',
        ]));
        $this->assertFalse(TelegramChannelPosterPublisher::isParseError([
            'ok'         => false,
            'error_code' => 429,
            'description' => 'Too Many Requests: retry later',
        ]));
        $this->assertFalse(TelegramChannelPosterPublisher::isParseError([
            'ok'         => false,
            'error_code' => 403,
            'description' => 'Forbidden: bot is not a member of the channel chat',
        ]));
    }

    public function test_successful_envelope_is_not_a_parse_error(): void
    {
        $this->assertFalse(TelegramChannelPosterPublisher::isParseError([
            'ok'     => true,
            'result' => ['message_id' => 42],
        ]));
    }

    // ── شکل‌های برگشتی vendor client (string/JSON) ─────────────────────

    public function test_parse_error_as_raw_json_string_is_recognized(): void
    {
        $raw = json_encode(['ok' => false, 'error_code' => 400, 'description' => "Bad Request: can't parse entities"]);
        $this->assertTrue(TelegramChannelPosterPublisher::isParseError($raw));
    }

    public function test_network_transport_failure_without_description_is_never_retried(): void
    {
        // curl_exec === false → vendor client پاکت curl_error برمی‌گرداند؛
        // هیچ description ندارد ⇒ timeout/خطای شبکه هرگز retry نمی‌شود.
        $this->assertFalse(TelegramChannelPosterPublisher::isParseError([
            'ok'              => false,
            'curl_error_code' => 28,
            'curl_error'      => 'Operation timed out after 300001 milliseconds',
        ]));
        $this->assertFalse(TelegramChannelPosterPublisher::isParseError([
            'ok'              => false,
            'curl_error_code' => 6,
            'curl_error'      => 'Could not resolve host: api.telegram.org',
        ]));
    }

    public function test_unknown_and_empty_results_are_never_retried(): void
    {
        $this->assertFalse(TelegramChannelPosterPublisher::isParseError(null));
        $this->assertFalse(TelegramChannelPosterPublisher::isParseError(''));
        $this->assertFalse(TelegramChannelPosterPublisher::isParseError(false));
        $this->assertFalse(TelegramChannelPosterPublisher::isParseError('some opaque error text without structure'));
        $this->assertFalse(TelegramChannelPosterPublisher::isParseError(['unexpected' => 'shape']));
    }

    // ── isParseErrorDescription: متن خام (خطای media sender) ─────────────

    public function test_description_text_from_media_error_is_recognized(): void
    {
        // parseResponse media sender، description را در error می‌بندد:
        // 'API ok=false: Bad Request: can\'t parse entities'
        $this->assertTrue(TelegramChannelPosterPublisher::isParseErrorDescription(
            "API ok=false: Bad Request: can't parse entities"
        ));
        $this->assertTrue(TelegramChannelPosterPublisher::isParseErrorDescription(
            "Bad Request: can't parse entities"
        ));
    }

    public function test_plain_error_text_is_never_retried(): void
    {
        $this->assertFalse(TelegramChannelPosterPublisher::isParseErrorDescription('HTTP 400'));
        $this->assertFalse(TelegramChannelPosterPublisher::isParseErrorDescription('HTTP 500'));
        $this->assertFalse(TelegramChannelPosterPublisher::isParseErrorDescription('API ok=false: Forbidden: bot was kicked'));
        $this->assertFalse(TelegramChannelPosterPublisher::isParseErrorDescription(''));
        $this->assertFalse(TelegramChannelPosterPublisher::isParseErrorDescription(null));
    }
}
