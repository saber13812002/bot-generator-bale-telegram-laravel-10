<?php

namespace App\Broadcasting;

use App\Models\User;

class RocketChatChannel
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toRocketChat($notifiable);

        $this->client->post(config('services.rocketchat.endpoint'), [
            'json' => [
                'text' => $message->content,
                'attachments' => [
                    [
                        'title' => $message->title,
                        'text' => $message->content,
                    ],
                ],
            ],
        ]);
    }
}
