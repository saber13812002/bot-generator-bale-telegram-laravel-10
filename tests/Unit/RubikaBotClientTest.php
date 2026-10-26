<?php

namespace Tests\Unit;

use App\Services\RubikaBotClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for RubikaBotClient (Rubika Bot API v3) — MOCK_TESTED.
 *
 * All requests are faked with Http::fake; no live calls.
 * The response envelope is NOT documented by Rubika (BLOCKED); success is
 * detected via message_id presence. See docs/features/platform-api-research.md.
 */
class RubikaBotClientTest extends TestCase
{
    private string $token = 'opaque-rubika-token-0123456789';

    // ── fileTypeFor mapping ─────────────────────────────────────────────

    public function test_file_type_for_maps_content_types(): void
    {
        $this->assertSame('Image', RubikaBotClient::fileTypeFor('photo'));
        $this->assertSame('Video', RubikaBotClient::fileTypeFor('video'));
        $this->assertSame('Voice', RubikaBotClient::fileTypeFor('voice'));
        $this->assertSame('Music', RubikaBotClient::fileTypeFor('audio'));
        $this->assertSame('File', RubikaBotClient::fileTypeFor('document'));
        $this->assertSame('File', RubikaBotClient::fileTypeFor('unknown'));
    }

    // ── call / getMe ────────────────────────────────────────────────────

    public function test_call_posts_json_to_v3_base_url(): void
    {
        Http::fake([
            'botapi.rubika.ir*' => Http::response(['result' => ['id' => '111']]),
        ]);

        $client = new RubikaBotClient($this->token);
        $data = $client->getMe();

        $this->assertSame('111', $data['result']['id']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://botapi.rubika.ir/v3/' . $this->token . '/getMe'
                && $request->method() === 'POST';
        });
    }

    public function test_http_error_status_throws_with_body_excerpt(): void
    {
        Http::fake([
            'botapi.rubika.ir*' => Http::response('{"error":"bad token"}', 401),
        ]);

        $client = new RubikaBotClient($this->token);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rubika: HTTP 401 calling getMe');

        $client->getMe();
    }

    public function test_non_json_response_throws_runtime_exception(): void
    {
        Http::fake([
            'botapi.rubika.ir*' => Http::response('gateway error', 200),
        ]);

        $client = new RubikaBotClient($this->token);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-JSON response from getMe');

        $client->getMe();
    }

    public function test_connection_error_throws_wrapped_runtime_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('network down'));

        $client = new RubikaBotClient($this->token);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rubika: connection error calling getMe');

        $client->getMe();
    }

    // ── sendMessage + message_id helpers ────────────────────────────────

    public function test_sendmessage_sends_chat_id_and_text_without_notification_flag(): void
    {
        Http::fake([
            'botapi.rubika.ir*' => Http::response(['message_id' => '8888']),
        ]);

        $client = new RubikaBotClient($this->token);
        $data = $client->sendMessage('@mychannel', 'سلام روبیکا');

        $this->assertTrue(RubikaBotClient::hasMessageId($data));
        $this->assertSame('8888', RubikaBotClient::extractMessageId($data));

        Http::assertSent(function (Request $request) {
            $payload = $request->data();
            return $request->url() === 'https://botapi.rubika.ir/v3/' . $this->token . '/sendMessage'
                && $payload['chat_id'] === '@mychannel'
                && $payload['text'] === 'سلام روبیکا'
                && ! array_key_exists('disable_notification', $payload);
        });
    }

    public function test_sendmessage_includes_disable_notification_when_requested(): void
    {
        Http::fake([
            'botapi.rubika.ir*' => Http::response(['message_id' => '1']),
        ]);

        $client = new RubikaBotClient($this->token);
        $client->sendMessage('123', 'ping', true);

        Http::assertSent(fn (Request $request) => $request->data()['disable_notification'] === true);
    }

    public function test_has_message_id_accepts_string_and_positive_int(): void
    {
        $this->assertTrue(RubikaBotClient::hasMessageId(['message_id' => 'abc']));
        $this->assertTrue(RubikaBotClient::hasMessageId(['message_id' => 42]));
        $this->assertFalse(RubikaBotClient::hasMessageId(['message_id' => '']));
        $this->assertFalse(RubikaBotClient::hasMessageId(['message_id' => 0]));
        $this->assertFalse(RubikaBotClient::hasMessageId([]));
    }

    public function test_extract_message_id_normalizes_int_to_string(): void
    {
        $this->assertSame('42', RubikaBotClient::extractMessageId(['message_id' => 42]));
        $this->assertSame('abc', RubikaBotClient::extractMessageId(['message_id' => 'abc']));
        $this->assertNull(RubikaBotClient::extractMessageId(['message_id' => '']));
        $this->assertNull(RubikaBotClient::extractMessageId([]));
    }

    // پاسخ واقعی روبیکا گاهی 2xx بدون message_id برمی‌گرداند (مستندات رسمی
    // فقط message_id را به‌عنوان خروجی sendMessage تعریف می‌کند؛ پاکت پاسخ
    // OFFICIAL_DOC_NOT_FOUND). هم‌معنای غیرمستند «id» پذیرفته می‌شود، اما:
    // 1) «chat_id» در قرارداد رسمی شناسهٔ چت است (فیلد ورودی جدا از
    //    message_id در همهٔ متدها) و هرگز شناسهٔ پیام نیست (ممیزی
    //    CP-RELEASE-GATE، اکتبر ۲۰۲۶) → دیگر به‌عنوان هم‌معنا پذیرفته نیست.
    // 2) پاکتی که کلید خطا (code/error/...) داشته باشد = خطای سطح API در
    //    HTTP 2xx؛ هیچ id (حتی message_id) از آن پذیرفته نمی‌شود.

    public function test_extract_message_id_accepts_undocumented_id_alias(): void
    {
        $this->assertSame('9002', RubikaBotClient::extractMessageId(['id' => '9002']));
        $this->assertSame('77', RubikaBotClient::extractMessageId(['id' => 77]));
    }

    public function test_extract_message_id_rejects_chat_id_as_message_identifier(): void
    {
        // chat_id در مستندات رسمی = شناسهٔ چت، نه شناسهٔ پیام.
        $this->assertNull(RubikaBotClient::extractMessageId(['chat_id' => 'chat-abc']));
        $this->assertFalse(RubikaBotClient::hasMessageId(['chat_id' => 'x']));

        // حضور chat_id مانع استفاده از هم‌معنای معتبر «id» نمی‌شود.
        $this->assertSame('9002', RubikaBotClient::extractMessageId(['chat_id' => 'chat-abc', 'id' => '9002']));
    }

    public function test_extract_message_id_ignores_ids_in_error_envelope(): void
    {
        // پاکت خطای API در HTTP 2xx — هیچ id از آن موفقیت شمرده نمی‌شود.
        $this->assertNull(RubikaBotClient::extractMessageId([
            'code' => 403,
            'error' => 'denied',
            'id' => '123',
        ]));

        // حتی اگر message_id هم داخل پاکت خطا باشد، موفقیت ثبت نمی‌شود.
        $this->assertNull(RubikaBotClient::extractMessageId([
            'code' => 403,
            'error' => 'denied',
            'message_id' => '9001',
        ]));

        $this->assertFalse(RubikaBotClient::hasMessageId(['code' => 403, 'error' => 'denied', 'id' => '123']));
    }

    public function test_extract_message_id_prefers_documented_message_id_over_alias(): void
    {
        $this->assertSame(
            'doc',
            RubikaBotClient::extractMessageId(['message_id' => 'doc', 'id' => 'alias'])
        );

        // message_id خالی/غیرمعتبر → به هم‌معنای بعدی ادامه می‌دهد
        $this->assertSame('alias', RubikaBotClient::extractMessageId(['message_id' => '', 'id' => 'alias']));
        $this->assertNull(RubikaBotClient::extractMessageId(['message_id' => 0]));
    }

    public function test_has_message_id_follows_extract_message_id(): void
    {
        $this->assertTrue(RubikaBotClient::hasMessageId(['id' => '9002']));
        $this->assertFalse(RubikaBotClient::hasMessageId(['chat_id' => 'x']));
        $this->assertFalse(RubikaBotClient::hasMessageId(['code' => 403, 'error' => 'denied']));
        $this->assertFalse(RubikaBotClient::hasMessageId([]));
    }

    public function test_diagnose_reports_shape_and_error_keys_without_leaking_values(): void
    {
        $diag = RubikaBotClient::diagnose([
            'code' => 403,
            'error' => 'access denied for chat',
            'description' => 'cannot post to this chat',
        ]);

        $this->assertFalse($diag['has_message_id']);
        $this->assertSame(3, $diag['keys_count']);
        $this->assertSame(['integer'], [$diag['shape']['code']]);
        $this->assertSame(['string'], [$diag['shape']['error']]);
        $this->assertSame(['code', 'error'], $diag['error_keys']);

        // هیچ مقدار فیلدی (نه متن خطا، نه توضیح) نشت نمی‌کند
        $dump = json_encode($diag, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('access denied', $dump);
        $this->assertStringNotContainsString('cannot post to this chat', $dump);
    }

    public function test_diagnose_success_shape_marks_message_id_and_not_error_key(): void
    {
        $diag = RubikaBotClient::diagnose(['message_id' => '9001']);

        $this->assertTrue($diag['has_message_id']);
        $this->assertSame(['string'], [$diag['shape']['message_id']]);
        $this->assertSame([], $diag['error_keys']);
    }

    public function test_correlation_id_is_uuid_shape_and_unique(): void
    {
        $a = RubikaBotClient::correlationId();
        $b = RubikaBotClient::correlationId();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $a
        );
        $this->assertNotSame($a, $b);
    }

    // ── 3-step file upload ──────────────────────────────────────────────

    public function test_request_upload_url_returns_upload_url_from_request_send_file(): void
    {
        Http::fake([
            'botapi.rubika.ir*' => Http::response(['upload_url' => 'https://upload.rubika.example/u/123']),
        ]);

        $client = new RubikaBotClient($this->token);
        $url = $client->requestUploadUrl('Image');

        $this->assertSame('https://upload.rubika.example/u/123', $url);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://botapi.rubika.ir/v3/' . $this->token . '/requestSendFile'
                && $request->data()['type'] === 'Image';
        });
    }

    public function test_request_upload_url_throws_when_url_missing(): void
    {
        Http::fake([
            'botapi.rubika.ir*' => Http::response(['some_other_field' => 1]),
        ]);

        $client = new RubikaBotClient($this->token);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no upload_url');

        $client->requestUploadUrl('File');
    }

    public function test_upload_file_posts_multipart_and_returns_file_id(): void
    {
        $uploadUrl = 'https://upload.rubika.example/u/123';
        $contents = str_repeat('x', 256);

        Http::fake([
            $uploadUrl => Http::response(['file_id' => 'f-900']),
        ]);

        $client = new RubikaBotClient($this->token);
        $fileId = $client->uploadFile($uploadUrl, $contents, 'photo.jpg', 'image/jpeg');

        $this->assertSame('f-900', $fileId);

        Http::assertSent(function (Request $request) use ($contents, $uploadUrl) {
            return $request->url() === $uploadUrl
                && $request->isMultipart()
                && $request->hasFile('file', $contents, 'photo.jpg');
        });
    }

    public function test_upload_file_throws_on_http_error(): void
    {
        Http::fake([
            'upload.rubika.example*' => Http::response('quota exceeded', 413),
        ]);

        $client = new RubikaBotClient($this->token);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rubika: HTTP 413 uploading file');

        $client->uploadFile('https://upload.rubika.example/u/1', 'abc', 'a.mp4', 'video/mp4');
    }

    public function test_upload_file_throws_when_file_id_missing(): void
    {
        Http::fake([
            'upload.rubika.example*' => Http::response(['ok' => false]),
        ]);

        $client = new RubikaBotClient($this->token);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no file_id in upload response');

        $client->uploadFile('https://upload.rubika.example/u/1', 'abc', 'a.jpg', 'image/jpeg');
    }

    public function test_send_file_posts_chat_file_and_text(): void
    {
        Http::fake([
            'botapi.rubika.ir*' => Http::response(['message_id' => 'm-1']),
        ]);

        $client = new RubikaBotClient($this->token);
        $response = $client->sendFile('@ch', 'f-900', 'کپشن فایل');

        $this->assertTrue(RubikaBotClient::hasMessageId($response->json()));

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://botapi.rubika.ir/v3/' . $this->token . '/sendFile'
                && $request->data()['chat_id'] === '@ch'
                && $request->data()['file_id'] === 'f-900'
                && $request->data()['text'] === 'کپشن فایل';
        });
    }

    public function test_send_file_omits_text_when_empty(): void
    {
        Http::fake([
            'botapi.rubika.ir*' => Http::response(['message_id' => 'm-1']),
        ]);

        $client = new RubikaBotClient($this->token);
        $client->sendFile('@ch', 'f-900', '');

        Http::assertSent(fn (Request $request) => ! array_key_exists('text', $request->data()));
    }

    // ── getUpdates / updateEndpoint ─────────────────────────────────────

    public function test_get_updates_passes_limit_and_offset(): void
    {
        Http::fake([
            'botapi.rubika.ir*' => Http::response(['result' => [], 'next_offset_id' => 'off-2']),
        ]);

        $client = new RubikaBotClient($this->token);
        $client->getUpdates('off-1', 50);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://botapi.rubika.ir/v3/' . $this->token . '/getUpdates'
                && $request->data()['limit'] === 50
                && $request->data()['offset_id'] === 'off-1';
        });
    }

    public function test_update_endpoint_calls_update_bot_endpoints(): void
    {
        Http::fake([
            'botapi.rubika.ir*' => Http::response(['result' => true]),
        ]);

        $client = new RubikaBotClient($this->token);
        $client->updateEndpoint('https://example.com/rubika', 'ReceiveUpdate');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://botapi.rubika.ir/v3/' . $this->token . '/updateBotEndpoints'
                && $request->data()['url'] === 'https://example.com/rubika'
                && $request->data()['type'] === 'ReceiveUpdate';
        });
    }

    // ── 5-state response analysis (CP-RUBIKA-RESPONSE-FIX / M2) ──────────

    public function test_analyze_live_observed_envelope_with_nested_message_id_is_success(): void
    {
        // Evidence from production log (2026-10-26): real API answered
        // {"status":"<string>","data":{...}} at HTTP 200 and the message
        // was delivered, with message_id nested under data.
        $result = RubikaBotClient::analyzeResponse([
            'status' => 'OK',
            'data' => ['message_id' => '777001'],
        ]);

        $this->assertSame(RubikaBotClient::STATE_SUCCESS_WITH_MESSAGE_ID, $result['state']);
        $this->assertSame('777001', $result['message_id']);
        $this->assertNull($result['reason']);
        $this->assertNull($result['http_status']);
        $this->assertFalse($result['permission_denied']);
    }

    public function test_analyze_explicit_success_status_without_message_id_is_success_without_id(): void
    {
        $result = RubikaBotClient::analyzeResponse([
            'status' => 'OK',
            'data' => [],
        ]);

        $this->assertSame(RubikaBotClient::STATE_SUCCESS_WITHOUT_MESSAGE_ID, $result['state']);
        $this->assertNull($result['message_id']);
        $this->assertNull($result['reason']);
        $this->assertFalse($result['permission_denied']);
    }

    public function test_analyze_flat_documented_message_id_still_success(): void
    {
        $result = RubikaBotClient::analyzeResponse(['message_id' => '9001']);

        $this->assertSame(RubikaBotClient::STATE_SUCCESS_WITH_MESSAGE_ID, $result['state']);
        $this->assertSame('9001', $result['message_id']);
    }

    public function test_analyze_error_envelope_with_403_code_is_api_rejected_with_permission_denied(): void
    {
        $result = RubikaBotClient::analyzeResponse([
            'code' => 403,
            'error' => 'access denied for chat',
        ]);

        $this->assertSame(RubikaBotClient::STATE_API_REJECTED, $result['state']);
        $this->assertSame('error_envelope', $result['reason']);
        $this->assertNull($result['message_id']);
        $this->assertTrue($result['permission_denied']);
    }

    public function test_analyze_error_envelope_ignores_embedded_message_id(): void
    {
        $result = RubikaBotClient::analyzeResponse([
            'code' => 403,
            'error' => 'denied',
            'message_id' => '9001',
        ]);

        $this->assertSame(RubikaBotClient::STATE_API_REJECTED, $result['state']);
        $this->assertNull($result['message_id']);
    }

    public function test_analyze_forbidden_status_string_is_api_rejected_with_permission_denied(): void
    {
        $result = RubikaBotClient::analyzeResponse(['status' => 'Forbidden', 'data' => []]);

        $this->assertSame(RubikaBotClient::STATE_API_REJECTED, $result['state']);
        $this->assertTrue($result['permission_denied']);
    }

    public function test_analyze_failure_status_without_error_keys_is_api_rejected(): void
    {
        $result = RubikaBotClient::analyzeResponse(['status' => 'error']);

        $this->assertSame(RubikaBotClient::STATE_API_REJECTED, $result['state']);
        $this->assertSame('failure_status', $result['reason']);
        $this->assertFalse($result['permission_denied']);
    }

    public function test_analyze_chat_id_only_response_is_unknown_result(): void
    {
        $result = RubikaBotClient::analyzeResponse(['chat_id' => 'abc-chat-01']);

        $this->assertSame(RubikaBotClient::STATE_UNKNOWN_RESULT, $result['state']);
        $this->assertNull($result['message_id']);
        $this->assertSame('no_success_evidence', $result['reason']);
        $this->assertFalse($result['permission_denied']);
    }

    public function test_analyze_empty_response_is_unknown_result(): void
    {
        $result = RubikaBotClient::analyzeResponse([]);

        $this->assertSame(RubikaBotClient::STATE_UNKNOWN_RESULT, $result['state']);
        $this->assertNull($result['message_id']);
    }

    public function test_analyze_does_not_assume_undocumented_candidate_structures(): void
    {
        // "result.id" is a Telegram-style shape Rubika never documented;
        // it must NOT be treated as success evidence.
        $result = RubikaBotClient::analyzeResponse(['result' => ['id' => '55']]);

        $this->assertSame(RubikaBotClient::STATE_UNKNOWN_RESULT, $result['state']);
        $this->assertNull($result['message_id']);
    }

    public function test_extract_message_id_reads_nested_data_message_id(): void
    {
        $this->assertSame('777001', RubikaBotClient::extractMessageId([
            'status' => 'OK',
            'data' => ['message_id' => '777001'],
        ]));
        $this->assertSame('55', RubikaBotClient::extractMessageId([
            'data' => ['message_id' => 55],
        ]));
    }

    public function test_extract_message_id_does_not_read_nested_id_alias(): void
    {
        // Only the documented key is honored at the nested level.
        $this->assertNull(RubikaBotClient::extractMessageId(['data' => ['id' => '123']]));
    }

    public function test_extract_message_id_rejects_nested_error_envelope(): void
    {
        $this->assertNull(RubikaBotClient::extractMessageId([
            'status' => 'error',
            'data' => ['code' => 403, 'error' => 'denied', 'message_id' => '9001'],
        ]));
    }

    public function test_has_message_id_detects_nested_envelope(): void
    {
        $this->assertTrue(RubikaBotClient::hasMessageId([
            'status' => 'OK',
            'data' => ['message_id' => '777001'],
        ]));
        $this->assertFalse(RubikaBotClient::hasMessageId(['status' => 'OK', 'data' => []]));
    }

    public function test_status_value_normalizes_and_rejects_non_strings(): void
    {
        $this->assertSame('ok', RubikaBotClient::statusValue(['status' => ' OK ']));
        $this->assertNull(RubikaBotClient::statusValue(['status' => 200]));
        $this->assertNull(RubikaBotClient::statusValue([]));
    }

    public function test_has_success_status_matches_only_documented_words(): void
    {
        $this->assertTrue(RubikaBotClient::hasSuccessStatus(['status' => 'Success']));
        $this->assertFalse(RubikaBotClient::hasSuccessStatus(['status' => 'unknown_state']));
    }

    public function test_has_failure_status_matches_failure_words(): void
    {
        $this->assertTrue(RubikaBotClient::hasFailureStatus(['status' => 'Error: timeout']));
        $this->assertFalse(RubikaBotClient::hasFailureStatus(['status' => 'OK']));
    }

    public function test_classify_transport_exception_maps_http_4xx_5xx_to_api_rejected(): void
    {
        $denied = RubikaBotClient::classifyTransportException(
            new RuntimeException('rubika: HTTP 403 calling sendMessage — denied')
        );
        $this->assertSame(RubikaBotClient::STATE_API_REJECTED, $denied['state']);
        $this->assertSame(403, $denied['http_status']);
        $this->assertTrue($denied['permission_denied']);
        $this->assertSame('http_403', $denied['reason']);

        $invalidToken = RubikaBotClient::classifyTransportException(
            new RuntimeException('rubika: HTTP 401 calling sendMessage — invalid token')
        );
        $this->assertSame(RubikaBotClient::STATE_API_REJECTED, $invalidToken['state']);
        $this->assertSame(401, $invalidToken['http_status']);
        $this->assertFalse($invalidToken['permission_denied']);

        $server = RubikaBotClient::classifyTransportException(
            new RuntimeException('rubika: HTTP 500 calling sendMessage — boom')
        );
        $this->assertSame(RubikaBotClient::STATE_API_REJECTED, $server['state']);
        $this->assertSame(500, $server['http_status']);
        $this->assertFalse($server['permission_denied']);
    }

    public function test_classify_transport_exception_maps_network_and_non_json_to_transport_failure(): void
    {
        $timeout = RubikaBotClient::classifyTransportException(
            new RuntimeException('rubika: connection error calling sendMessage: cURL error 28: Operation timed out')
        );
        $this->assertSame(RubikaBotClient::STATE_TRANSPORT_FAILURE, $timeout['state']);
        $this->assertNull($timeout['http_status']);
        $this->assertNull($timeout['message_id']);
        $this->assertFalse($timeout['permission_denied']);
        $this->assertNotNull($timeout['reason']);

        $nonJson = RubikaBotClient::classifyTransportException(
            new RuntimeException('rubika: non-JSON response from sendMessage (HTTP 200)')
        );
        $this->assertSame(RubikaBotClient::STATE_TRANSPORT_FAILURE, $nonJson['state']);
        $this->assertNull($nonJson['http_status']);
    }

    public function test_diagnose_reports_nested_envelope_shape_and_status_flags(): void
    {
        $diagnostic = RubikaBotClient::diagnose([
            'status' => 'OK',
            'data' => ['message_id' => '777001'],
        ]);

        $this->assertTrue($diagnostic['has_success_status']);
        $this->assertFalse($diagnostic['has_failure_status']);
        $this->assertSame(['message_id'], $diagnostic['data_keys']);
        $this->assertTrue($diagnostic['has_message_id']);
        $this->assertSame([], $diagnostic['error_keys']);
    }

    public function test_diagnose_reports_nested_error_keys(): void
    {
        $diagnostic = RubikaBotClient::diagnose([
            'status' => 'error',
            'data' => ['code' => 403, 'error' => 'denied'],
        ]);

        $this->assertTrue($diagnostic['has_failure_status']);
        $this->assertFalse($diagnostic['has_success_status']);
        $this->assertSame(['data.code', 'data.error'], $diagnostic['error_keys']);
    }

    // ── CP-RUBIKA-MEDIA-UPLOAD-FIX: stages 1–2 (requestSendFile/upload) ──

    public function test_extract_field_reads_top_level_then_live_observed_nested_envelope(): void
    {
        // سطح اول (شکل مستند API)
        $this->assertSame(
            'https://upload.rubika.example/u/1',
            RubikaBotClient::extractField(['upload_url' => 'https://upload.rubika.example/u/1'], 'upload_url')
        );

        // توی data (پاکت دو سطحی مشاهده‌شده در پروتکشن)
        $this->assertSame(
            'https://upload.rubika.example/u/2',
            RubikaBotClient::extractField([
                'status' => 'OK',
                'data' => ['upload_url' => 'https://upload.rubika.example/u/2'],
            ], 'upload_url')
        );

        // int مثبت (مانند message_id) نرمال به string می‌شود
        $this->assertSame('9', RubikaBotClient::extractField(['file_id' => 9], 'file_id'));

        // مقادیر نامعتبر / غیاب → null
        $this->assertNull(RubikaBotClient::extractField(['upload_url' => ''], 'upload_url'));
        $this->assertNull(RubikaBotClient::extractField(['upload_url' => 0], 'upload_url'));
        $this->assertNull(RubikaBotClient::extractField(['data' => ['upload_url' => '']], 'upload_url'));
        $this->assertNull(RubikaBotClient::extractField([], 'upload_url'));
        $this->assertNull(RubikaBotClient::extractField(['other' => 'x'], 'upload_url'));
    }

    public function test_request_upload_url_reads_upload_url_from_live_observed_nested_envelope(): void
    {
        // ریشهٔ خطای «no upload_url» در انتشار واقعی: پاسخ موفق requestSendFile
        // داخل پاکت {"status":..., "data":{...}} بسته شده بود و parser قدیمی
        // فقط سطح اول را می‌خواند.
        Http::fake([
            'botapi.rubika.ir*' => Http::response([
                'status' => 'OK',
                'data' => ['upload_url' => 'https://upload.rubika.example/u/nested'],
            ]),
        ]);

        $client = new RubikaBotClient($this->token);
        $url = $client->requestUploadUrl('Image');

        $this->assertSame('https://upload.rubika.example/u/nested', $url);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://botapi.rubika.ir/v3/' . $this->token . '/requestSendFile'
                && $request->data()['type'] === 'Image';
        });
    }

    public function test_request_upload_url_preserves_api_rejection_error_envelope(): void
    {
        // HTTP 200 با پاکت خطای API: خطای واقعی API حفظ می‌شود —
        // تبدیل به خطای عمومی «no upload_url» نمی‌شود (M2).
        Http::fake([
            'botapi.rubika.ir*' => Http::response([
                'status' => 'error',
                'data' => ['code' => 13, 'error' => 'invalid token'],
            ]),
        ]);

        $client = new RubikaBotClient($this->token);

        try {
            $client->requestUploadUrl('Image');
            $this->fail('expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('API rejected requestSendFile', $e->getMessage());
            $this->assertStringContainsString('reason: error_envelope', $e->getMessage());
            $this->assertStringContainsString('status=error', $e->getMessage());
            $this->assertStringContainsString('data.code', $e->getMessage());
            $this->assertStringNotContainsString('no upload_url', $e->getMessage());
            // متن خطای API (مقدار «error») هرگز در پیام نشت نمی‌کند —
            // فقط نام کلیدها (M2: فقط ساختار خطا)
            $this->assertStringNotContainsString('invalid token', $e->getMessage());
        }
    }

    public function test_request_upload_url_preserves_api_rejection_failure_status(): void
    {
        // status شکست بدون کلید خطا → ردشوندگی واقعی API، با جزئیات status.
        Http::fake([
            'botapi.rubika.ir*' => Http::response([
                'status' => 'Forbidden',
                'data' => [],
            ]),
        ]);

        $client = new RubikaBotClient($this->token);

        try {
            $client->requestUploadUrl('Image');
            $this->fail('expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('API rejected requestSendFile', $e->getMessage());
            $this->assertStringContainsString('reason: failure_status', $e->getMessage());
            $this->assertStringContainsString('status=forbidden', $e->getMessage());
            $this->assertStringNotContainsString('no upload_url', $e->getMessage());
        }
    }

    public function test_request_upload_url_non_json_response_is_distinct_error(): void
    {
        // HTTP 200 با بدنهٔ غیر-JSON (مثلاً خطای gateway): پیام مخصوص،
        // نه «no upload_url».
        Http::fake([
            'botapi.rubika.ir*' => Http::response('gateway error', 200),
        ]);

        $client = new RubikaBotClient($this->token);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-JSON response from requestSendFile (HTTP 200)');

        $client->requestUploadUrl('Image');
    }

    public function test_request_upload_url_missing_url_keeps_generic_substring_with_state(): void
    {
        // پاسخ بدون ردشوندگی اما بدون upload_url: خطای عمومی حفظ می‌شود
        // (سازگاری با مصرف‌کنندگان قدیمی) + حالت تحلیل برای تشخیص.
        Http::fake([
            'botapi.rubika.ir*' => Http::response(['some_other_field' => 1]),
        ]);

        $client = new RubikaBotClient($this->token);

        try {
            $client->requestUploadUrl('File');
            $this->fail('expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no upload_url in requestSendFile response', $e->getMessage());
            $this->assertStringContainsString('state: unknown_result', $e->getMessage());
        }
    }

    public function test_upload_file_reads_file_id_from_live_observed_nested_envelope(): void
    {
        $uploadUrl = 'https://upload.rubika.example/u/nested';

        Http::fake([
            $uploadUrl => Http::response([
                'status' => 'OK',
                'data' => ['file_id' => 'f-nested'],
            ]),
        ]);

        $client = new RubikaBotClient($this->token);
        $fileId = $client->uploadFile($uploadUrl, 'x', 'a.jpg', 'image/jpeg');

        $this->assertSame('f-nested', $fileId);
    }

    public function test_upload_file_preserves_api_rejection_on_2xx_error_envelope(): void
    {
        // HTTP 200 + پاکت خطا: ردشوندگی واقعی API گزارش می‌شود،
        // نه «no file_id in upload response» (M2).
        Http::fake([
            'upload.rubika.example*' => Http::response([
                'status' => 'error',
                'data' => ['code' => 14, 'error' => 'file too large'],
            ]),
        ]);

        $client = new RubikaBotClient($this->token);

        try {
            $client->uploadFile('https://upload.rubika.example/u/1', 'abc', 'a.mp4', 'video/mp4');
            $this->fail('expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('API rejected the file upload', $e->getMessage());
            $this->assertStringContainsString('reason: error_envelope', $e->getMessage());
            $this->assertStringContainsString('status=error', $e->getMessage());
            $this->assertStringNotContainsString('no file_id in upload response', $e->getMessage());
            $this->assertStringNotContainsString('file too large', $e->getMessage());
        }
    }

    public function test_upload_file_non_json_response_is_distinct_error(): void
    {
        Http::fake([
            'upload.rubika.example*' => Http::response('<html>502</html>', 200),
        ]);

        $client = new RubikaBotClient($this->token);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-JSON upload response (HTTP 200)');

        $client->uploadFile('https://upload.rubika.example/u/1', 'abc', 'a.jpg', 'image/jpeg');
    }

    public function test_upload_file_missing_file_id_keeps_generic_substring_with_state(): void
    {
        Http::fake([
            'upload.rubika.example*' => Http::response(['some' => 'payload']),
        ]);

        $client = new RubikaBotClient($this->token);

        try {
            $client->uploadFile('https://upload.rubika.example/u/1', 'abc', 'a.jpg', 'image/jpeg');
            $this->fail('expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no file_id in upload response', $e->getMessage());
            $this->assertStringContainsString('state: unknown_result', $e->getMessage());
        }
    }
}
