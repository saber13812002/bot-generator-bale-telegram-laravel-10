<?php

namespace App\Providers;

use App\Interfaces\Repositories\HadithApiRepository;
use App\Interfaces\Repositories\NahjRepository;
use App\Interfaces\Repositories\WeatherOpenWeatherApiRepository;
use App\Interfaces\Repositories\WeatherTomorrowApiRepository;
use App\Interfaces\Services\HadithApiService;
use App\Interfaces\Services\NahjService;
use App\Interfaces\Services\QuranBotUserRankingService;
use App\Interfaces\Services\WeatherOpenWeatherMapApiService;
use App\Interfaces\Services\WeatherTomorrowApiService;
use App\Repositories\HadithApiRepositoryImpl;
use App\Repositories\NahjRepositoryImpl;
use App\Repositories\WeatherOpenWeatherApiRepositoryImpl;
use App\Repositories\WeatherTomorrowApiRepositoryImpl;
use App\Services\HadithApiServiceImpl;
use App\Services\NahjServiceImpl;
use App\Services\QuranBotUserRankingServiceImpl;
use App\Services\WeatherOpenWeatherMapApiServiceImpl;
use App\Services\WeatherTomorrowApiServiceImpl;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Repositories
        $this->app->bind(WeatherTomorrowApiRepository::class, WeatherTomorrowApiRepositoryImpl::class);
        $this->app->bind(WeatherOpenWeatherApiRepository::class, WeatherOpenWeatherApiRepositoryImpl::class);

        $this->app->bind(HadithApiRepository::class, HadithApiRepositoryImpl::class);
        $this->app->bind(NahjRepository::class, NahjRepositoryImpl::class);

        // Services
        $this->app->bind(WeatherTomorrowApiService::class, WeatherTomorrowApiServiceImpl::class);
        $this->app->bind(WeatherOpenWeatherMapApiService::class, WeatherOpenWeatherMapApiServiceImpl::class);

        $this->app->bind(HadithApiService::class, HadithApiServiceImpl::class);
        $this->app->bind(NahjService::class, NahjServiceImpl::class);

        $this->app->bind(QuranBotUserRankingService::class, QuranBotUserRankingServiceImpl::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
