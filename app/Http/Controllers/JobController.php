<?php

namespace App\Http\Controllers;


use App\Helpers\BotHelper;
use App\Models\BotLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class JobController extends Controller
{
    public function handle(Request $request)
    {
        $usersChatId = $this->getUsers();
        foreach ($usersChatId as $userChatId) {
            $this->sendLastMessageIfDidntHaveAnyActivity($userChatId);
        }
        return 0;
    }

    private function getUsers()
    {
        return ['485750575'];
    }

    private function sendLastMessageIfDidntHaveAnyActivity(int $userChatId)
    {
        $intervalHour = 24;
        if ($this->didntHaveAnyActivity($userChatId, $intervalHour)) {
            $this->sendLastMessage($userChatId);
        }
    }

    private function didntHaveAnyActivity(int $userChatId, $intervalHour)
    {
        $fromDate = Carbon::now()
            ->subHours($intervalHour)
            ->toDateString();

        $log = BotLog::query()
            ->whereChatId($userChatId)
            ->where('created_at', '>', $fromDate)
            ->get();

        return count($log) > 0;
    }

    /**
     * @throws \Exception
     */
    private function sendLastMessage(int $userChatId)
    {
        $last = BotLog::query()
            ->whereChatId($userChatId)
            ->whereWebhookEndpointUri( 'webhook-quran-word')
            ->whereIsCommand(1)
            ->where('text', 'LIKE', '/sure%')
            ->orderBy('created_at', 'desc')
            ->first();

//        dd($last);
//        dd(1);

        BotHelper::sendMessageToSuperAdmin($last->text,$last->type);
    }
}
