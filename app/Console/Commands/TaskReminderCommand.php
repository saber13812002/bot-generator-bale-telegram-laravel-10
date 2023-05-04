<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Models\BotLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TaskReminderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:task-reminder-command';

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
        //
        $count_daily = BotLog::where('created_at', '>=', Carbon::now()->subDay())->count();
        $count_unique_daily = BotLog::where('created_at', '>=', Carbon::now()->subDay())->distinct('chat_id')->count();


        $count_weekly = BotLog::where('created_at', '>=', Carbon::now()->subDay(7))->count();
        $count_unique_weekly = BotLog::where('created_at', '>=', Carbon::now()->subDay(7))->distinct('chat_id')->count();


        $count_monthly = BotLog::where('created_at', '>=', Carbon::now()->subDay(30))->count();
        $count_unique_monthly = BotLog::where('created_at', '>=', Carbon::now()->subDay(30))->distinct('chat_id')->count();


        $postfix_local = ' : ' . env('APP_ENV');

        $message = "آمار روز" . $count_daily . "
یونیک روز:" . $count_unique_daily . "
آمار هفته: " . $count_weekly . "
یونیک هفته: " . $count_unique_weekly . "
آمار یک ماه قبل: " . $count_monthly . "
یونیک سی روز قبل: " . $count_unique_monthly . "
" . $postfix_local;


        BotHelper::sendMessageToSuperAdmin($message, 'telegram');

        BotHelper::sendMessageToSuperAdmin($message, 'bale');

    }
}
