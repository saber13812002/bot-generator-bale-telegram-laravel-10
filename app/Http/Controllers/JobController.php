<?php

namespace App\Http\Controllers;


use App\Helpers\BotHelper;
use App\Models\BotLog;
use App\Models\BotUsers;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

class JobController extends Controller
{
    /**
     * @throws Exception
     */
    public function handle(Request $request)
    {
        if (!$request->has('token')) {
            return 0;
        }

        if ($request->input('token') != env('QURAN_HEFZ_BOT_TOKEN_BALE'))
            return -1;

        $intervalHour = 24;
        $type = 'bale';

        $usersChatId = $this->getUsers($intervalHour, $type);

        $count = 0;
        foreach ($usersChatId as $userChatId) {
            if ($this->sendLastMessageIfDidntHaveAnyActivityInLastHours($userChatId, $intervalHour, $type))
                $count++;
        }
        $message = 'ارسال برای ' . $count . ' نفر کسانی که در ۲۴ ساعت قبل فعالیت نداشتند ارسال شد ';
        BotHelper::sendMessageToSuperAdmin($message, $type);
        return 1;
    }

    private function getUsers($intervalHour = 24, $type = 'bale')
    {
        if (App::environment() == "local")
            return ['485750575'];

        $allUsersBale = BotUsers::whereOrigin($type)
            ->pluck('chat_id')->toArray();

        $usersHasActivity = BotLog::
        where('created_at', '>=', now()->subHours($intervalHour))
            ->where('chat_id', '>=', 0)
            ->whereType($type)
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->distinct('chat_id')
            ->pluck('chat_id')->toArray();

//        dd($allUsersBale, $usersHasActivity);
        $usersHasNotActivity = array_diff($allUsersBale, $usersHasActivity);
//        dd($usersHasNotActivity);

        return $usersHasNotActivity;
    }

    private function sendLastMessageIfDidntHaveAnyActivityInLastHours(int $userChatId, int $intervalHour = 24, $type = 'bale')
    {
        if ($this->didntHaveAnyActivityInLastHours($userChatId, $intervalHour)) {
            if ($this->sendLastMessage($userChatId, $type))
                return true;
        }
        return false;
    }

    private function didntHaveAnyActivityInLastHours(int $userChatId, $intervalHour)
    {
        $fromDate = Carbon::now()
            ->subHours($intervalHour)
            ->toDateString();

        $log = BotLog::query()
            ->whereChatId($userChatId)
            ->where('created_at', '>', $fromDate)
            ->get();

        return count($log) == 0;
    }

    /**
     * @throws Exception
     */
    private function sendLastMessage(int $userChatId, $type = 'bale')
    {
        $last = BotLog::query()
            ->whereChatId($userChatId)
            ->whereWebhookEndpointUri('webhook-quran-word')
            ->whereIsCommand(1)
            ->where('text', 'LIKE', '/sure%')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($last) {

            $message = "آخرین قرایت/تدبر/مطالعه/تلاوت شما:
" . BotHelper::generateLink($last->text, $type) . "
کلیک کنید ☝☝☝";

            BotHelper::sendMessageByDefaultQuranBot($message, $last->type, $userChatId);
            return true;
        }
        $message = "آخرین قرایت/تدبر/مطالعه/تلاوت شما:
" . BotHelper::generateLink("/sure2ayah1", $type) . "
کلیک کنید ☝☝☝";
        BotHelper::sendMessageByDefaultQuranBot($message, $type, $userChatId);
        return false;
    }
}
