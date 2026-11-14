<?php

namespace Tests\Unit;

use App\Services\Ai\AiErrorRedactor;
use PHPUnit\Framework\TestCase;

class AiErrorRedactorTest extends TestCase
{
    public function test_redacts_sk_token_anywhere_in_string(): void
    {
        $out = AiErrorRedactor::redact('failed with sk-abcdefghijklmnop123456 in payload');
        $this->assertStringNotContainsString('sk-abcdefghijklmnop123456', $out);
        $this->assertStringContainsString('***REDACTED***', $out);
    }

    public function test_redacts_received_api_key_from_litellm_error(): void
    {
        $input = 'Authentication Error, Invalid proxy server token passed. '
            . 'Received API Key: sk-litellm-9f8e7d6c5b4a, '
            . 'Key Hash (Token): abc123def456';
        $out = AiErrorRedactor::redact($input);

        $this->assertStringNotContainsString('sk-litellm-9f8e7d6c5b4a', $out);
        $this->assertStringNotContainsString('abc123def456', $out);
        $this->assertStringContainsString('***REDACTED***', $out);
    }

    public function test_redacts_key_hash_with_parenthesized_token(): void
    {
        $out = AiErrorRedactor::redact('Key Hash (Token): 0f1e2d3c4b5a');
        $this->assertStringNotContainsString('0f1e2d3c4b5a', $out);
        $this->assertStringContainsString('***REDACTED***', $out);
    }

    public function test_redacts_bearer_header(): void
    {
        $out = AiErrorRedactor::redact('Authorization: Bearer sk-proj-xyz123abc');
        $this->assertStringNotContainsString('sk-proj-xyz123abc', $out);
    }

    public function test_plain_text_without_key_is_unchanged(): void
    {
        $text = 'اتصال با موفقیت برقرار شد.';
        $this->assertSame($text, AiErrorRedactor::redact($text));
    }

    public function test_redacts_nested_array(): void
    {
        $out = AiErrorRedactor::redactValue([
            'error' => ['message' => 'Key Hash (Token): secret123hash'],
            'code'  => 401,
        ]);

        $this->assertIsArray($out);
        $this->assertStringNotContainsString('secret123hash', $out['error']['message']);
        $this->assertSame(401, $out['code']);
    }
}
