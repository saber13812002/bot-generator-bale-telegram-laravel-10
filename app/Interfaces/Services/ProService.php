<?php

namespace App\Interfaces\Services;

interface ProService
{
    /**
     * بررسی Pro بودن
     */
    public function isPro(int $botUserId, int $botId): bool;

    /**
     * بررسی دسترسی به فیچر
     */
    public function canUseFeature(int $botUserId, int $botId, string $feature): bool;

    /**
     * ثبت درخواست خرید
     */
    public function requestPurchase(int $botUserId, int $botId, string $userIdentifier): array;

    /**
     * تایید خرید توسط مدیر
     */
    public function confirmPurchase(int $requestId, ?int $adminId = null): bool;

    /**
     * لیست فیچرهای Pro برای یک ربات
     */
    public function getProFeatures(int $botId): array;
}
