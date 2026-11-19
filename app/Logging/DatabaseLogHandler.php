<?php

namespace App\Logging;

use App\Models\AppLogEntry;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class DatabaseLogHandler extends AbstractProcessingHandler
{
    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $context = $record->context;
            $botId = $context['bot_id'] ?? $context['botId'] ?? null;
            $featureKey = $context['feature_key'] ?? $context['featureKey'] ?? null;

            AppLogEntry::create([
                'level' => strtolower($record->level->getName()),
                'message' => $record->message,
                'bot_id' => is_numeric($botId) ? (int) $botId : null,
                'feature_key' => is_string($featureKey) && $featureKey !== '' ? substr($featureKey, 0, 64) : null,
                'context' => $this->safeContext($context),
                'created_at' => now(),
            ]);

            if (random_int(1, 20) === 1) {
                AppLogEntry::pruneExcess((int) config('observability.log_max_rows', 5000));
            }
        } catch (Throwable) {
            // Never call Log:: from this handler (recursion). Fail silently.
        }
    }

    private function safeContext(array $context): ?array
    {
        if ($context === []) {
            return null;
        }

        try {
            $encoded = json_encode(
                $context,
                JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE
            );
            if ($encoded === false) {
                return ['_unserializable' => true];
            }

            $decoded = json_decode($encoded, true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }
}
