<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Models\weather;
use App\Http\Requests\StoreweatherRequest;
use App\Http\Requests\UpdateweatherRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Telegram;

class WeatherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bot = new Telegram(env("BOT_WEATHER_TOKEN_BALE"), 'bale');
        $api_key = "5544a4477b054679586be02cba1dbf39";
        $city_name = "Qom";

        $client = new Client();
        $request = new Request('GET', 'https://api.openweathermap.org/data/2.5/weather?q=Qom&appid=5544a4477b054679586be02cba1dbf39');
        $res = $client->sendAsync($request)->wait();


        BotHelper::sendMessage($bot, 'هوا باد میاد
        '.json_decode($res->getBody()));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreweatherRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(weather $weather)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(weather $weather)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateweatherRequest $request, weather $weather)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(weather $weather)
    {
        //
    }
}
