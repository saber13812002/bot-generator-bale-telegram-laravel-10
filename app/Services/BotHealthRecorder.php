<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotHealthEvent;
use Throwable;

class BotHealthRecorder
{
    public static function record(array $data): void
    {
        try {
            $botId = isset($data['bot_id']) && is_numeric($data['bot_id'])
                ? (int) $data['bot_id']
                : null;
            $status = (string) ($data['status'] ?? 'fail');

            BotHealthEvent::create([
                'bot_id' => $botId,
                'feature_key' => (string) ($data['feature_key'] ?? 'unknown'),
                'platform' => (string) ($data['platform'] ?? 'unknown'),
                'event_type' => (string) ($data['event_type'] ?? 'channel_post'),
                'status' => $status,
                'message' => $data['message'] ?? null,
                'meta' => $data['meta'] ?? null,
                'created_at' => now(),
            ]);

            if ($botId && $status === 'ok') {
                Bot::where('id', $botId)->update(['last_activity_at' => now()]);
            }
        } catch (Throwable) {
            // Health recording must never break posting.
        }
    }

    public static function isMessengerOk(mixed $response): bool
    {
        if (is_array($response) && array_key_exists('ok', $response)) {
            return (bool) $response['ok'];
        }

        if (is_object($response) && isset($response->ok)) {
            return (bool) $response->ok;
        }

        if (is_string($response) && $response !== '') {
            $decoded = json_decode($response, true);
            if (is_array($decoded) && array_key_exists('ok', $decoded)) {
                return (bool) $decoded['ok'];
            }
        }

        return false;
    }

    public static function findBotIdByToken(?string $token): ?int
    {
        if (!$token) {
            return null;
        }

        try {
            $bot = Bot::query()
                ->where(function ($query) use ($token) {
                    $query->where('telegram_bot_token', $token)
                        ->orWhere('bale_bot_token', $token);
                })
                ->first();

            return $bot?->id;
        } catch (Throwable) {
            return null;
        }
    }
}
