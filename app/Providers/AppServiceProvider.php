<?php

namespace App\Providers;

use App\Interfaces\Repositories\ContentRepository;
use App\Interfaces\Repositories\HadithApiRepository;
use App\Interfaces\Repositories\MissionRepository;
use App\Interfaces\Repositories\NahjRepository;
use App\Interfaces\Repositories\PrayerEstimateRepository;
use App\Interfaces\Repositories\PrayerRecordRepository;
use App\Interfaces\Repositories\WeatherOpenWeatherApiRepository;
use App\Interfaces\Repositories\WeatherTomorrowApiRepository;
use App\Interfaces\Services\ContentService;
use App\Interfaces\Services\HadithApiService;
use App\Interfaces\Services\MissionService;
use App\Interfaces\Services\NahjService;
use App\Interfaces\Services\PrayerBotService;
use App\Interfaces\Services\QuranBotUserRankingService;
use App\Interfaces\Services\WeatherOpenWeatherMapApiService;
use App\Interfaces\Services\WeatherTomorrowApiService;
use App\Repositories\ContentRepositoryImpl;
use App\Repositories\HadithApiRepositoryImpl;
use App\Repositories\MissionRepositoryImpl;
use App\Repositories\NahjRepositoryImpl;
use App\Repositories\PrayerEstimateRepositoryImpl;
use App\Repositories\PrayerRecordRepositoryImpl;
use App\Repositories\WeatherOpenWeatherApiRepositoryImpl;
use App\Repositories\WeatherTomorrowApiRepositoryImpl;
use App\Services\ContentServiceImpl;
use App\Services\HadithApiServiceImpl;
use App\Services\MissionServiceImpl;
use App\Services\NahjServiceImpl;
use App\Services\PrayerBotServiceImpl;
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

        $this->app->bind(MissionRepository::class, MissionRepositoryImpl::class);
        $this->app->bind(ContentRepository::class, ContentRepositoryImpl::class);

        $this->app->bind(PrayerRecordRepository::class, PrayerRecordRepositoryImpl::class);
        $this->app->bind(PrayerEstimateRepository::class, PrayerEstimateRepositoryImpl::class);

        // Services
        $this->app->bind(WeatherTomorrowApiService::class, WeatherTomorrowApiServiceImpl::class);
        $this->app->bind(WeatherOpenWeatherMapApiService::class, WeatherOpenWeatherMapApiServiceImpl::class);

        $this->app->bind(HadithApiService::class, HadithApiServiceImpl::class);
        $this->app->bind(NahjService::class, NahjServiceImpl::class);

        $this->app->bind(QuranBotUserRankingService::class, QuranBotUserRankingServiceImpl::class);

        $this->app->bind(MissionService::class, MissionServiceImpl::class);
        $this->app->bind(ContentService::class, ContentServiceImpl::class);

        $this->app->bind(PrayerBotService::class, PrayerBotServiceImpl::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
