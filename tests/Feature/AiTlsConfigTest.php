<?php

namespace Tests\Feature;

use App\Services\Ai\EmbeddingAdapter;
use App\Services\Ai\TtsAdapter;
use App\Services\Ai\TranscriptionAdapter;
use App\Services\Ai\VisionAdapter;
use Tests\TestCase;

class AiTlsConfigTest extends TestCase
{
    public function test_strict_tls_enabled_by_default_with_repo_ca_bundle(): void
    {
        // config/ai.php: verify = AI_VERIFY_SSL (default true) → مسیر bundle رپوزیتری
        $verify = config('ai.http_options.verify');

        $this->assertNotSame(false, $verify, 'Strict TLS نباید به‌صورت پیش‌فرض غیرفعال باشد');
        $this->assertIsString($verify);
        $this->assertFileExists($verify);
    }

    public function test_verify_expression_yields_false_when_flag_disabled(): void
    {
        // همان منطق config/ai.php در حالت AI_VERIFY_SSL=0
        putenv('AI_VERIFY_SSL=0');
        $verify = env('AI_VERIFY_SSL', true)
            ? (env('AI_CA_BUNDLE_PATH') ?: base_path('storage/certs/ismc-ai-ca-bundle.pem'))
            : false;
        putenv('AI_VERIFY_SSL');

        $this->assertSame(false, $verify);
    }

    public function test_ca_bundle_exists_and_contains_ca_certs(): void
    {
        $bundle = base_path('storage/certs/ismc-ai-ca-bundle.pem');
        $this->assertFileExists($bundle);

        $content = file_get_contents($bundle);
        // حداقل root + intermediate Certum
        $this->assertGreaterThanOrEqual(2, substr_count($content, 'BEGIN CERTIFICATE'));
    }

    public function test_capability_model_config_keys_exist(): void
    {
        $this->assertArrayHasKey('vision_model', config('ai'));
        $this->assertArrayHasKey('asr_model', config('ai'));
        $this->assertArrayHasKey('embedding_model', config('ai'));
        $this->assertArrayHasKey('tts_model', config('ai'));
        $this->assertArrayHasKey('vision_ocr_prompt', config('ai'));
    }

    public function test_capability_adapters_instantiate_without_db(): void
    {
        $this->assertInstanceOf(VisionAdapter::class, new VisionAdapter());
        $this->assertInstanceOf(TranscriptionAdapter::class, new TranscriptionAdapter());
        $this->assertInstanceOf(EmbeddingAdapter::class, new EmbeddingAdapter());
        $this->assertInstanceOf(TtsAdapter::class, new TtsAdapter());
    }

    public function test_embedding_adapter_reports_model_name(): void
    {
        $adapter = new EmbeddingAdapter(null, 'text-embedding-3-small');
        $this->assertSame('text-embedding-3-small', $adapter->model());
    }
}
