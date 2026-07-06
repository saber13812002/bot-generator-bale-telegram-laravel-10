<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        'App\Models\ContentCategory' => 'App\Policies\ContentCategoryPolicy',
        'App\Models\ContentItem' => 'App\Policies\ContentItemPolicy',
        'App\Models\ContentAsset' => 'App\Policies\ContentAssetPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
