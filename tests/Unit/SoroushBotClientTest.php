<?php

namespace Tests\Unit;

use App\Services\SoroushBotClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for SoroushBotClient (Soroush Plus Bot API) — MOCK_TESTED.
 *
 * All requests are faked with Http::fake; no live calls.
 * Source of truth: docs/features/platform-api-research.md.
 */
class SoroushBotClientTest extends TestCase
{
    private string $token = '123456:ABC-DEF-GHIJKLMNOPQRSTUVWXYZ-0123456789';

    public function test_call_posts_to_splus_base_url_and_returns_envelope(): void
    {
        Http::fake([
            'api.splus.ir*' => Http::response([
                'ok' => true,
                'result' => ['id' => 42, 'name' => 'TestBot'],
            ]),
        ]);

        $client = new SoroushBotClient($this->token);
        $data = $client->call('getMe');

        $this->assertTrue(SoroushBotClient::isOk($data));
        $this->assertSame(42, $data['result']['id']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.splus.ir/bot' . $this->token . '/getMe'
                && $request->method() === 'POST'
                && ($request->headers()['Accept'][0] ?? null) === 'application/json';
        });
    }

    public function test_isok_distinguishes_true_from_other_values(): void
    {
        $this->assertTrue(SoroushBotClient::isOk(['ok' => true]));
        $this->assertFalse(SoroushBotClient::isOk(['ok' => false]));
        $this->assertFalse(SoroushBotClient::isOk([]));
        $this->assertFalse(SoroushBotClient::isOk(['ok' => '1']));
    }

    public function test_sendmessage_sends_chat_id_and_text(): void
    {
        Http::fake([
            'api.splus.ir*' => Http::response(['ok' => true, 'result' => ['message_id' => 7]]),
        ]);

        $client = new SoroushBotClient($this->token);
        $data = $client->sendMessage('-1001234567890', 'سلام سروش');

        $this->assertTrue(SoroushBotClient::isOk($data));
        $this->assertSame(7, $data['result']['message_id']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.splus.ir/bot' . $this->token . '/sendMessage'
                && $request->data()['chat_id'] === '-1001234567890'
                && $request->data()['text'] === 'سلام سروش';
        });
    }

    public function test_sendmedia_maps_field_to_method_and_includes_caption(): void
    {
        Http::fake([
            'api.splus.ir*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]]),
        ]);

        $client = new SoroushBotClient($this->token);
        $client->sendMedia('photo', '@mychannel', 'file-id-xyz', 'کپشن');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.splus.ir/bot' . $this->token . '/sendPhoto'
                && $request->data()['photo'] === 'file-id-xyz'
                && $request->data()['chat_id'] === '@mychannel'
                && $request->data()['caption'] === 'کپشن';
        });
    }

    public function test_sendmedia_defaults_to_send_document(): void
    {
        Http::fake([
            'api.splus.ir*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]]),
        ]);

        $client = new SoroushBotClient($this->token);
        $client->sendMedia('document', '12345', 'file-id-doc');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sendDocument'));
    }

    public function test_webhook_methods_call_expected_endpoints(): void
    {
        Http::fake([
            'api.splus.ir*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $client = new SoroushBotClient($this->token);
        $client->setWebhook('https://example.com/hook', ['drop_pending_updates' => true]);
        $client->deleteWebhook(true);
        $client->getWebhookInfo();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/setWebhook')
            && $request->data()['url'] === 'https://example.com/hook');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/deleteWebhook')
            && $request->data()['drop_pending_updates'] === true);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/getWebhookInfo'));
    }

    public function test_non_json_response_throws_runtime_exception(): void
    {
        Http::fake([
            'api.splus.ir*' => Http::response('not-json', 200),
        ]);

        $client = new SoroushBotClient($this->token);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-JSON response from getMe');

        $client->getMe();
    }

    public function test_connection_error_throws_wrapped_runtime_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('network down'));

        $client = new SoroushBotClient($this->token);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('soroush: connection error calling getMe');

        $client->getMe();
    }

    public function test_error_envelope_is_reported_not_ok(): void
    {
        Http::fake([
            'api.splus.ir*' => Http::response([
                'ok' => false,
                'error_code' => 400,
                'description' => 'bad message_id',
            ]),
        ]);

        $client = new SoroushBotClient($this->token);
        $data = $client->call('sendMessage', ['chat_id' => 'x', 'text' => 'y']);

        $this->assertFalse(SoroushBotClient::isOk($data));
        $this->assertSame('bad message_id', $data['description']);
    }
}
