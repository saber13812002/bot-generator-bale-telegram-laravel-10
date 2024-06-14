<?php

namespace App\Services;

use GuzzleHttp\Client;

class SPlusChatService
{

    private mixed $authToken;
    private string $message;
    private string $sendTo;


    /**
     * @param string $message
     * @param string $sendTo
     */
    public function __construct(string $message, string $sendTo)
    {
        $this->message = $message;
        $this->sendTo = $sendTo;
        $authToken = $this->getToken();
        $this->authToken = $authToken;
    }

    public function send()
    {

        $client = new Client();

        $messageUri = config('services.splus.endpoint') . "v2/messages/send";

        $messageBody = json_encode([
            'phone_number' => $this->sendTo,
            'pattern_id' => 341,
            "message_values" => [
                $this->message
            ]
        ]);

        $messageResponse = $client->request('POST', $messageUri, [
            'headers' => [
                'Authorization' => $this->authToken,
                'Content-Type' => 'application/json'
            ],
            'body' => $messageBody
        ]);
    }

    private function getToken()
    {
        return env('SPLUS_BOT_TOKEN');
    }
}
