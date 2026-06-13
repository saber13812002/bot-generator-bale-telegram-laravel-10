<?php

namespace Tests\Unit\Modules\BaleOtp;

use App\Modules\BaleOtp\Exceptions\BaleOtpException;
use App\Modules\BaleOtp\Services\BaleOtpAuthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BaleOtpAuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'bale-otp.client_id' => 'test-client',
            'bale-otp.client_secret' => 'test-secret',
            'bale-otp.base_url' => 'https://safir.bale.ai/api/v2',
            'bale-otp.token_cache_key' => 'bale_safir_access_token_test',
        ]);
    }

    public function test_fetches_and_caches_access_token(): void
    {
        Http::fake([
            'safir.bale.ai/api/v2/auth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 43200,
                'scope' => 'read',
                'token_type' => 'bearer',
            ], 200),
        ]);

        $service = new BaleOtpAuthService();
        $token = $service->getAccessToken();

        $this->assertSame('token-abc', $token);
        $this->assertSame('token-abc', $service->getAccessToken());

        Http::assertSentCount(1);
    }

    public function test_throws_on_auth_failure(): void
    {
        Http::fake([
            'safir.bale.ai/api/v2/auth/token' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed',
            ], 401),
        ]);

        $this->expectException(BaleOtpException::class);
        $this->expectExceptionMessage('Client authentication failed');

        (new BaleOtpAuthService())->getAccessToken();
    }
}
