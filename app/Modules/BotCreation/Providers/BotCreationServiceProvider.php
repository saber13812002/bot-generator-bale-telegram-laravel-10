<?php

namespace App\Modules\BotCreation\Providers;

use App\Modules\BotCreation\Contracts\BotCreationWorkflowInterface;
use App\Modules\BotCreation\Services\BotCreationWorkflowService;
use App\Modules\BotCreation\Services\ChatRenderer;
use App\Modules\BotCreation\Services\FieldRegistry;
use App\Modules\BotCreation\Services\WebRenderer;
use Illuminate\Support\ServiceProvider;

class BotCreationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FieldRegistry::class, function () {
            $registry = new FieldRegistry();
            $registry->registerDefaults();
            return $registry;
        });

        $this->app->bind(BotCreationWorkflowInterface::class, BotCreationWorkflowService::class);

        $this->app->bind(ChatRenderer::class, function ($app) {
            return new ChatRenderer($app->make(FieldRegistry::class));
        });

        $this->app->bind(WebRenderer::class, function ($app) {
            return new WebRenderer($app->make(FieldRegistry::class));
        });
    }
}
