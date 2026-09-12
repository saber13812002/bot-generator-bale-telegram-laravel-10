<?php

namespace App\Providers;

use App\Interfaces\Repositories\BookRepository;
use App\Interfaces\Repositories\BookPageScanRepository;
use App\Interfaces\Repositories\BookUserScoreRepository;
use App\Interfaces\Repositories\BookDraftRepository;
use App\Interfaces\Repositories\BookScanMissionRepository;
use App\Interfaces\Repositories\ContentRepository;
use App\Interfaces\Repositories\HadithApiRepository;
use App\Interfaces\Repositories\LibraryRepository;
use App\Interfaces\Repositories\MissionRepository;
use App\Interfaces\Repositories\NahjRepository;
use App\Interfaces\Repositories\PrayerEstimateRepository;
use App\Interfaces\Repositories\PrayerRecordRepository;
use App\Interfaces\Repositories\PoemLikeRepository;
use App\Interfaces\Repositories\PoemLineRepository;
use App\Interfaces\Repositories\PoemRepository;
use App\Interfaces\Repositories\PoemSuggestionRepository;
use App\Interfaces\Repositories\PoemVersionRepository;
use App\Interfaces\Repositories\WeatherOpenWeatherApiRepository;
use App\Interfaces\Repositories\WeatherTomorrowApiRepository;
use App\Interfaces\Services\BookGamificationService;
use App\Interfaces\Services\BookPixelService;
use App\Interfaces\Services\BookPublishingService;
use App\Interfaces\Services\BookDraftService;
use App\Interfaces\Services\BookScanMissionService;
use App\Interfaces\Services\BookStatisticsService;
use App\Interfaces\Services\ContentService;
use App\Interfaces\Services\ContentSubmissionService;
use App\Interfaces\Services\EmailService;
use App\Interfaces\Services\BookLibraryDeliveryService;
use App\Interfaces\Services\BookLibraryPlanService;
use App\Interfaces\Services\BookLibraryService;
use App\Interfaces\Services\ContentDeliveryService;
use App\Interfaces\Services\ContentQueueService;
use App\Interfaces\Services\HadithApiService;
use App\Interfaces\Services\LibraryMilestoneService;
use App\Interfaces\Services\MissionService;
use App\Interfaces\Services\NahjService;
use App\Interfaces\Services\PrayerBotService;
use App\Interfaces\Services\QuranBotUserRankingService;
use App\Interfaces\Services\ProService;
use App\Interfaces\Services\ReverseGeocodingService;
use App\Interfaces\Services\WeatherAlertService;
use App\Interfaces\Services\WeatherComparisonService;
use App\Interfaces\Services\MawkibFinderService;
use App\Interfaces\Services\PoemBotService;
use App\Interfaces\Services\ChannelPosterBotService;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Interfaces\Services\GrowthCompanionService;
use App\Interfaces\Services\GrowthLlmProvider;
use App\Interfaces\Services\GrowthMessengerFactory;
use App\Interfaces\Services\MpContactBotService;
use App\Interfaces\Services\WeatherOpenWeatherMapApiService;
use App\Interfaces\Services\WeatherTomorrowApiService;
use App\Repositories\BookRepositoryImpl;
use App\Repositories\BookPageScanRepositoryImpl;
use App\Repositories\BookUserScoreRepositoryImpl;
use App\Repositories\BookDraftRepositoryImpl;
use App\Repositories\BookScanMissionRepositoryImpl;
use App\Repositories\ContentRepositoryImpl;
use App\Repositories\LibraryRepositoryImpl;
use App\Repositories\HadithApiRepositoryImpl;
use App\Repositories\MissionRepositoryImpl;
use App\Repositories\NahjRepositoryImpl;
use App\Repositories\PrayerEstimateRepositoryImpl;
use App\Repositories\PrayerRecordRepositoryImpl;
use App\Repositories\PoemLikeRepositoryImpl;
use App\Repositories\PoemLineRepositoryImpl;
use App\Repositories\PoemRepositoryImpl;
use App\Repositories\PoemSuggestionRepositoryImpl;
use App\Repositories\PoemVersionRepositoryImpl;
use App\Repositories\WeatherOpenWeatherApiRepositoryImpl;
use App\Repositories\WeatherTomorrowApiRepositoryImpl;
use App\Services\BookGamificationServiceImpl;
use App\Services\BookPixelServiceImpl;
use App\Services\BookPublishingServiceImpl;
use App\Services\BookDraftServiceImpl;
use App\Services\BookScanMissionServiceImpl;
use App\Services\BookStatisticsServiceImpl;
use App\Services\ContentServiceImpl;
use App\Services\ContentSubmissionServiceImpl;
use App\Services\MawkibFinderServiceImpl;
use App\Services\BookLibraryDeliveryServiceImpl;
use App\Services\BookLibraryPlanServiceImpl;
use App\Services\BookLibraryServiceImpl;
use App\Services\LibraryMilestoneServiceImpl;
use App\Services\ContentAdminService;
use App\Services\ContentDeliveryServiceImpl;
use App\Services\ContentQueueServiceImpl;
use App\Services\HadithApiServiceImpl;
use App\Services\MailtrapEmailServiceImpl;
use App\Services\MissionServiceImpl;
use App\Services\NahjServiceImpl;
use App\Services\PrayerBotServiceImpl;
use App\Services\QuranBotUserRankingServiceImpl;
use App\Services\ProServiceImpl;
use App\Services\ReverseGeocodingServiceImpl;
use App\Services\WeatherAlertServiceImpl;
use App\Services\WeatherComparisonServiceImpl;
use App\Services\ChannelPosterBotServiceImpl;
use App\Services\MpContactBotServiceImpl;
use App\Services\TelegramChannelPosterPublisherFactory;
use App\Services\GrowthCompanionServiceImpl;
use App\Services\TelegramGrowthMessengerFactory;
use App\Services\PoemBotServiceImpl;
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

        $this->app->bind(LibraryRepository::class, LibraryRepositoryImpl::class);
        $this->app->bind(MissionRepository::class, MissionRepositoryImpl::class);
        $this->app->bind(ContentRepository::class, ContentRepositoryImpl::class);

        $this->app->bind(PrayerRecordRepository::class, PrayerRecordRepositoryImpl::class);
        $this->app->bind(PrayerEstimateRepository::class, PrayerEstimateRepositoryImpl::class);

        $this->app->bind(BookRepository::class, BookRepositoryImpl::class);
        $this->app->bind(BookPageScanRepository::class, BookPageScanRepositoryImpl::class);
        $this->app->bind(BookUserScoreRepository::class, BookUserScoreRepositoryImpl::class);
        $this->app->bind(BookDraftRepository::class, BookDraftRepositoryImpl::class);
        $this->app->bind(BookScanMissionRepository::class, BookScanMissionRepositoryImpl::class);

        $this->app->bind(PoemRepository::class, PoemRepositoryImpl::class);
        $this->app->bind(PoemVersionRepository::class, PoemVersionRepositoryImpl::class);
        $this->app->bind(PoemLineRepository::class, PoemLineRepositoryImpl::class);
        $this->app->bind(PoemLikeRepository::class, PoemLikeRepositoryImpl::class);
        $this->app->bind(PoemSuggestionRepository::class, PoemSuggestionRepositoryImpl::class);

        // Services
        $this->app->bind(WeatherTomorrowApiService::class, WeatherTomorrowApiServiceImpl::class);
        $this->app->bind(WeatherOpenWeatherMapApiService::class, WeatherOpenWeatherMapApiServiceImpl::class);

        $this->app->bind(ReverseGeocodingService::class, ReverseGeocodingServiceImpl::class);
        $this->app->bind(WeatherComparisonService::class, WeatherComparisonServiceImpl::class);
        $this->app->bind(ProService::class, ProServiceImpl::class);
        $this->app->bind(WeatherAlertService::class, WeatherAlertServiceImpl::class);

        $this->app->bind(BookLibraryService::class, BookLibraryServiceImpl::class);
        $this->app->bind(BookLibraryDeliveryService::class, BookLibraryDeliveryServiceImpl::class);
        $this->app->bind(BookLibraryPlanService::class, BookLibraryPlanServiceImpl::class);
        $this->app->bind(LibraryMilestoneService::class, LibraryMilestoneServiceImpl::class);

        $this->app->bind(ContentQueueService::class, ContentQueueServiceImpl::class);
        $this->app->bind(ContentDeliveryService::class, ContentDeliveryServiceImpl::class);
        $this->app->singleton(ContentAdminService::class);

        $this->app->bind(HadithApiService::class, HadithApiServiceImpl::class);
        $this->app->bind(NahjService::class, NahjServiceImpl::class);

        $this->app->bind(QuranBotUserRankingService::class, QuranBotUserRankingServiceImpl::class);

        $this->app->bind(MissionService::class, MissionServiceImpl::class);
        $this->app->bind(ContentService::class, ContentServiceImpl::class);

        $this->app->bind(PrayerBotService::class, PrayerBotServiceImpl::class);

        $this->app->bind(PoemBotService::class, PoemBotServiceImpl::class);
        $this->app->bind(MpContactBotService::class, MpContactBotServiceImpl::class);
        $this->app->bind(ChannelPosterBotService::class, ChannelPosterBotServiceImpl::class);
        $this->app->bind(ChannelPosterPublisherFactory::class, TelegramChannelPosterPublisherFactory::class);
        $this->app->bind(GrowthCompanionService::class, GrowthCompanionServiceImpl::class);
        $this->app->bind(GrowthMessengerFactory::class, TelegramGrowthMessengerFactory::class);
        $this->app->bind(GrowthLlmProvider::class, function () {
            $key = config('growth.llm.api_key');
            if (!$key) {
                return new \App\Services\NullGrowthLlmProvider();
            }

            return new \App\Services\HttpGrowthLlmProvider(
                new \GuzzleHttp\Client(),
                (array) config('growth.llm')
            );
        });
        $this->app->bind(MawkibFinderService::class, MawkibFinderServiceImpl::class);
        $this->app->singleton(\App\Services\MawkibFinderOtpService::class);

        $this->app->bind(BookPixelService::class, BookPixelServiceImpl::class);
        $this->app->bind(BookGamificationService::class, BookGamificationServiceImpl::class);
        $this->app->bind(BookPublishingService::class, BookPublishingServiceImpl::class);
        $this->app->bind(BookDraftService::class, BookDraftServiceImpl::class);
        $this->app->bind(BookScanMissionService::class, BookScanMissionServiceImpl::class);
        $this->app->bind(BookStatisticsService::class, BookStatisticsServiceImpl::class);
        $this->app->bind(ContentSubmissionService::class, ContentSubmissionServiceImpl::class);

        // Email Service
        $this->app->bind(EmailService::class, MailtrapEmailServiceImpl::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
