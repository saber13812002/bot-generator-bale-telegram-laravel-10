<?php

namespace App\Broadcasting;

use App\Models\User;
use App\Services\RocketChatService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Notification;

class RocketChatChannel
{
    private string $sendTo;

    public function __construct($sendTo)
    {
        $this->sendTo = $sendTo;
    }

    /**
     * @throws GuzzleException
     */
    public function send($notifiable, Notification $notification)
    {
        //todo this should test later
        $message = $notification->send($notifiable);

        $rocket = new RocketChatService($message, $this->sendTo);
        $rocket->sendMessage();
    }
}
