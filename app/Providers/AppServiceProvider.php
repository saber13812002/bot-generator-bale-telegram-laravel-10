<?php

namespace App\Providers;

use App\Interfaces\Repositories\WeatherOpenWeatherApiRepository;
use App\Interfaces\Repositories\WeatherTomorrowApiRepository;
use App\Interfaces\Services\WeatherOpenWeatherMapApiService;
use App\Interfaces\Services\WeatherTomorrowApiService;
use App\Repositories\WeatherOpenWeatherApiRepositoryImpl;
use App\Repositories\WeatherTomorrowApiRepositoryImpl;
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

        // Services
        $this->app->bind(WeatherTomorrowApiService::class, WeatherTomorrowApiServiceImpl::class);
        $this->app->bind(WeatherOpenWeatherMapApiService::class, WeatherOpenWeatherMapApiServiceImpl::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
