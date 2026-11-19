<?php

namespace App\Services;

use App\Interfaces\Services\ProService;
use App\Models\BotUsers;
use App\Models\ProPurchaseRequest;
use App\Models\ProUser;
use Illuminate\Support\Facades\Log;

class ProServiceImpl implements ProService
{
    /**
     * بررسی Pro بودن
     */
    public function isPro(int $botUserId, int $botId): bool
    {
        $user = BotUsers::find($botUserId);
        if (!$user) {
            return false;
        }

        return $user->isPro($botId);
    }

    /**
     * بررسی دسترسی به فیچر
     */
    public function canUseFeature(int $botUserId, int $botId, string $feature): bool
    {
        if (!$this->isPro($botUserId, $botId)) {
            return false;
        }

        $features = $this->getProFeatures($botId);
        return in_array($feature, $features);
    }

    /**
     * ثبت درخواست خرید
     */
    public function requestPurchase(int $botUserId, int $botId, string $userIdentifier, array $extra = []): array
    {
        $existingRequest = ProPurchaseRequest::where('bot_user_id', $botUserId)
            ->where('bot_id', $botId)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return [
                'success' => false,
                'message' => 'درخواست قبلی شما در حال بررسی است',
                'request_id' => $existingRequest->id,
            ];
        }

        $paymentInfo = $extra['payment_info'] ?? null;
        if (is_array($paymentInfo)) {
            $paymentInfo = json_encode($paymentInfo, JSON_UNESCAPED_UNICODE);
        }

        $request = ProPurchaseRequest::create([
            'bot_user_id' => $botUserId,
            'bot_id' => $botId,
            'user_identifier' => $userIdentifier,
            'status' => 'pending',
            'payment_method' => $extra['payment_method'] ?? null,
            'payment_info' => $paymentInfo,
        ]);

        Log::info('💳 [Pro] Purchase request created', [
            'request_id' => $request->id,
            'bot_user_id' => $botUserId,
            'bot_id' => $botId,
        ]);

        return [
            'success' => true,
            'request_id' => $request->id,
            'message' => 'درخواست شما ثبت شد',
        ];
    }

    /**
     * تایید خرید توسط مدیر
     */
    public function confirmPurchase(int $requestId, ?int $adminId = null): bool
    {
        $request = ProPurchaseRequest::find($requestId);
        
        if (!$request || $request->status !== 'pending') {
            return false;
        }

        $info = $this->decodePaymentInfo($request->payment_info);
        $months = (int) ($info['months'] ?? 0);

        $proUser = ProUser::updateOrCreate(
            [
                'bot_user_id' => $request->bot_user_id,
                'bot_id' => $request->bot_id,
            ],
            [
                'status' => 'active',
                'purchase_requested_at' => $request->created_at,
                'purchase_confirmed_at' => now(),
                'confirmed_by_admin_id' => $adminId,
                'expires_at' => $months > 0 ? now()->addMonths($months) : null,
                'payment_info' => [
                    'payment_method' => $request->payment_method,
                    'payment_info' => $info !== [] ? $info : $request->payment_info,
                ],
            ]
        );

        // به‌روزرسانی request
        $request->status = 'confirmed';
        $request->approved_by = $adminId;
        $request->approved_at = now();
        $request->save();

        Log::info('✅ [Pro] Purchase confirmed', [
            'request_id' => $requestId,
            'pro_user_id' => $proUser->id,
            'admin_id' => $adminId,
        ]);

        return true;
    }

    /**
     * لیست فیچرهای Pro برای یک ربات
     */
    public function getProFeatures(int $botId): array
    {
        $config = config('pro_features', []);
        
        // دریافت endpoint_id از bot
        $bot = \App\Models\Bot::find($botId);
        $endpointId = $bot->endpoint_id ?? null;

        if (!$endpointId || !isset($config[$endpointId])) {
            return [];
        }

        $features = [];
        $botFeatures = $config[$endpointId];

        if (isset($botFeatures['unlimited_alerts']) && $botFeatures['unlimited_alerts']) {
            $features[] = 'unlimited_alerts';
        }

        if (isset($botFeatures['advanced_reports']) && $botFeatures['advanced_reports']) {
            $features[] = 'advanced_reports';
        }

        if (isset($botFeatures['email_after_year']) && $botFeatures['email_after_year']) {
            $features[] = 'email_after_year';
        }

        if (isset($botFeatures['unlimited_topics']) && $botFeatures['unlimited_topics']) {
            $features[] = 'unlimited_topics';
        }

        if (isset($botFeatures['advanced_mode']) && $botFeatures['advanced_mode']) {
            $features[] = 'advanced_mode';
        }

        if (isset($botFeatures['ai_variants']) && $botFeatures['ai_variants']) {
            $features[] = 'ai_variants';
        }

        return $features;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePaymentInfo(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
