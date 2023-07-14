<?php

namespace App\Console\Commands;

use App\Interfaces\Services\QuranBotUserRankingService;
use Illuminate\Console\Command;

class UsersRankingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:users-ranking-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    private QuranBotUserRankingService $quranBotUserRankingService;

    public function __construct(QuranBotUserRankingService $quranBotUserRankingService)
    {
        parent::__construct();
        $this->quranBotUserRankingService = $quranBotUserRankingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->quranBotUserRankingService->sendToAllUsers();
    }

}
