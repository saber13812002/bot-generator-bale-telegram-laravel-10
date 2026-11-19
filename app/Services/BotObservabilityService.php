<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotHealthEvent;
use App\Models\BotLog;
use App\Models\BotUsers;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class BotObservabilityService
{
    /**
     * One series per bot × platform that has a token.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function series(): Collection
    {
        $endpointNames = $this->endpointNames();
        $userCounts = $this->userCountsByBotAndPlatform();
        $lastInbound = $this->lastInboundByBotAndPlatform();
        $lastOutboundOk = $this->lastOutboundByBotAndPlatform('ok');
        $lastOutboundFail = $this->lastOutboundByBotAndPlatform('fail');

        $series = collect();

        foreach ($this->bots() as $bot) {
            foreach (['bale', 'telegram'] as $platform) {
                if (!$this->hasPlatformToken($bot, $platform)) {
                    continue;
                }

                $key = $bot->id.'|'.$platform;
                $endpointId = (string) ($bot->endpoint_id ?: 'unknown');
                $inboundAt = $lastInbound[$key] ?? null;
                $outboundOkAt = $lastOutboundOk[$key] ?? null;
                $outboundFailAt = $lastOutboundFail[$key] ?? null;
                $active = $this->isPlatformActive($bot, $platform);
                $webhookSet = $this->isWebhookSet($bot, $platform);
                $users = (int) ($userCounts[$key] ?? 0);

                $row = [
                    'endpoint_id' => $endpointId,
                    'endpoint_name' => (string) ($endpointNames[$endpointId] ?? $endpointId),
                    'bot_id' => (int) $bot->id,
                    'bot_name' => $this->botName($bot, $platform),
                    'platform' => $platform,
                    'users' => $users,
                    'last_inbound_at' => $inboundAt,
                    'last_outbound_ok_at' => $outboundOkAt,
                    'last_outbound_fail_at' => $outboundFailAt,
                    'webhook_is_set' => $webhookSet,
                    'status_active' => $active,
                    'status' => $active ? 'Active' : 'DeActive',
                ];
                $row['symptoms'] = $this->symptoms($row);
                $series->push($row);
            }
        }

        return $series;
    }

    /**
     * @return array<string, mixed>
     */
    public function healthPayload(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'bots' => $this->series()->map(function (array $row) {
                return [
                    'endpoint_id' => $row['endpoint_id'],
                    'endpoint_name' => $row['endpoint_name'],
                    'bot_id' => $row['bot_id'],
                    'bot_name' => $row['bot_name'],
                    'platform' => $row['platform'],
                    'users' => $row['users'],
                    'last_inbound_at' => $this->iso($row['last_inbound_at']),
                    'last_outbound_ok_at' => $this->iso($row['last_outbound_ok_at']),
                    'last_outbound_fail_at' => $this->iso($row['last_outbound_fail_at']),
                    'webhook_is_set' => $row['webhook_is_set'],
                    'status' => $row['status'],
                    'symptoms' => $row['symptoms'],
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function symptoms(array $row): array
    {
        $symptoms = [];

        if (empty($row['status_active'])) {
            $symptoms[] = 'deactive';
        }
        if (empty($row['webhook_is_set'])) {
            $symptoms[] = 'webhook_not_set';
        }
        if ((int) ($row['users'] ?? 0) === 0) {
            $symptoms[] = 'no_users';
        }
        if (empty($row['last_inbound_at'])) {
            $symptoms[] = 'no_inbound';
        } else {
            $ok = $row['last_outbound_ok_at'] ?? null;
            if ($ok === null || $row['last_inbound_at'] > $ok) {
                $symptoms[] = 'inbound_without_outbound';
            }
        }

        return $symptoms;
    }

    /**
     * @return Collection<int, Bot>
     */
    private function bots(): Collection
    {
        if (!Schema::hasTable('bots')) {
            return collect();
        }

        return Bot::query()->orderBy('id')->get();
    }

    /**
     * @return array<string, string>
     */
    private function endpointNames(): array
    {
        if (!Schema::hasTable('webhook_endpoints')) {
            return [];
        }

        return WebhookEndpoint::query()
            ->pluck('name', 'endpoint_id')
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function userCountsByBotAndPlatform(): array
    {
        if (!Schema::hasTable('bot_users')) {
            return [];
        }

        return BotUsers::query()
            ->selectRaw('bot_id, origin, COUNT(*) as total')
            ->groupBy('bot_id', 'origin')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->bot_id.'|'.$row->origin => (int) $row->total])
            ->all();
    }

    /**
     * @return array<string, Carbon>
     */
    private function lastInboundByBotAndPlatform(): array
    {
        if (!Schema::hasTable('bot_logs')) {
            return [];
        }

        return BotLog::query()
            ->selectRaw('bot_id, type, MAX(created_at) as last_at')
            ->whereNotNull('bot_id')
            ->groupBy('bot_id', 'type')
            ->get()
            ->filter(fn ($row) => filled($row->last_at))
            ->mapWithKeys(fn ($row) => [$row->bot_id.'|'.$row->type => Carbon::parse($row->last_at)])
            ->all();
    }

    /**
     * @return array<string, Carbon>
     */
    private function lastOutboundByBotAndPlatform(string $status): array
    {
        if (!Schema::hasTable('bot_health_events')) {
            return [];
        }

        return BotHealthEvent::query()
            ->selectRaw('bot_id, platform, MAX(created_at) as last_at')
            ->where('status', $status)
            ->whereNotNull('bot_id')
            ->groupBy('bot_id', 'platform')
            ->get()
            ->filter(fn ($row) => filled($row->last_at))
            ->mapWithKeys(fn ($row) => [$row->bot_id.'|'.$row->platform => Carbon::parse($row->last_at)])
            ->all();
    }

    private function hasPlatformToken(Bot $bot, string $platform): bool
    {
        $token = $platform === 'bale'
            ? $bot->bale_bot_token
            : $bot->telegram_bot_token;

        return is_string($token) && $token !== '';
    }

    private function isPlatformActive(Bot $bot, string $platform): bool
    {
        $status = $platform === 'bale'
            ? $bot->bale_bot_status
            : $bot->telegram_bot_status;

        return strcasecmp((string) $status, 'Active') === 0;
    }

    private function isWebhookSet(Bot $bot, string $platform): bool
    {
        $column = $platform === 'bale' ? 'bale_webhook_is_set' : 'telegram_webhook_is_set';

        return (bool) ($bot->{$column} ?? false);
    }

    private function botName(Bot $bot, string $platform): string
    {
        if ($platform === 'bale') {
            return (string) ($bot->bale_bot_name ?: $bot->telegram_bot_name ?: 'bot-'.$bot->id);
        }

        return (string) ($bot->telegram_bot_name ?: $bot->bale_bot_name ?: 'bot-'.$bot->id);
    }

    private function iso(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        return null;
    }
}
