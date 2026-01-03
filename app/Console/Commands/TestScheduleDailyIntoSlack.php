<?php

namespace App\Console\Commands;

use App\Services\RocketChatService;
use Illuminate\Console\Command;

class TestScheduleDailyIntoSlack extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-schedule-daily-into-slack';

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
        $tarikh = verta()->addDays(-1)->formatWord('l d F');
        $rocket = new RocketChatService($tarikh. " - " ."طراحی و تحقیق و توسعه", "test");
        $rocket->sendMessage();
        $rocket = new RocketChatService($tarikh. " - " ."تست و پشتیبانی", "test");
        $rocket->sendMessage();
    }
}
