<?php

namespace App\Interfaces\Services;

interface WeatherAlertService
{
    /**
     * ایجاد alert
     */
    public function createAlert(int $botUserId, int $botId, array $alertData): array;

    /**
     * چک کردن همه alerts
     */
    public function checkAlerts(): void;

    /**
     * Trigger کردن alert
     */
    public function triggerAlert($alert, array $weatherData): void;

    /**
     * مقایسه آب و هوا
     */
    public function compareWeather(array $current, ?array $previous, $alert): bool;
}
