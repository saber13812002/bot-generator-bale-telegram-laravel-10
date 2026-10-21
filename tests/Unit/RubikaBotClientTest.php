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
}
