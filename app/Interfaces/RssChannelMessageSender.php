<?php

namespace App\Interfaces;

/**
 * ارسال پیام/رسانه به کانال RSS — انتراکت برای تست‌پذیری.
 *
 * ارسال به Eitaa از طریق curl خام در BotHelper انجام می‌شود (نه Http facade)
 * که با Http::fake قابل تست نبود. این انترفیس ارسال را از بقیه‌ی منطق
 * (ثبت وضعیت، ریکاوری، لاگ) جدا می‌کند تا تست‌ها fake بپیوندند.
 *
 * @see \App\Services\BotRssChannelMessageSender (پیاده‌سازی پیش‌فرض)
 */
interface RssChannelMessageSender
{
    /**
     * ارسال پیام اصلی (متن) به کانال.
     *
     * @param string $message متن پیام
     * @param string $token   توکن ربات
     * @param mixed  $target_id chat id کانال
     * @param string $mediumSlug پلتفرم (telegram|bale|eitaa|gap|...)
     * @param string|null $parseMode
     * @return mixed پاسخ خام API (آرایه/آبجکت/رابطه)
     */
    public function sendBaseMessage(string $message, string $token, $target_id, string $mediumSlug, ?string $parseMode = null): mixed;

    /**
     * ارسال عکس به کانال.
     *
     * @return mixed پاسخ خام API
     */
    public function sendPhoto(string $token, $target_id, string $photoUrl, string $caption, string $title, string $mediumSlug, ?string $parseMode = null): mixed;

    /**
     * ارسال صدا/پادکست به کانال.
     *
     * @return mixed پاسخ خام API
     */
    public function sendAudio(string $token, $target_id, string $audioUrl, string $caption, string $title, string $mediumSlug, ?string $parseMode = null): mixed;
}
