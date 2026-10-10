<?php

namespace Tests\Feature;

use App\Services\LlmTranslationService;
use App\Services\TranslationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesAiProviderSqlite;
use Tests\UsesChannelPosterSqlite;

class TranslationDriverTest extends TestCase
{
    use UsesAiProviderSqlite;
    use UsesChannelPosterSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAiProvidersTable();
        $this->createActiveAiProvider();
        config(['translation.driver' => 'llm']);
    }

    public function test_llm_driver_translates_text(): void
    {
        Http::fake(fn () => Http::response([
            'choices' => [['message' => ['content' => 'ترجمه: دنیا را سلام']]],
            'usage' => ['total_tokens' => 10],
        ], 200));

        $result = TranslationService::call('Hello world');

        $this->assertSame('دنیا را سلام', $result);

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);

            return str_contains($request->url(), '/v1/chat/completions')
                && str_contains($data['messages'][0]['content'], 'Hello world')
                && str_contains($data['messages'][0]['content'], 'فارسی (پارسی)')
                && $data['max_tokens'] === 1024
                && $data['temperature'] === 0.2;
        });
    }

    public function test_llm_failure_returns_original_text(): void
    {
        Http::fake(fn () => Http::response(['error' => 'boom'], 500));

        $result = TranslationService::call('Hello world');

        $this->assertSame('Hello world', $result);
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $result = TranslationService::call('');

        $this->assertSame('', $result);
    }

    public function test_clean_response_strips_prefixes_quotes_and_bullets(): void
    {
        $this->assertSame('متن نهایی', LlmTranslationService::cleanResponse('ترجمه: متن نهایی'));
        $this->assertSame('متن نهایی', LlmTranslationService::cleanResponse('«متن نهایی»'));
        $this->assertSame('متن نهایی', LlmTranslationService::cleanResponse('- متن نهایی'));
        $this->assertSame('متن نهایی', LlmTranslationService::cleanResponse('  "متن نهایی"  '));
    }

    public function test_build_prompt_contains_text_and_language(): void
    {
        $prompt = LlmTranslationService::buildPrompt('Hello world', 'fa');

        $this->assertStringContainsString('Hello world', $prompt);
        $this->assertStringContainsString('فارسی (پارسی)', $prompt);
    }
}
