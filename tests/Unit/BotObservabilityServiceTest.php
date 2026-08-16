<?php

namespace Tests\Unit;

use App\Services\BotObservabilityService;
use App\Services\PrometheusMetricsExporter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BotObservabilityServiceTest extends TestCase
{
    public function test_symptoms_for_empty_bot(): void
    {
        $service = new BotObservabilityService();

        $symptoms = $service->symptoms([
            'users' => 0,
            'last_inbound_at' => null,
            'last_outbound_ok_at' => null,
            'webhook_is_set' => false,
            'status_active' => false,
        ]);

        $this->assertSame(
            ['deactive', 'webhook_not_set', 'no_users', 'no_inbound'],
            $symptoms
        );
    }

    public function test_symptoms_inbound_without_outbound(): void
    {
        $service = new BotObservabilityService();
        $inbound = Carbon::parse('2026-08-16 12:00:00');

        $this->assertContains('inbound_without_outbound', $service->symptoms([
            'users' => 2,
            'last_inbound_at' => $inbound,
            'last_outbound_ok_at' => null,
            'webhook_is_set' => true,
            'status_active' => true,
        ]));

        $this->assertContains('inbound_without_outbound', $service->symptoms([
            'users' => 2,
            'last_inbound_at' => $inbound,
            'last_outbound_ok_at' => Carbon::parse('2026-08-16 11:00:00'),
            'webhook_is_set' => true,
            'status_active' => true,
        ]));

        $this->assertNotContains('inbound_without_outbound', $service->symptoms([
            'users' => 2,
            'last_inbound_at' => $inbound,
            'last_outbound_ok_at' => Carbon::parse('2026-08-16 13:00:00'),
            'webhook_is_set' => true,
            'status_active' => true,
        ]));
    }

    public function test_prometheus_escapes_label_quotes(): void
    {
        $exporter = new PrometheusMetricsExporter();
        $body = $exporter->render(collect([[
            'endpoint_id' => 'webhook-hadith',
            'endpoint_name' => 'Hadith "fa"',
            'bot_id' => 12,
            'bot_name' => 'bot\\name',
            'platform' => 'bale',
            'users' => 0,
            'last_inbound_at' => null,
            'last_outbound_ok_at' => null,
            'last_outbound_fail_at' => null,
            'webhook_is_set' => false,
            'status_active' => true,
        ]]));

        $this->assertStringContainsString('endpoint_name="Hadith \\"fa\\""', $body);
        $this->assertStringContainsString('bot_name="bot\\\\name"', $body);
        $this->assertStringContainsString('# TYPE bot_users_total gauge', $body);
    }
}
