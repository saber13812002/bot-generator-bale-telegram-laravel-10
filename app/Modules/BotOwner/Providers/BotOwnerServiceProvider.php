<?php

namespace App\Modules\BotOwner\Providers;

use App\Modules\BotOwner\Contracts\BotAdminKieServiceInterface;
use App\Modules\BotOwner\Contracts\BotCategoryServiceInterface;
use App\Modules\BotOwner\Contracts\BotItemsServiceInterface;
use App\Modules\BotOwner\Contracts\BotLibraryServiceInterface;
use App\Modules\BotOwner\Contracts\BotManageServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerDashboardServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerOtpSessionRepositoryInterface;
use App\Modules\BotOwner\Contracts\BotOwnerProServiceInterface;
use App\Modules\BotOwner\Contracts\BotOwnerRepositoryInterface;
use App\Modules\BotOwner\Contracts\BotPlanServiceInterface;
use App\Modules\BotOwner\Contracts\BotUploadsServiceInterface;
use App\Modules\BotOwner\Repositories\BotOwnerOtpSessionRepository;
use App\Modules\BotOwner\Repositories\BotOwnerRepository;
use App\Modules\BotOwner\Services\BotAdminKieService;
use App\Modules\BotOwner\Services\BotCategoryService;
use App\Modules\BotOwner\Services\BotItemsService;
use App\Modules\BotOwner\Services\BotLibraryService;
use App\Modules\BotOwner\Services\BotManageService;
use App\Modules\BotOwner\Services\BotOwnerAuthService;
use App\Modules\BotOwner\Services\BotOwnerDashboardService;
use App\Modules\BotOwner\Services\BotOwnerProService;
use App\Modules\BotOwner\Services\BotPlanService;
use App\Modules\BotOwner\Services\BotUploadsService;
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

        // New management service bindings
        $this->app->bind(BotLibraryServiceInterface::class, BotLibraryService::class);
        $this->app->bind(BotManageServiceInterface::class, BotManageService::class);
        $this->app->bind(BotAdminKieServiceInterface::class, BotAdminKieService::class);
        $this->app->bind(BotPlanServiceInterface::class, BotPlanService::class);
        $this->app->bind(BotCategoryServiceInterface::class, BotCategoryService::class);
        $this->app->bind(BotItemsServiceInterface::class, BotItemsService::class);
        $this->app->bind(BotUploadsServiceInterface::class, BotUploadsService::class);
    }
}
