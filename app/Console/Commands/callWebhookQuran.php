<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class callWebhookQuran extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:call-webhook-quran';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $webhookUrl = config('app.url') . '/api/webhook-rss?origin=bale&token=1775842974%3A0cefc40d5eaab5a8d2ca19f77ca13a5463be5994&language=fa&bot_mother_id=1';

        $webhookPayloads = [
            [
                "update_id" => 2,
                "message" => [
                    "message_id" => -687281639,
                    "from" => [
                        "id" => 485750575,
                        "first_name" => "صابر طباطبایی یزدی",
                        "username" => "sabertaba",
                        "is_bot" => false
                    ],
                    "date" => 1680438429,
                    "chat" => [
                        "id" => 485750575,
                        "type" => "private",
                        "username" => "sabertaba",
                        "first_name" => "صابر طباطبایی یزدی"
                    ],
                    "text" => "/publish:rocket:test:_id100"
                ]
            ]
        ];

        foreach ($webhookPayloads as $payload) {
            $response = Http::post($webhookUrl, $payload);
            if ($response->successful()) {
                $this->info('Webhook sent successfully: ' . json_encode($payload));
            } else {
                $this->error('Failed to send webhook: ' . json_encode($payload));
            }
        }
    }
}
