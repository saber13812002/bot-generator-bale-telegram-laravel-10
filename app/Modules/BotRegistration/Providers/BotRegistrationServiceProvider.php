<?php

namespace App\Modules\BotRegistration\Providers;

use App\Modules\BotRegistration\Contracts\BotRegistrationServiceInterface;
use App\Modules\BotRegistration\Services\BotRegistrationService;
use Illuminate\Support\ServiceProvider;

class BotRegistrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BotRegistrationServiceInterface::class, BotRegistrationService::class);
    }
}
