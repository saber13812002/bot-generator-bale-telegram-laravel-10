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
        $count = BotLog::where('created_at', '>=', Carbon::now()->subDay())->count();
        BotHelper::sendMessageToSuperAdmin("تعداد" . $count, 'telegram');

    }
}
