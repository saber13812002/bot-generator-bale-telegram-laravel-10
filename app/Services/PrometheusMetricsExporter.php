<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PrometheusMetricsExporter
{
    /**
     * @param  Collection<int, array<string, mixed>>  $series
     */
    public function render(Collection $series): string
    {
        $lines = [];

        $this->appendGauge($lines, $series, 'bot_info', 'Bot identity (always 1).', fn () => 1);
        $this->appendGauge($lines, $series, 'bot_users_total', 'Registered bot users for this bot and platform.', fn (array $row) => (int) $row['users']);
        $this->appendGauge($lines, $series, 'bot_last_inbound_timestamp', 'Unix time of last inbound webhook message. 0 if never.', fn (array $row) => $this->unix($row['last_inbound_at']));
        $this->appendGauge($lines, $series, 'bot_last_outbound_ok_timestamp', 'Unix time of last successful outbound. 0 if never.', fn (array $row) => $this->unix($row['last_outbound_ok_at']));
        $this->appendGauge($lines, $series, 'bot_last_outbound_fail_timestamp', 'Unix time of last failed outbound. 0 if never.', fn (array $row) => $this->unix($row['last_outbound_fail_at']));
        $this->appendGauge($lines, $series, 'bot_webhook_is_set', '1 if messenger webhook is marked set.', fn (array $row) => $row['webhook_is_set'] ? 1 : 0);
        $this->appendGauge($lines, $series, 'bot_status_active', '1 if this platform status is Active.', fn (array $row) => $row['status_active'] ? 1 : 0);

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<string>  $lines
     * @param  Collection<int, array<string, mixed>>  $series
     */
    private function appendGauge(array &$lines, Collection $series, string $name, string $help, callable $value): void
    {
        $lines[] = '# HELP '.$name.' '.$help;
        $lines[] = '# TYPE '.$name.' gauge';

        foreach ($series as $row) {
            $lines[] = $name.$this->labels($row).' '.$value($row);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function labels(array $row): string
    {
        $pairs = [
            'endpoint_id' => (string) $row['endpoint_id'],
            'endpoint_name' => (string) $row['endpoint_name'],
            'bot_id' => (string) $row['bot_id'],
            'bot_name' => (string) $row['bot_name'],
            'platform' => (string) $row['platform'],
        ];

        $parts = [];
        foreach ($pairs as $key => $val) {
            $parts[] = $key.'="'.$this->escapeLabel($val).'"';
        }

        return '{'.implode(',', $parts).'}';
    }

    private function escapeLabel(string $value): string
    {
        return str_replace(
            ['\\', "\n", '"'],
            ['\\\\', '\\n', '\\"'],
            $value
        );
    }

    private function unix(mixed $value): int
    {
        if ($value instanceof Carbon) {
            return $value->getTimestamp();
        }

        return 0;
    }
}
