<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\StringHelper;
use App\Http\Requests\StoreweatherRequest;
use App\Http\Requests\UpdateweatherRequest;
use App\Interfaces\Services\WeatherOpenWeatherMapApiService;
use App\Interfaces\Services\WeatherTomorrowApiService;
use App\Models\Weather;
use Exception;
use Illuminate\Http\Request;
use Log;
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
        try {


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
                    // پیام راهنما برای تعیین حداقل سرعت باد
                    $message = trans("bot.Please determine the minimum wind speed for bot to send you desired alert");
                } else if (is_numeric($bot->Text()) && intval($bot->Text()) > 1 && intval($bot->Text()) < 20) {
                    // اگر عدد بین 1 تا 20 بود، از آن به عنوان حداقل سرعت باد استفاده کن
                    $message = $this->weatherTomorrowApiService->getMessage($bot->Text());
                } else {
                    // برای هر متن دیگر (یا اگر forecasting بدون عدد بود)، از مقدار پیش‌فرض 20 استفاده کن
                    $message = $this->weatherTomorrowApiService->getMessage("20");
                }

                BotHelper::sendMessageToUserAndAdmins($bot, $message . $commands, $type);
                return 0;
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            return 0;
        }

        return 0;
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
    function show(Weather $weather)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public
    function edit(Weather $weather)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public
    function update(UpdateweatherRequest $request, Weather $weather)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public
    function destroy(Weather $weather)
    {
        //
    }

}
