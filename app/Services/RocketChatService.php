<?php

namespace App\Services;

use App\Helpers\BotHelper;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RocketChatService
{

    private mixed $authToken;
    private mixed $userId;
    private string $message;
    private string $sendTo;

    /**
     * @throws GuzzleException
     */
    public function __construct($message, $sendTo)
    {
        $this->message = $message;
        $this->sendTo = $sendTo;
        list($authToken, $userId) = $this->getToken();
        $this->authToken = $authToken;
        $this->userId = $userId;
//        dd($this->userId, $this->authToken, $this->sendTo, $this->message);
    }


    /**
     * @return array
     * @throws GuzzleException
     * @throws Exception
     */
    private function getToken(): array
    {
        // Check if data exists in the cache
        if (Cache::has('rocket_login')) {
            // Data exists
            $loginResponseData = Cache::get('rocket_login');
        } else {
            // Data does not exist
            try {
                $loginResponseData = $this->getLoginResponseData();
            } catch (Exception $e) {
                Log::error($e->getMessage());
                throw $e;
            }
            // Store data in the cache
            Cache::put('rocket_login', $loginResponseData, config('rocket.cache.ttl')); // Cache for 60 minutes
        }


        $authToken = $loginResponseData['data']['authToken'];
        $userId = $loginResponseData['data']['userId'];

        return array($authToken, $userId);
    }

    /**
     * @return void
     * @throws GuzzleException
     */
    public function sendMessage(): void
    {

        $client = new Client();

        $messageUri = "https://chat.depna.com/api/v1/chat.postMessage";

        $messageBody = json_encode([
            'channel' => $this->sendTo,
            'text' => $this->message
        ]);

        $messageResponse = $client->request('POST', $messageUri, [
            'headers' => [
                'X-Auth-Token' => $this->authToken,
                'X-User-Id' => $this->userId,
                'Content-Type' => 'application/json'
            ],
            'body' => $messageBody
        ]);
    }

    /**
     * @return mixed
     * @throws GuzzleException
     * @throws Exception
     */
    private function getLoginResponseData(): mixed
    {

        try {
            $client = new Client();

            $loginUri = "https://chat.depna.com/api/v1/login";
            $loginBody = json_encode([
                'username' => config('rocket.account.username'),
                'password' => config('rocket.account.password')
            ]);

            $loginResponse = $client->request('POST', $loginUri, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => $loginBody
            ]);

        } catch (Exception $e) {
            BotHelper::sendMessageToBotAdmin(new \Telegram(env('BOT_HADITH_TOKEN_BALE')), "error in read xml as rss" . $e->getMessage());
            throw $e;
        }

        return json_decode($loginResponse->getBody()->getContents(), true);
    }
}
