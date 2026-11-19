<?php

namespace App\Services;

use App\Models\AppLogEntry;
use App\Models\BotHealthEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LogViewerService
{
    public function latestLogs(?int $botId, int $limit): Collection
    {
        return AppLogEntry::query()
            ->forBot($botId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function formatLogsAsText(Collection $logs): string
    {
        return $logs->map(function (AppLogEntry $log) {
            $time = $log->created_at?->toDateTimeString() ?? '-';
            $bot = $log->bot_id !== null ? 'bot_id='.$log->bot_id : 'bot_id=-';
            $feature = $log->feature_key ? $log->feature_key : '-';
            $context = $log->context ? json_encode($log->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
            $line = "[{$time}] {$log->level} {$bot} {$feature} | {$log->message}";
            if ($context !== '') {
                $line .= "\n  context: {$context}";
            }

            return $line;
        })->implode("\n\n");
    }

    public function tailLogFile(int $lines, ?int $botId = null): string
    {
        $path = storage_path('logs/laravel.log');
        if (!is_readable($path)) {
            return '';
        }

        $content = $this->tailFile($path, max($lines, 1));
        if ($botId === null) {
            return $content;
        }

        $needle = (string) $botId;
        $kept = [];
        foreach (preg_split("/\r\n|\n|\r/", $content) as $line) {
            if (str_contains($line, 'bot_id') && str_contains($line, $needle)) {
                $kept[] = $line;
            }
        }

        return implode("\n", $kept);
    }

    public function healthSummary(?int $botId): Collection
    {
        $latestIds = BotHealthEvent::query()
            ->forBot($botId)
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('feature_key', 'platform')
            ->pluck('id');

        $latest = BotHealthEvent::query()
            ->whereIn('id', $latestIds)
            ->orderBy('feature_key')
            ->orderBy('platform')
            ->get();

        $lastOk = BotHealthEvent::query()
            ->forBot($botId)
            ->where('status', 'ok')
            ->select('feature_key', 'platform', DB::raw('MAX(created_at) as last_ok_at'))
            ->groupBy('feature_key', 'platform')
            ->get()
            ->keyBy(fn (BotHealthEvent $row) => $row->feature_key.'|'.$row->platform);

        return $latest->map(function (BotHealthEvent $event) use ($lastOk) {
            $key = $event->feature_key.'|'.$event->platform;
            $okAt = $lastOk->get($key)?->last_ok_at;
            $okAtCarbon = $okAt ? Carbon::parse($okAt) : null;
            $isToday = $okAtCarbon?->isToday() ?? false;

            return [
                'feature_key' => $event->feature_key,
                'platform' => $event->platform,
                'last_status' => $event->status,
                'last_event_at' => $event->created_at,
                'last_ok_at' => $okAtCarbon,
                'is_green' => $isToday,
                'bot_id' => $event->bot_id,
                'message' => $event->message,
            ];
        });
    }

    private function tailFile(string $path, int $lines): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        $buffer = '';
        $chunk = 4096;
        fseek($handle, 0, SEEK_END);
        $pos = ftell($handle);
        $lineCount = 0;

        while ($pos > 0 && $lineCount <= $lines) {
            $read = min($chunk, $pos);
            $pos -= $read;
            fseek($handle, $pos);
            $buffer = fread($handle, $read).$buffer;
            $lineCount = substr_count($buffer, "\n");
        }

        fclose($handle);

        $all = preg_split("/\r\n|\n|\r/", $buffer);

        return implode("\n", array_slice($all, -$lines));
    }
}
