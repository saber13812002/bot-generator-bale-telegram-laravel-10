<?php

namespace App\Modules\BaleOtp\Providers;

use App\Modules\BaleOtp\Contracts\BaleOtpAuthServiceInterface;
use App\Modules\BaleOtp\Contracts\BaleOtpSendServiceInterface;
use App\Modules\BaleOtp\Services\BaleOtpAuthService;
use App\Modules\BaleOtp\Services\BaleOtpSendService;
use Illuminate\Support\ServiceProvider;

class BaleOtpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/bale-otp.php'),
            'bale-otp'
        );

        $this->app->bind(BaleOtpAuthServiceInterface::class, BaleOtpAuthService::class);
        $this->app->bind(BaleOtpSendServiceInterface::class, BaleOtpSendService::class);
    }
}
