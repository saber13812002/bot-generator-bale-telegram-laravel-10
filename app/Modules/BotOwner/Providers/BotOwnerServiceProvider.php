<?php

namespace App\Modules\BotOwner\Providers;

use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerDashboardServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerOtpSessionRepositoryInterface;
use App\Modules\BotOwner\Contracts\BotOwnerProServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerRepositoryInterface;
use App\Modules\BotOwner\Repositories\BotOwnerOtpSessionRepository;
use App\Modules\BotOwner\Repositories\BotOwnerRepository;
use App\Modules\BotOwner\Services\BotOwnerAuthService;
use App\Modules\BotOwner\Services\BotOwnerDashboardService;
use App\Modules\BotOwner\Services\BotOwnerProService;
use Illuminate\Support\ServiceProvider;

class BotOwnerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BotOwnerRepositoryInterface::class, BotOwnerRepository::class);
        $this->app->bind(BotOwnerOtpSessionRepositoryInterface::class, BotOwnerOtpSessionRepository::class);
        $this->app->bind(BotOwnerAuthServiceInterface::class, BotOwnerAuthService::class);
        $this->app->bind(BotOwnerDashboardServiceInterface::class, BotOwnerDashboardService::class);
        $this->app->bind(BotOwnerProServiceInterface::class, BotOwnerProService::class);
    }
}
