<?php

namespace App\Services;

use App\Helpers\BotHelper;
use App\Helpers\StringHelper;
use App\Interfaces\Repositories\HadithApiRepository;
use App\Interfaces\Services\HadithApiService;
use Exception;
use Illuminate\Support\Facades\Log;

class HadithApiServiceImpl implements HadithApiService
{

    private HadithApiRepository $hadithApiRepository;

    public function __construct(HadithApiRepository $hadithApiRepository)
    {
        $this->hadithApiRepository = $hadithApiRepository;
    }

    /**
     */
    public function search(string $phrase, string $currentPage, string $pageSize): string
    {
        $message = $this->getMessageFromHadithApi($phrase, $currentPage, $pageSize);
        if ($message == "")
            return "هیچ حدیث یافت نشد";
        else
            return $message;
    }

    public function help($bot): string
    {
        BotHelper::sendMessage($bot, trans("hadith.command not found. please send me /search to search all Shia Hadith then send your phrase to search."));
        return "help";
    }

    /**
     * @param string $phrase
     * @param string $currentPage
     * @param string $pageSize
     * @return string
     */
    public function getMessageFromHadithApi(string $phrase, string $currentPage, string $pageSize): string
    {
        try {
            $academyOfIslamData = $this->hadithApiRepository->call($phrase, $currentPage, $pageSize);
        } catch (Exception $e) {
            Log::warning($e->getMessage());
//            throw $e;
            return StringHelper::findString($e->getMessage(), "Too Many Calls") ? substr($e->getMessage(), -180) : "خطای ناشناخته";
        }
        return self::generateMessageByHadithData($phrase, $academyOfIslamData);
    }

    /**
     * @param $botText
     * @param mixed $academyOfIslamData
     * @return string
     */
    private static function generateMessageByHadithData($botText, mixed $academyOfIslamData): string
    {
//        dd($botText, $academyOfIslamData);

        $raiseLimitCount = 0;
        $message = "";

        foreach ($academyOfIslamData["collection"] as $academyOfIslamDataItem) {
            $raiseLimitCount++;
            $message .= StringHelper::addLineToMessageForSecondItemToLast($raiseLimitCount);
            $message .= StringHelper::generateDetailHadithMessage($academyOfIslamDataItem);
        }
//        dd(1);
        if ($raiseLimitCount > 0) {
            return $message;
        }
        return "";
    }


}
