<?php

namespace App\Http\Controllers;

use App\Models\WebhookEndpoint;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    /**
     * نمایش صفحه اصلی
     */
    public function index(): View
    {
        // دریافت لیست ربات‌های فعال از دیتابیس
        $bots = WebhookEndpoint::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('welcome', [
            'bots' => $bots,
        ]);
    }
}
