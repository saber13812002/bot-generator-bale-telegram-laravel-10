<?php

namespace App\Modules\AdminBots\Providers;

use App\Modules\AdminBots\Services\AdminBotsService;
use Illuminate\Support\ServiceProvider;

class AdminBotsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminBotsService::class);
    }
}
