<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;

class BotController extends Controller
{
    public function BotKidLog($bot)
    {
        // Your shared method logic here
    }

    public function setLocale($request)
    {
        if ($request->has('language')) {
            App::setLocale($request->input('language'));
        } else {
            App::setLocale("fa");
        }
    }
}
