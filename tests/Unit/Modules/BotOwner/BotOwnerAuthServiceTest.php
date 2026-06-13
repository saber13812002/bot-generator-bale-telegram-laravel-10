<?php

namespace Tests\Unit\Modules\BotOwner;

use App\Modules\BotOwner\Repositories\BotOwnerOtpSessionRepository;
use App\Modules\BotOwner\Repositories\BotOwnerRepository;
use App\Modules\BotOwner\Services\BotOwnerAuthService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class BotOwnerAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_verify_otp_creates_owner_and_logs_in(): void
    {
        $sendService = Mockery::mock(\App\Modules\BaleOtp\Contracts\BaleOtpSendServiceInterface::class);

        $service = new BotOwnerAuthService(
            new BotOwnerRepository(),
            new BotOwnerOtpSessionRepository(),
            $sendService,
        );

        (new BotOwnerOtpSessionRepository())->createSession(
            '989123456789',
            Hash::make('654321'),
            Carbon::now()->addMinutes(5),
        );

        $verify = $service->verifyOtp('09123456789', '654321');
        $this->assertTrue($verify['success']);
        $this->assertSame('989123456789', $verify['bot_owner']->phone);
    }

    public function test_send_otp_handles_no_bale_account(): void
    {
        $sendService = Mockery::mock(\App\Modules\BaleOtp\Contracts\BaleOtpSendServiceInterface::class);
        $sendService->shouldReceive('sendOtp')->once()
            ->andThrow(\App\Modules\BaleOtp\Exceptions\BaleOtpException::noBaleAccount());

        $service = new BotOwnerAuthService(
            new BotOwnerRepository(),
            new BotOwnerOtpSessionRepository(),
            $sendService,
        );

        $result = $service->sendOtp('09123456789');
        $this->assertFalse($result['success']);
    }
}
