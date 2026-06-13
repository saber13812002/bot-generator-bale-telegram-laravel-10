<?php

namespace Tests\Unit\Modules\BaleOtp;

use App\Modules\BaleOtp\Contracts\BaleOtpAuthServiceInterface;
use App\Modules\BaleOtp\Exceptions\BaleOtpException;
use App\Modules\BaleOtp\Services\BaleOtpSendService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class BaleOtpSendServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sends_otp_successfully(): void
    {
        $auth = Mockery::mock(BaleOtpAuthServiceInterface::class);
        $auth->shouldReceive('getAccessToken')->once()->andReturn('bearer-token');

        Http::fake([
            'safir.bale.ai/api/v2/send_otp' => Http::response(['balance' => 985], 200),
        ]);

        $service = new BaleOtpSendService($auth);
        $result = $service->sendOtp('09123456789', 123456);

        $this->assertSame(['balance' => 985], $result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://safir.bale.ai/api/v2/send_otp'
                && $request['phone'] === '989123456789'
                && $request['otp'] === 123456;
        });
    }

    public function test_throws_when_phone_has_no_bale_account(): void
    {
        $auth = Mockery::mock(BaleOtpAuthServiceInterface::class);
        $auth->shouldReceive('getAccessToken')->once()->andReturn('bearer-token');

        Http::fake([
            'safir.bale.ai/api/v2/send_otp' => Http::response([
                'type' => 3,
                'code' => 17,
                'message' => 'this phone does not have an account in Bale',
            ], 404),
        ]);

        $this->expectException(BaleOtpException::class);
        $this->expectExceptionMessage('this phone does not have an account in Bale');

        (new BaleOtpSendService($auth))->sendOtp('09123456789', 123456);
    }
}
