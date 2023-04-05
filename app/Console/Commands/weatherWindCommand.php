<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Interfaces\Services\WeatherOpenWeatherMapApiService;
use App\Interfaces\Services\WeatherTomorrowApiService;
use Illuminate\Console\Command;

class weatherWindCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:weather-wind-command {speed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    private WeatherTomorrowApiService $weatherTomorrowApiService;
    private WeatherOpenWeatherMapApiService $weatherOpenWeatherMapApiService;

    public function __construct(WeatherTomorrowApiService $weatherTomorrowApiService, WeatherOpenWeatherMapApiService $weatherOpenWeatherMapApiService)
    {
        parent::__construct();
        $this->weatherTomorrowApiService = $weatherTomorrowApiService;
        $this->weatherOpenWeatherMapApiService = $weatherOpenWeatherMapApiService;
    }
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $speed = $this->argument('speed');
        $message = $this->weatherTomorrowApiService->getMessage(20);
        BotHelper::sendMessageToSuperAdmin($message, 'telegram');
    }
}
