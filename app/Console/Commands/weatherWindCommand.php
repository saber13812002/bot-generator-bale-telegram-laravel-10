<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Http\Controllers\WeatherController;
use GuzzleHttp\Exception\GuzzleException;
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

    /**
     * Execute the console command.
     * @throws GuzzleException
     */
    public function handle(): void
    {
        $speed = $this->argument('speed');
        $message = WeatherController::getMessageFromTomorrowApi("15");
        BotHelper::sendMessageToSuperAdmin($message, 'telegram');
    }
}
