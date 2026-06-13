<?php

namespace Tests\Feature\Modules\BotOwner;

use App\Modules\BaleOtp\Contracts\BaleOtpSendServiceInterface;
use App\Modules\BotOwner\Models\BotOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class OtpLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_intro_page_loads(): void
    {
        $response = $this->get('/bots');
        $response->assertStatus(200);
        $response->assertSee('ساخت ربات اختصاصی شما', false);
    }

    public function test_login_and_dashboard_flow(): void
    {
        $sendService = Mockery::mock(BaleOtpSendServiceInterface::class);
        $sendService->shouldReceive('sendOtp')->andReturn(['balance' => 100]);
        $this->app->instance(BaleOtpSendServiceInterface::class, $sendService);

        $this->post('/bots/otp/send', ['phone' => '09123456789'])
            ->assertRedirect()
            ->assertSessionHas('otp_sent', true);

        $session = \App\Modules\BotOwner\Models\BotOwnerOtpSession::where('phone', '989123456789')->first();
        $session->otp_hash = Hash::make('654321');
        $session->save();

        $this->post('/bots/otp/verify', [
            'phone' => '09123456789',
            'otp' => '654321',
        ])->assertRedirect(route('bot-owner.dashboard'));

        $this->get('/bots/dashboard')
            ->assertStatus(200)
            ->assertSee('989123456789');
    }
}
