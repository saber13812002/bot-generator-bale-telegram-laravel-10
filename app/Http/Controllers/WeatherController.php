<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\StringHelper;
use App\Http\Requests\StoreweatherRequest;
use App\Http\Requests\UpdateweatherRequest;
use App\Interfaces\Services\WeatherOpenWeatherMapApiService;
use App\Interfaces\Services\WeatherTomorrowApiService;
use App\Models\weather;
use Illuminate\Http\Request;
use Telegram;


class WeatherController extends Controller
{
    private WeatherTomorrowApiService $weatherTomorrowApiService;
    private WeatherOpenWeatherMapApiService $weatherOpenWeatherMapApiService;

    public function __construct(WeatherTomorrowApiService $weatherTomorrowApiService, WeatherOpenWeatherMapApiService $weatherOpenWeatherMapApiService)
    {
        $this->weatherTomorrowApiService = $weatherTomorrowApiService;
        $this->weatherOpenWeatherMapApiService = $weatherOpenWeatherMapApiService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $type = $request->input('origin');
        $message = "-";
        if ($request->has('origin')) {
            if ($request->input('origin') == 'bale') {
                $bot = new Telegram(env("BOT_WEATHER_TOKEN_BALE"), 'bale');
            } else {
                $bot = new Telegram(env("BOT_WEATHER_TOKEN_TELEGRAM"), 'telegram');
            }
            $commands = StringHelper::getWeatherBotCommandsAsPostfixForMessages();
            if ($bot->Text() == "/current") {
                $message = $this->weatherOpenWeatherMapApiService->getMessage();
            } else if ($bot->Text() == "/forecasting") {
                $message = trans("bot.Please determine the minimum wind speed for bot to send you desired alert");
            } else if ($bot->Text() > 1 && $bot->Text() < 20) {
                $message = $this->weatherTomorrowApiService->getMessage($bot->Text());
            } else {
                $message = $this->weatherTomorrowApiService->getMessage(20);
            }

            BotHelper::sendMessageToUserAndAdmins($bot, $message . $commands, $type);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public
    function store(StoreweatherRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public
    function show(weather $weather)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public
    function edit(weather $weather)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public
    function update(UpdateweatherRequest $request, weather $weather)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public
    function destroy(weather $weather)
    {
        //
    }

}
