<?php

namespace App\Http\Controllers;

use App\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
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

    /**
     * نمایش صفحه جزئیات ربات
     */
    public function show(string $endpointId): View|RedirectResponse
    {
        // دریافت ربات از endpoint_id
        $bot = WebhookEndpoint::where('endpoint_id', $endpointId)
            ->where('is_active', true)
            ->first();

        if (!$bot) {
            abort(404, 'ربات یافت نشد');
        }

        // دریافت 3 ربات مرتبط (با ترتیب order)
        $relatedBots = $bot->getRelatedBots(3);

        return view('bot.show', [
            'bot' => $bot,
            'relatedBots' => $relatedBots,
        ]);
    }
}
